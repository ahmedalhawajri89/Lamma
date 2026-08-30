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

import { login, register } from './auth.js';
import { toast } from './ui.js';
import { $, $$, clamp } from './utils.js';

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

  card: (value) => luhnCheck(value) || 'رقم البطاقة غير صحيح',

  expiry: (value) => validExpiry(value) || 'تاريخ الانتهاء غير صالح',

  cvc: (value) => /^\d{3,4}$/.test(value.trim()) || 'رمز الأمان من ٣ أو ٤ أرقام',

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
      box.insertAdjacentHTML('afterbegin', '<i class="fa-solid fa-circle-exclamation"></i> ');
    }
  }
}

/** تشغيل قواعد حقل واحد. الترتيب مهم: "مطلوب" أولاً. */
function validateInput(input) {
  const value = input.value ?? '';
  const rules = (input.dataset.validate || '').split('|').filter(Boolean);

  // حقل اختياري وفارغ = صالح
  if (!rules.includes('required') && !value.trim()) {
    setError(input, '');
    return true;
  }

  for (const rule of rules) {
    const [name, param] = rule.split(':');
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
export function initForms() {
  $$('form[data-validate-form]').forEach((form) => {
    const inputs = $$('[data-validate]', form);

    // نتحقق عند الخروج من الحقل، ثم مباشرةً أثناء الكتابة بعد أول خطأ.
    // هذا يمنع إزعاج المستخدم بخطأ قبل أن ينتهي من الكتابة.
    inputs.forEach((input) => {
      input.addEventListener('blur', () => validateInput(input));
      input.addEventListener('input', () => {
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
        button.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin"></i> جارٍ المعالجة…';
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

  if (!action) {
    // لا خادم لهذا النموذج بعد — سلوك المحاكاة كما كان
    setTimeout(() => {
      const redirect = form.dataset.redirect;
      if (redirect) {
        window.location.href = redirect;
        return;
      }
      restore();
      form.reset();
      $$('.field', form).forEach((f) => f.classList.remove('is-invalid'));
      toast(form.dataset.successMessage || 'تم الإرسال بنجاح، شكراً لك', 'success');
    }, 900);
    return;
  }

  const values = Object.fromEntries(new FormData(form).entries());

  try {
    if (action === 'login') {
      await login(values.email, values.password, Boolean(values.remember));
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
        terms: true,
      });
    }

    // ?redirect= يعيد الكافل إلى الصفحة التي طُرد منها، لا إلى اللوحة دائماً
    const params = new URLSearchParams(window.location.search);
    window.location.href = params.get('redirect') || form.dataset.redirect || 'dashboard.html';
  } catch (error) {
    restore();

    const fields = error.errors ?? {};
    let focused = false;

    Object.entries(fields).forEach(([name, messages]) => {
      const input = form.querySelector(`[name="${name}"]`);
      const wrapper = input?.closest('.field');
      const errorEl = wrapper?.querySelector('.field__error');

      if (wrapper) wrapper.classList.add('is-invalid');
      if (errorEl) errorEl.textContent = messages[0];
      if (input && !focused) {
        input.focus();
        focused = true;
      }
    });

    if (!Object.keys(fields).length) {
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
  { label: 'ضعيفة جداً', color: 'var(--rose-600)' },
  { label: 'ضعيفة',      color: 'var(--rose-600)' },
  { label: 'متوسطة',     color: 'var(--amber-500)' },
  { label: 'جيدة',       color: 'var(--olive-500)' },
  { label: 'قوية',       color: 'var(--olive-600)' },
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
