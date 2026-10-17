/**
 * صفحة الكفالة وصفحة الشكر.
 *
 * كانت الصفحة محاكاة: زرّ الدفع لا يصل الخادم، والطفل يُقرأ من قائمة محلية
 * ويُستبدل بأولها إن لم يوجد، ثم تقول صفحة الشكر «تمّت الكفالة». هنا:
 *
 *  · الطفل من الخادم برمزه، ومعه المتبقي من حاجته وهل يقبل كفالة الآن.
 *  · المبلغ لا يتجاوز المتبقي، والإجمالي = المبلغ × أشهر الدورة.
 *  · رقم البطاقة لا يغادر الصفحة: يتحوّل «رمزاً» (كما تفعل البوابة الحقيقية
 *    بحقولها المستضافة)، ويُرسَل الرمز وآخر أربعة أرقام وحدها.
 *  · صفحة الشكر تُبنى مما ردّ به الخادم، لا من الرابط.
 */
import { $, $$, formatMoney } from './utils.js';
import { fetchOrphan, fetchCheckoutConfig, submitCheckout } from './api.js';
import { me, isSponsor, markSignedIn } from './auth.js';
import { launchConfetti } from './features.js';

const INTERVALS = { monthly: 1, quarterly: 3, yearly: 12 };
const CYCLE_WORDS = { 1: 'شهرياً', 3: 'كل ثلاثة أشهر', 12: 'سنوياً' };
const RESULT_KEY = 'kafala.checkout.result';
const DRAFT_KEY = 'kafala.checkout.draft';

/** المبلغ بعملته: الدولار برمزه، وغيره برمز ISO بعد الرقم */
const money = (v, currency = 'USD') =>
  currency === 'USD' ? formatMoney(v) : `${Number(v).toLocaleString('en-US', { maximumFractionDigits: 2 })} ${currency}`;

/* =========================================================================
   صفحة الكفالة
   ========================================================================= */

const state = {
  orphan: null,
  config: null,
  user: null,
  interval: 1,
};

export async function initCheckout() {
  const root = $('#checkoutSummary');
  if (!root) return;

  const reference = new URLSearchParams(window.location.search).get('id');
  const form = $('#checkoutForm');

  /*
   * زر الدفع معطّل حتى تكتمل البيانات: الضغط قبلها كان يومض بالمؤشر ثم يعود
   * بلا كلمة، فيظن الزائر أن الدفع فشل أو أنه لم يُسجَّل.
   */
  const submit = $('#checkoutSubmit');
  const submitLabel = submit?.innerHTML;
  if (submit) {
    submit.disabled = true;
    submit.innerHTML = '<i class="fa-solid fa-circle-notch fa-spin" aria-hidden="true"></i> جارٍ تحميل بيانات الكفالة…';
  }

  try {
    [state.orphan, state.config, state.user] = await Promise.all([
      reference ? fetchOrphan(reference) : Promise.reject({ status: 404 }),
      fetchCheckoutConfig(),
      me().catch(() => null),
    ]);
  } catch (error) {
    showBlocked(error?.status === 404
      ? 'لم نجد هذه الحالة. ربما تغيّر رابطها أو أُغلق ملفها.'
      : 'تعذّر تحميل بيانات الكفالة. أعد تحميل الصفحة بعد قليل.');
    return;
  }

  const o = state.orphan;

  // مكتمل التمويل أو مغلق: لا نموذج دفع لطفلٍ لا يقبل كفالة
  if (!o.accepting) {
    showBlocked(`اكتمل تمويل ${o.name} أو لم يعد ملفه يقبل كفالة جديدة. شكراً لك، وهناك أطفال آخرون ينتظرون.`);
    return;
  }

  renderChild();
  setupAmount();
  setupIdentity();
  setupMethods();
  setupCycle();
  restoreDraft();
  renderTotals();

  if (state.config.demo) $('[data-checkout="demo"]')?.removeAttribute('hidden');

  // «سجّل الدخول» يعيد إلى هذه الصفحة نفسها
  const login = $('[data-checkout="login-link"]');
  if (login) login.href = `/login?redirect=${encodeURIComponent(`/checkout?id=${o.id}`)}`;

  form.dataset.action = 'checkout';

  if (submit) {
    submit.disabled = false;
    submit.innerHTML = submitLabel;
  }
}

function showBlocked(message) {
  const form = $('#checkoutForm');
  const box = $('[data-checkout="blocked"]');
  if (form) form.hidden = true;
  if (box) {
    box.hidden = false;
    $('[data-checkout="blocked-text"]', box).textContent = message;
  }
  $$('[data-checkout="name"]').forEach((el) => { el.textContent = state.orphan?.name ?? 'الحالة'; });
}

function renderChild() {
  const o = state.orphan;
  $$('[data-checkout="name"]').forEach((el) => { el.textContent = o.name; });
  $$('[data-checkout="program"]').forEach((el) => { el.textContent = o.program_name ?? ''; });
  $$('[data-checkout="id"]').forEach((el) => { el.textContent = o.id; });
  $$('[data-checkout="remaining"]').forEach((el) => { el.textContent = money(o.remaining, o.currency); });

  // بلا صورة: الحرف الأول بدل صورة مكسورة
  const photo = $('[data-checkout="photo"]');
  const initial = $('[data-checkout="initial"]');
  if (o.photo && photo) {
    photo.src = o.photo;
    photo.hidden = false;
    if (initial) initial.hidden = true;
  } else if (initial) {
    initial.textContent = o.name?.charAt(0) ?? '';
  }
}

/** المبلغ: سعر البرنامج أو المتبقي أيّهما أقل، ولا يتجاوز المتبقي */
function setupAmount() {
  const o = state.orphan;
  const input = $('#monthlyAmount');
  if (!input) return;

  const suggested = Math.min(o.program_price ?? o.remaining, o.remaining);
  input.value = suggested;
  input.max = o.remaining;
  input.min = Math.min(5, o.remaining);
  input.dataset.validate = `required|range:${input.min}:${o.remaining}`;

  const hint = $('#hint-amount');
  if (hint) {
    hint.textContent = o.remaining < (o.monthly ?? o.remaining)
      ? `تبقّى من حاجة ${o.name} الشهرية ${money(o.remaining, o.currency)}، فكفالتك لا تتجاوزه.`
      : `حاجته الشهرية ${money(o.remaining, o.currency)}. يمكنك كفالتها كاملة أو جزءاً منها.`;
  }

  input.addEventListener('input', renderTotals);
}

/** الكافل الداخل لا يكتب اسمه وبريده من جديد */
function setupIdentity() {
  const user = state.user;
  const guest = $('[data-checkout="guest"]');
  const signed = $('[data-checkout="signed"]');

  if (user && isSponsor(user)) {
    if (guest) {
      guest.hidden = true;
      $$('input', guest).forEach((i) => { i.disabled = true; });
    }
    if (signed) {
      signed.hidden = false;
      $('[data-checkout="signed-name"]', signed).textContent = user.name;
    }
  }
}

/** حقول البطاقة للبطاقة وحدها؛ والتحويل يعرض ما سيحدث بعده */
function setupMethods() {
  const radios = $$('input[name="method"]');
  const cardFields = $('[data-checkout="card-fields"]');
  const bankNote = $('[data-checkout="bank-note"]');

  const apply = () => {
    const method = radios.find((r) => r.checked)?.value ?? 'card';
    const card = method === 'card';

    if (cardFields) {
      cardFields.hidden = !card;
      $$('input', cardFields).forEach((i) => { i.disabled = !card; });
    }
    if (bankNote) {
      bankNote.hidden = card;
      const days = state.config?.grace_days ?? 5;
      $('[data-checkout="grace"]', bankNote).textContent = `${days} أيام`;
    }

    const submit = $('#checkoutSubmit');
    if (submit) submit.lastChild.textContent = card ? ' ادفع وفعّل الكفالة' : ' احجز الكفالة واعرض بيانات التحويل';
    renderTotals();
  };

  radios.forEach((r) => r.addEventListener('change', apply));
  apply();
}

function setupCycle() {
  const chips = $('[data-chips]');
  const read = () => INTERVALS[$('.chip.is-active', chips ?? document)?.dataset.value] ?? 1;
  state.interval = read();
  chips?.addEventListener('chip:change', (event) => {
    state.interval = INTERVALS[event.detail?.value] ?? 1;
    renderTotals();
  });
}

/** الإجمالي اليوم، ثم ما يتكرر ومتى: لا يدفع أحد قبل أن يعرف ما سيُخصم */
function renderTotals() {
  const o = state.orphan;
  if (!o) return;

  const amount = Number($('#monthlyAmount')?.value) || 0;
  const total = amount * state.interval;
  const method = $$('input[name="method"]').find((r) => r.checked)?.value ?? 'card';
  const day = new Date().getDate();

  $$('[data-checkout="price"]').forEach((el) => { el.textContent = money(amount, o.currency); });
  $$('[data-checkout="total"]').forEach((el) => { el.textContent = money(total, o.currency); });
  $$('[data-checkout="cycle"]').forEach((el) => {
    el.textContent = state.interval === 1 ? '' : `عن ${state.interval === 3 ? 'ثلاثة أشهر' : 'سنة'}`;
  });

  const plan = method === 'card'
    ? `تُخصم ${money(total, o.currency)} اليوم، ثم ${CYCLE_WORDS[state.interval]} يوم ${Math.min(day, 28)} من الشهر. تستطيع الإيقاف متى شئت.`
    : `بعد تحويلك ${money(total, o.currency)} تتفعّل الكفالة، ثم يتكرر التحويل ${CYCLE_WORDS[state.interval]}.`;
  $$('[data-checkout="plan"]').forEach((el) => { el.textContent = plan; });
}

/* ── الحفظ قبل الدخول: من طُلب منه الدخول يعود فيجد اختياراته ── */

function saveDraft(form) {
  try {
    sessionStorage.setItem(DRAFT_KEY, JSON.stringify({
      orphan: state.orphan.id,
      amount: $('#monthlyAmount')?.value,
      interval: state.interval,
      zakat: $('[name="is_zakat"]', form)?.checked ?? false,
      method: $$('input[name="method"]').find((r) => r.checked)?.value,
    }));
  } catch { /* التخزين قد يكون معطّلاً: يعيد الاختيار يدوياً */ }
}

function restoreDraft() {
  let draft = null;
  try { draft = JSON.parse(sessionStorage.getItem(DRAFT_KEY) || 'null'); } catch { /* لا شيء */ }
  if (!draft || draft.orphan !== state.orphan.id) return;

  if (draft.amount) $('#monthlyAmount').value = Math.min(Number(draft.amount), state.orphan.remaining);
  const chip = $(`[data-chips] .chip[data-value="${Object.keys(INTERVALS).find((k) => INTERVALS[k] === draft.interval)}"]`);
  chip?.click();
  const zakat = $('[name="is_zakat"]');
  if (zakat) zakat.checked = Boolean(draft.zakat);
  const radio = $(`input[name="method"][value="${draft.method}"]`);
  if (radio) { radio.checked = true; radio.dispatchEvent(new Event('change')); }

  try { sessionStorage.removeItem(DRAFT_KEY); } catch { /* لا شيء */ }
}

/* ── الإرسال ── */

/**
 * «الترميز»: في البوابة الحقيقية تحوّل حقولها المستضافة الرقمَ إلى رمز قبل أن
 * يصل صفحتنا. وفي الوضع التجريبي نحاكي ذلك ببطاقتي الاختبار. في الحالتين
 * لا يُرسَل الرقم إلى خادمنا.
 */
function tokenize(number) {
  const digits = number.replace(/\D/g, '');
  const token = digits === '4000000000000002'
    ? 'tok_demo_decline'
    : digits === '4242424242424242' ? 'tok_demo_ok' : 'tok_demo_invalid';
  return { token, last4: digits.slice(-4), brand: digits.startsWith('4') ? 'فيزا' : digits.startsWith('5') ? 'ماستركارد' : 'بطاقة' };
}

function attemptKey() {
  // مفتاح المحاولة يبقى ثابتاً لإعادة الإرسال نفسها، ويتجدد بعد كفالة ناجحة
  try {
    let key = sessionStorage.getItem('kafala.checkout.key');
    if (!key) {
      key = crypto.randomUUID();
      sessionStorage.setItem('kafala.checkout.key', key);
    }
    return key;
  } catch {
    return crypto.randomUUID();
  }
}

/** يُستدعى من forms.js بعد نجاح التحقق في المتصفح */
export async function submitCheckoutForm(form) {
  const values = Object.fromEntries(new FormData(form).entries());
  const method = values.method ?? 'card';

  // خطأ المحاولة السابقة لا يبقى معلّقاً فوق الزر أثناء المحاولة الجديدة
  const box = $('[data-checkout="error"]');
  if (box) { box.hidden = true; box.textContent = ''; }

  const payload = {
    orphan_reference: state.orphan.id,
    monthly_amount: Number($('#monthlyAmount').value),
    interval_months: state.interval,
    is_zakat: Boolean($('[name="is_zakat"]', form)?.checked),
    method,
  };

  if (!(state.user && isSponsor(state.user))) {
    Object.assign(payload, { name: values.name, email: values.email, phone: values.phone || null });
  }

  if (method === 'card') {
    const card = tokenize($('#cardNumber').value);
    Object.assign(payload, { card_token: card.token, card_last4: card.last4, card_brand: card.brand });
  }

  try {
    const { data } = await submitCheckout(payload, attemptKey());

    try {
      sessionStorage.setItem(RESULT_KEY, JSON.stringify(data));
      sessionStorage.removeItem('kafala.checkout.key');
    } catch { /* صفحة الشكر تعرض ما تستطيع */ }

    if (data.account_created) markSignedIn();
    window.location.href = `/thank-you?ref=${encodeURIComponent(data.reference)}`;
  } catch (error) {
    if (error.code === 'login_required') {
      saveDraft(form);
      const back = `/checkout?id=${encodeURIComponent(state.orphan.id)}`;
      error.redirect = `/login?redirect=${encodeURIComponent(back)}`;
    }

    // أخطاء لا حقل لها في النموذج (الطفل اكتمل تمويله، رفض البنك) تظهر فوق الزر،
    // ولا تتكرّر في إشعار عائم: موضعٌ واحد قرب الزر أوضح من رسالتين
    if (box && !error.redirect && (error.code || error.errors?.orphan_reference || error.status === 402)) {
      box.hidden = false;
      box.textContent = error.errors?.orphan_reference?.[0] ?? error.message;
      error.shown = true;
    }

    throw error;
  }
}

/* =========================================================================
   صفحة الشكر: مما ردّ به الخادم
   ========================================================================= */
export function initThankYou() {
  const card = $('#thanksCard');
  if (!card) return;

  const ref = new URLSearchParams(window.location.search).get('ref');
  let data = null;
  try { data = JSON.parse(sessionStorage.getItem(RESULT_KEY) || 'null'); } catch { /* لا شيء */ }

  // بلا كفالة حديثة في هذه الجلسة لا «تمّت الكفالة»: صفحة الشكر لا تُفتح بالرابط وحده
  if (!data || data.reference !== ref) {
    card.hidden = true;
    $('#thanksNone')?.removeAttribute('hidden');
    return;
  }

  const set = (key, value) => $$(`[data-thanks="${key}"]`).forEach((el) => { el.textContent = value; });
  const paid = data.status === 'active';

  set('name', data.orphan?.name ?? '');
  set('ref', data.reference);
  set('amount', money(data.cycle_amount, data.currency));
  set('cycle', CYCLE_WORDS[data.interval_months] ?? 'شهرياً');
  set('next', data.next_due_at ?? '');
  set('receipt', data.receipt_number ?? '');

  $$('[data-thanks-when="paid"]').forEach((el) => { el.hidden = !paid; });
  $$('[data-thanks-when="pending"]').forEach((el) => { el.hidden = paid; });
  $$('[data-thanks-when="account"]').forEach((el) => { el.hidden = !data.account_created; });
  $$('[data-thanks-when="demo"]').forEach((el) => { el.hidden = !data.demo; });

  // احتفالٌ بما تمّ فعلاً: الحجز بانتظار التحويل لم يتمّ بعد
  if (paid) launchConfetti();

  if (!paid && data.bank) {
    set('bank-name', data.bank.name);
    set('bank-account', data.bank.account_name);
    set('bank-iban', data.bank.iban);
    set('bank-swift', data.bank.swift);
    set('hold', data.hold_expires_at ?? '');
  }
}
