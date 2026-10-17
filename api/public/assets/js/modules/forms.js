/**
 * التحقق من النماذج ومعالجة المدخلات الحساسة.
 *
 * ملاحظة أمنية مهمة:
 * هذا مشروع واجهة أمامية فقط. التحقق هنا لتحسين التجربة وتقليل الأخطاء،
 * وليس بديلاً عن التحقق في الخادم — أي تحقق يعمل في المتصفح يمكن تجاوزه.
 * لذلك:
 *   • لا نُخزّن أي بيانات بطاقة في localStorage أو في أي مكان.
 *   • حقول البطاقة تحمل autocomplete الصحيح ولا تُرسل عبر GET أبداً.
 *   • كل رسائل الخطأ نصية (textContent) فلا مجال لحقن HTML.
 */

import { login, logout, register, forgotPassword, resetPassword, markSignedIn, isSponsor } from './auth.js';
import { submitContact, subscribeNewsletter } from './api.js';
import { toast } from './ui.js';
import { $, $$, clamp, toLatinDigits } from './utils.js';
import { submitCheckoutForm } from './checkout.js';

/**
 * وجهة ما بعد الدخول من الرابط، على هذا الموقع وحده.
 *
 * كانت تُستعمل كما هي: `/login?redirect=https://موقع-آخر` يرسل الكافل
 * بعد دخولٍ حقيقي إلى صفحة تصيّد تشبه الموقع. المقبول مسار نسبي على الأصل
 * نفسه، وما سواه يُتجاهل.
 */
export function safeRedirect(value) {
  // `/\evil.com` يقرؤه بعض المتصفحات `//evil.com`، فالشرطة المائلة العكسية تُرفض كلها
  if (!value || value.startsWith('//') || value.includes('\\')) return null;
  try {
    const url = new URL(value, window.location.origin);
    if (url.origin !== window.location.origin) return null;
    return url.pathname.replace(/^\//, '') + url.search + url.hash;
  } catch {
    return null;
  }
}

/* =========================================================================
   قواعد التحقق
   ========================================================================= */
const RULES = {
  required: (value) => value.trim().length > 0 || 'هذا الحقل مطلوب',

  email: (value) =>
    /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value.trim()) || 'صيغة البريد الإلكتروني غير صحيحة',

  // يقبل الأرقام الدولية بصيغ شائعة، ٩ إلى ١٥ رقماً
  phone: (value) =>
    /^\+?[\d\s-]{9,18}$/.test(value.trim()) || 'أدخل رقم هاتف صحيح',

  minlength: (value, param) =>
    value.trim().length >= Number(param) || `يجب ألا يقل عن ${param} أحرف`,

  // ما يشترطه الخادم بعينه (Password::letters()->numbers()): حرفٌ ورقم بأي لغة،
  // فيُعرف الرفض هنا قبل أن يُرسل النموذج ويعود
  lettersNumbers: (value) =>
    (/\p{L}/u.test(value) && /\p{N}/u.test(value)) || 'يجب أن تحتوي حرفاً ورقماً على الأقل',

  card: (value) => luhnCheck(value) || 'رقم البطاقة غير صحيح',

  expiry: (value) => validExpiry(value) || 'تاريخ الانتهاء غير صالح',

  cvc: (value) => /^\d{3,4}$/.test(value.trim()) || 'رمز الأمان من ٣ أو ٤ أرقام',

  // مبلغ بين حدّين: `range:5:90`
  range: (value, param) => {
    const [min, max] = param.split(':').map(Number);
    const n = Number(value);
    if (!Number.isFinite(n)) return 'اكتب مبلغاً بالأرقام';
    if (n < min) return `أقل مبلغ ${min}`;
    return n <= max || `أكثر ما يمكن ${max}، وهو المتبقي من حاجته`;
  },

  match: (value, param) => {
    const other = document.getElementById(param);
    return (other && value === other.value) || 'القيمتان غير متطابقتين';
  },
};

/**
 * خوارزمية Luhn — الفحص القياسي لأرقام بطاقات الدفع.
 * تلتقط أخطاء الكتابة قبل إرسال العملية، فتوفّر على المستخدم رفضاً من البنك.
 */
function luhnCheck(value) {
  const digits = value.replace(/\D/g, '');
  if (digits.length < 13 || digits.length > 19) return false;

  let sum = 0;
  let double = false;

  for (let i = digits.length - 1; i >= 0; i -= 1) {
    let digit = Number(digits[i]);
    if (double) {
      digit *= 2;
      if (digit > 9) digit -= 9;
    }
    sum += digit;
    double = !double;
  }

  return sum % 10 === 0;
}

/** التحقق من MM/YY وأنه لم يمضِ. */
function validExpiry(value) {
  const match = value.trim().match(/^(\d{2})\s*\/\s*(\d{2})$/);
  if (!match) return false;

  const month = Number(match[1]);
  const year = 2000 + Number(match[2]);
  if (month < 1 || month > 12) return false;

  const now = new Date();
  const endOfMonth = new Date(year, month, 0, 23, 59, 59);
  return endOfMonth >= now;
}

/* =========================================================================
   عرض الأخطاء
   ========================================================================= */
function setError(input, message) {
  const field = input.closest('.field');
  if (!field) return;

  const box = $('.field__error', field);
  field.classList.toggle('is-invalid', Boolean(message));
  input.setAttribute('aria-invalid', String(Boolean(message)));

  if (box) {
    // نص فقط — لا innerHTML مع محتوى متغيّر
    box.textContent = message || '';
    if (message) {
      box.insertAdjacentHTML('afterbegin', '<i class="fa-solid fa-circle-exclamation" aria-hidden="true"></i> ');
    }
  }
}

/** تشغيل قواعد حقل واحد. الترتيب مهم: "مطلوب" أولاً. */
function validateInput(input) {
  // حقلٌ معطّل خارج النموذج الآن (حقول البطاقة حين يُختار التحويل)
  if (input.disabled) {
    setError(input, '');
    return true;
  }

  const rules = (input.dataset.validate || '').split('|').filter(Boolean);

  // خانة الموافقة قيمتها «on» دائماً، فالعبرة بحالة التأشير لا بالقيمة
  if (input.type === 'checkbox') {
    const ok = !rules.includes('required') || input.checked;
    setError(input, ok ? '' : (input.dataset.requiredMessage || 'يلزم التأشير على هذه الخانة للمتابعة'));
    return ok;
  }

  const value = input.value ?? '';

  // حقل اختياري وفارغ = صالح
  if (!rules.includes('required') && !value.trim()) {
    setError(input, '');
    return true;
  }

  for (const rule of rules) {
    const [name, ...rest] = rule.split(':');
    const param = rest.join(':');
    const check = RULES[name];
    if (!check) continue;

    const result = check(value, param);
    if (result !== true) {
      setError(input, result);
      return false;
    }
  }

  setError(input, '');
  return true;
}

/* =========================================================================
   ربط النماذج
   ========================================================================= */
/**
 * الحقول الرقمية تقبل «٥٠» كما تقبل «50».
 *
 * كان المبلغ المكتوب بلوحة عربية يُرفض بـ«اكتب مبلغاً بالأرقام»، وأرقام
 * البطاقة تُحذف كلها لأن `\D` لا يعدّها أرقاماً. التحويل في مرحلة الالتقاط
 * يسبق أي معالج آخر (التنسيق والتحقق والمعاينة)، والطول لا يتغير فيبقى
 * المؤشر مكانه. كلمات المرور مستثناة: «٣» فيها حرفٌ مقصود لا يُبدَّل.
 */
function initDigitInputs() {
  const numeric = 'input[inputmode="numeric"], input[inputmode="decimal"], input[inputmode="tel"], input[type="tel"], input[type="number"]';

  document.addEventListener('input', (e) => {
    const input = e.target;
    if (!input.matches?.(numeric)) return;

    const latin = toLatinDigits(input.value);
    if (latin === input.value) return;

    const { selectionStart, selectionEnd } = input;
    input.value = latin;
    try { input.setSelectionRange(selectionStart, selectionEnd); } catch { /* type=number لا يدعم التحديد */ }
  }, true);
}

export function initForms() {
  initDigitInputs();
  $$('form[data-validate-form]').forEach((form) => {
    const inputs = $$('[data-validate]', form);

    // «مطلوب» يُعلَن لقارئ الشاشة لا بالنجمة وحدها (وهي مخفية عنه)
    inputs.forEach((input) => {
      if (input.dataset.validate.split('|').includes('required')) input.setAttribute('aria-required', 'true');
    });

    // نتحقق عند الخروج من الحقل، ثم مباشرةً أثناء الكتابة بعد أول خطأ.
    // هذا يمنع إزعاج المستخدم بخطأ قبل أن ينتهي من الكتابة.
    //
    // وخطأ الخادم («بيانات الدخول غير صحيحة») يبقى حتى يُعدَّل الحقل: كان الخروج
    // منه يمحوه لأن صيغته صحيحة، فتقفز الصفحة تحت المؤشر وتضيع نقرة «نسيت
    // كلمة المرور؟»، وتختفي الرسالة قبل أن تُقرأ.
    inputs.forEach((input) => {
      input.addEventListener('blur', () => {
        if (input.dataset.serverError === input.value) return;
        validateInput(input);
      });
      input.addEventListener('input', () => {
        delete input.dataset.serverError;
        if (input.closest('.field')?.classList.contains('is-invalid')) validateInput(input);
      });
    });

    form.addEventListener('submit', (e) => {
      e.preventDefault();

      const results = inputs.map(validateInput);
      const firstInvalid = inputs[results.indexOf(false)];

      if (firstInvalid) {
        firstInvalid.focus();
        firstInvalid.scrollIntoView({ block: 'center', behavior: 'smooth' });
        toast('يرجى تصحيح الحقول المعلَّمة بالأحمر', 'error');
        return;
      }

      const button = $('[type="submit"]', form);
      const original = button?.innerHTML;

      if (button) {
        button.disabled = true;
        button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i> جارٍ المعالجة…';
      }

      submit(form, button, original);
    });
  });
}

/**
 * إرسال النموذج.
 *
 * `data-action` يحدّد الوجهة: نماذج المصادقة تُنادي الـ API، وما عداها
 * (‏الدفع مثلاً) يبقى محاكاةً حتى تُربط بوابة الدفع.
 *
 * أخطاء الحقول تُعرض تحت حقولها لا في تنبيه عام: «البريد مسجَّل لدينا»
 * فوق حقل البريد أوضح من رسالة عائمة تختفي بعد ثوانٍ.
 */
async function submit(form, button, original) {
  const restore = () => {
    if (button) {
      button.disabled = false;
      button.innerHTML = original;
    }
  };

  const action = form.dataset.action;

  /*
   * نموذجٌ بلا وجهة لا يدّعي النجاح. كان هذا الفرع «محاكاة» تنتقل بعد ثانية
   * إلى صفحة الشكر، فبدت الكفالة تمّت ولم يصل الخادم شيء. صفحة الكفالة
   * تضبط وجهتها بعد تحميل الطفل، فإن أُرسلت قبل ذلك يعود الزرّ ببساطة.
   */
  if (!action) {
    restore();
    return;
  }

  const values = Object.fromEntries(new FormData(form).entries());

  /**
   * نموذجا التواصل والنشرة لا ينتقلان بعد النجاح: المستخدم يبقى مكانه
   * ويحتاج تأكيداً في الصفحة. أما الدخول والتسجيل فينتهيان بانتقال.
   */
  const staysOnPage = action === 'contact' || action === 'newsletter' || action === 'forgot';

  try {
    // الكفالة: الوحدة تُرسل وتنتقل إلى صفحة الشكر، والأخطاء تعود إلى هنا
    if (action === 'checkout') {
      await submitCheckoutForm(form);
      return;
    }

    if (action === 'contact') {
      const { message } = await submitContact({
        name: values.name,
        email: values.email,

        // حقول النموذج مسمّاة بلغة الواجهة (topic و message)،
        // والربط بأسماء الـ API يقع هنا لا بتشويه الـ HTML الدلالي
        subject: values.topic || null,
        body: values.message,
        website: values.website ?? '',
      });

      restore();
      form.reset();
      $$('.field', form).forEach((f) => f.classList.remove('is-invalid'));
      toast(message || form.dataset.successMessage, 'success');

      return;
    }

    if (action === 'newsletter') {
      const { message } = await subscribeNewsletter(values.email, form.dataset.source || 'footer');

      restore();
      form.reset();
      toast(message || form.dataset.successMessage, 'success');

      return;
    }

    /**
     * طلب رابط الاستعادة.
     *
     * الرسالة تأتي من الخادم لا من `data-success-message`: صياغتها مقصودة
     * («إن كان هذا البريد مسجّلاً…») كي لا تكشف الشاشة أي البُرد لدينا.
     * ورسالةٌ محلية مبهجة تنقض ذلك بلا أن ينتبه أحد.
     */
    if (action === 'forgot') {
      const { message } = await forgotPassword(values.email);

      restore();
      form.reset();
      $$('.field', form).forEach((fl) => fl.classList.remove('is-invalid'));

      // صفحةٌ لها حالة «أُرسل» تعرضها (صفحة الدخول)، وإلا فإشعار
      const sent = new CustomEvent('form:sent', { cancelable: true, detail: { email: values.email, message } });
      if (form.dispatchEvent(sent)) toast(message, 'success');

      return;
    }

    if (action === 'reset') {
      const { message } = await resetPassword({
        token: values.token,
        email: values.email,
        password: values.password,
        password_confirmation: values.password_confirmation,
      });

      toast(message, 'success');

      // مهلة قصيرة ليُقرأ التأكيد قبل أن تنتقل الصفحة
      setTimeout(() => { window.location.href = '/login'; }, 1400);

      return;
    }

    if (action === 'login') {
      const { data: user } = await login(values.email, values.password, Boolean(values.remember));

      // حساب فريق العمل لا يدخل لوحة الكافل: نغلق جلسته فوراً ونوجّهه
      if (!isSponsor(user)) {
        await logout().catch(() => {});
        throw {
          staff: true,
          errors: { email: ['هذا حساب فريق العمل، والدخول به من لوحة الإدارة.'] },
        };
      }
    } else if (action === 'register') {
      // حقول النموذج مسمّاة بقيم autocomplete (given-name وtel) لأنها الأصحّ
      // دلالياً للمتصفح؛ الربط بأسماء الـ API يقع هنا لا بتشويه الـ HTML
      const fullName = [values['given-name'], values['family-name']]
        .filter(Boolean)
        .join(' ')
        .trim();

      await register({
        name: fullName || values.name,
        email: values.email,
        phone: values.tel || values.phone || null,
        password: values.password,
        password_confirmation: values.password_confirmation ?? values.password,
        // القيمة الفعلية للخانة، فالخادم يتحقق منها بـ accepted
        terms: values.terms === 'on',
      });
    }

    markSignedIn();

    // ?redirect= يعيد الكافل إلى الصفحة التي طُرد منها، لا إلى اللوحة دائماً
    const params = new URLSearchParams(window.location.search);
    window.location.href = safeRedirect(params.get('redirect')) || form.dataset.redirect || '/dashboard';
  } catch (error) {
    restore();

    // بريدٌ مسجَّل في صفحة الكفالة: إلى الدخول، والاختيارات محفوظة للعودة
    if (error.redirect) {
      toast(error.message, 'error');
      setTimeout(() => { window.location.href = error.redirect; }, 1600);
      return;
    }

    const fields = error.errors ?? {};
    let focused = false;

    /**
     * رسالة لا تجد حقلاً ظاهراً تُعرض كإشعار.
     *
     * الخادم يعلّق الخطأ على اسم حقل، وقد يكون ذلك الحقل مخفياً: صفحة تعيين
     * كلمة المرور تحمل `token` و`email` مخفيَّين، فرسالة «الرابط منتهي
     * الصلاحية» كانت تُكتب في عنصرٍ غير موجود وتضيع — يضغط المستخدم «حفظ»
     * فلا يحدث شيء ولا يُقال له لماذا. الصمت أسوأ من أي رسالة.
     */
    const orphaned = [];

    Object.entries(fields).forEach(([name, messages]) => {
      const input = form.querySelector(`[name="${name}"]`);
      const errorEl = input?.closest('.field')?.querySelector('.field__error');

      // setError لا كتابة النص وحده: يضبط aria-invalid والأيقونة كخطأ المتصفح تماماً
      if (errorEl && input.type !== 'hidden') {
        setError(input, messages[0]);
        input.dataset.serverError = input.value;
      } else {
        orphaned.push(messages[0]);
      }

      if (input && input.type !== 'hidden' && !focused) {
        input.focus();
        focused = true;
      }
    });

    // رابط مباشر للوحة الإدارة تحت رسالة حساب الفريق
    if (error.staff) {
      const errorEl = form.querySelector('[name="email"]')?.closest('.field')?.querySelector('.field__error');
      if (errorEl) {
        const link = document.createElement('a');
        link.href = '/admin';
        link.textContent = ' افتح لوحة الإدارة';
        errorEl.append(link);
      }
    }

    if (error.shown) {
      // عُرض في مكانه من الصفحة (صندوق خطأ الكفالة فوق الزر)
    } else if (orphaned.length) {
      toast(orphaned[0], 'error');
    } else if (!Object.keys(fields).length) {
      toast(error.message ?? 'تعذّر إتمام الطلب.', 'error');
    }
  }
}

/* =========================================================================
   تنسيق حقول البطاقة + المعاينة الحية
   ========================================================================= */
export function initCardInput() {
  const number = $('#cardNumber');
  const expiry = $('#cardExpiry');
  const cvc = $('#cardCvc');
  const holder = $('#cardHolder');

  const previewNumber = $('#previewNumber');
  const previewHolder = $('#previewHolder');
  const previewExpiry = $('#previewExpiry');

  if (number) {
    number.addEventListener('input', () => {
      const digits = number.value.replace(/\D/g, '').slice(0, 19);
      number.value = digits.replace(/(.{4})/g, '$1 ').trim();

      if (previewNumber) {
        const padded = digits.padEnd(16, '•');
        previewNumber.textContent = padded.replace(/(.{4})/g, '$1 ').trim();
      }
    });
  }

  if (expiry) {
    expiry.addEventListener('input', () => {
      const digits = expiry.value.replace(/\D/g, '').slice(0, 4);
      expiry.value = digits.length > 2 ? `${digits.slice(0, 2)}/${digits.slice(2)}` : digits;
      if (previewExpiry) previewExpiry.textContent = expiry.value || 'MM/YY';
    });
  }

  if (cvc) {
    cvc.addEventListener('input', () => {
      cvc.value = cvc.value.replace(/\D/g, '').slice(0, 4);
    });
  }

  if (holder && previewHolder) {
    holder.addEventListener('input', () => {
      // textContent يمنع أي حقن من مدخلات المستخدم
      previewHolder.textContent = holder.value.trim().toUpperCase() || 'الاسم على البطاقة';
    });
  }
}

/* =========================================================================
   مقياس قوة كلمة المرور
   ========================================================================= */
const STRENGTH_LEVELS = [
  { label: 'ضعيفة جداً', color: 'var(--color-urgent)' },
  { label: 'ضعيفة',      color: 'var(--color-urgent)' },
  { label: 'متوسطة',     color: 'var(--color-accent)' },
  { label: 'جيدة',       color: 'var(--color-brand)' },
  { label: 'قوية',       color: 'var(--color-success)' },
];

function scorePassword(value) {
  let score = 0;
  if (value.length >= 8) score += 1;
  if (value.length >= 12) score += 1;
  if (/[a-z]/.test(value) && /[A-Z]/.test(value)) score += 1;
  if (/\d/.test(value)) score += 1;
  if (/[^\w\s]/.test(value)) score += 1;
  return clamp(score, 0, 4);
}

export function initPasswordStrength() {
  const input = $('[data-strength-for]');
  const meter = $('#passwordStrength');
  if (!input || !meter) return;

  const bars = $$('.strength__bar', meter);
  const label = $('.strength__label', meter);

  input.addEventListener('input', () => {
    if (!input.value) {
      bars.forEach((bar) => bar.classList.remove('is-on'));
      label.textContent = 'استخدم ٨ أحرف على الأقل مع أرقام ورموز';
      return;
    }

    const score = scorePassword(input.value);
    const level = STRENGTH_LEVELS[score];

    bars.forEach((bar, i) => {
      bar.classList.toggle('is-on', i <= score);
      bar.style.setProperty('--strength-color', level.color);
    });

    label.textContent = `قوة كلمة المرور: ${level.label}`;
  });
}
