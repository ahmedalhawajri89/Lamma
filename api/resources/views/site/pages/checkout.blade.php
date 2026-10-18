@extends('site.layouts.app')

{{--
  صفحة الكفالة. الملخص يرسمه الخادم (OrphanPageController::checkout)، والنموذج
  يكمله checkout.js: المبلغ والدورية والهوية والدفع، والإرسال إلى الـ API.

  أمان البطاقة: حقولها بلا `name`، فلا تدخل FormData ولا تُرسل أبداً. يتحوّل
  الرقم في الصفحة رمزاً (tokenize) ويُرسل الرمز وآخر أربعة أرقام وحدها، ولا
  تُحفظ في مسودة ما قبل الدخول.
--}}
@php
  if ($kid) {
      $her = $kid['her'];
      $remaining = (float) $kid['remaining'];
      $price = (float) ($kid['program_price'] ?? $remaining);
      // مبالغ جاهزة: سعر البرنامج، ونصف المتبقي، والمتبقي كاملاً (5 فأكثر، بلا تكرار)
      $presets = collect([min($price, $remaining), round($remaining / 2), $remaining])
          ->map(fn ($v) => (float) $v)->filter(fn ($v) => $v >= 5 || $v === $remaining)
          ->unique()->sort()->values();
      $suggested = min($price, $remaining);
      // بدأ غيره كفالته: «أكمل كفالة…» والمتبقي لا الحاجة كاملة
      $shared = $remaining < (float) $kid['monthly'];
  }
@endphp

@section('title'){{ $kid ? 'كفالة '.$kid['name'] : 'إتمام الكفالة' }}@endsection
@section('description')خطوة أخيرة لتفعيل كفالتك. دفع مشفّر، ولا نحتفظ ببيانات بطاقتك.@endsection
@section('robots', 'noindex, nofollow')

@push('styles')
  <link rel="stylesheet" href="/assets/css/checkout.css">
@endpush

@section('content')
  <main id="main" class="co">
    <div class="container">

      @if (! $kid || ! $kid['accepting'])
        {{-- لا نموذج دفع لطفلٍ لا يقبل كفالة، ولا لرمزٍ لا طفل له --}}
        <section class="co-blocked">
          <span class="co-blocked__icon" aria-hidden="true"><i class="fa-solid {{ $kid ? 'fa-circle-check' : 'fa-child' }}"></i></span>
          @if ($kid)
            <h1>اكتملت كفالة {{ $kid['name'] }}</h1>
            <p>{{ $her ? 'كفالتها' : 'كفالته' }} مكتملة الآن، شكراً لك. وهناك أطفال آخرون ينتظرون من يقف معهم.</p>
          @else
            <h1>لم نجد هذه الحالة</h1>
            <p>ربما تغيّر رابطها أو لم يعد ملفها منشوراً.</p>
          @endif
          <a class="btn btn-cta btn-lg" href="/orphans">أطفال ينتظرون كافلاً</a>
        </section>
      @else

      <header class="co-head">
        <a class="co-back" href="/orphan?id={{ urlencode($kid['id']) }}"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> ملف {{ $kid['name'] }}</a>
        <h1 class="co-head__title">{{ $shared ? 'أكمل كفالة' : 'كفالة' }} {{ $kid['name'] }}</h1>
        <ol class="co-steps" aria-label="مراحل الكفالة">
          <li class="is-done"><span aria-hidden="true"><i class="fa-solid fa-check"></i></span> اختيار الطفل</li>
          <li aria-current="step"><span class="num" aria-hidden="true">2</span> المبلغ والدفع</li>
          <li><span class="num" aria-hidden="true">3</span> التأكيد</li>
        </ol>
      </header>

      <div class="row g-4 g-xl-5" id="checkoutSummary">

        {{-- ══ النموذج ══ --}}
        <div class="col-lg-7 order-2 order-lg-1">
          {{-- البوابة تجريبية: لا يظن أحد أنه دفع مالاً حقيقياً --}}
          <div class="co-demo" data-checkout="demo" role="note" hidden>
            <i class="fa-solid fa-flask" aria-hidden="true"></i>
            <span><strong>وضع تجريبي:</strong> لا يُخصم أي مال. للقبول <span class="num" dir="ltr">4242 4242 4242 4242</span>، وللرفض <span class="num" dir="ltr">4000 0000 0000 0002</span>، بأي تاريخ قادم وأي رمز.</span>
          </div>

          <div data-checkout="blocked" class="co-blocked co-blocked--inline" role="status" hidden>
            <p data-checkout="blocked-text"></p>
            <a class="btn btn-primary" href="/orphans">اختر طفلاً آخر ينتظر كافلاً</a>
          </div>

          {{-- method=post: إن تعطّل JS لا تذهب الحقول في الرابط --}}
          <form id="checkoutForm" class="co-form" data-validate-form novalidate method="post" action="#">

            {{-- ١. المبلغ --}}
            <section class="co-card" aria-labelledby="coAmount">
              <h2 class="co-card__title" id="coAmount"><span class="co-card__num num">1</span> مبلغ الكفالة الشهري</h2>

              <div class="co-presets" role="group" aria-label="مبالغ مقترحة">
                @foreach ($presets as $v)
                  <button type="button" class="co-preset" data-amount="{{ $v }}" aria-pressed="{{ $v == $suggested ? 'true' : 'false' }}">
                    <b class="num">{{ \App\Support\PublicChild::money($v) }}</b>
                    <span>{{ $v == $remaining ? ($shared ? 'المتبقي كاملاً' : 'الكفالة كاملة') : ($v == $price ? 'سعر البرنامج' : 'نصف المتبقي') }}</span>
                  </button>
                @endforeach
              </div>

              <div class="field">
                <label class="form-label" for="monthlyAmount">أو اكتب مبلغاً <span class="text-muted fw-normal">({{ $remaining > 5 ? 'من $5 حتى '.\App\Support\PublicChild::money($remaining) : 'المتبقي '.\App\Support\PublicChild::money($remaining).' كاملاً' }})</span></label>
                <div class="input-group input-group-lg co-amount">
                  <span class="input-group-text">$</span>
                  <input class="form-control num" type="text" id="monthlyAmount" inputmode="decimal" dir="ltr" lang="en"
                         value="{{ $suggested }}" data-validate="required" aria-describedby="hint-amount err-monthlyAmount">
                  <span class="input-group-text">في الشهر</span>
                </div>
                <p class="form-text" id="hint-amount"></p>
                <p class="field__error" id="err-monthlyAmount" aria-live="polite"></p>
              </div>
            </section>

            {{-- ٢. التكرار والزكاة --}}
            <section class="co-card" aria-labelledby="coCycle">
              <h2 class="co-card__title" id="coCycle"><span class="co-card__num num">2</span> كيف تدفع؟</h2>
              <div class="co-cycles" data-chips role="group" aria-label="تكرار الدفع">
                <button class="chip co-cycle is-active" type="button" data-value="monthly"><b>شهرياً</b><span class="num" data-cycle-sum="1"></span></button>
                <button class="chip co-cycle" type="button" data-value="quarterly"><b>كل 3 أشهر</b><span class="num" data-cycle-sum="3"></span></button>
                <button class="chip co-cycle" type="button" data-value="yearly"><b>سنوياً</b><span class="num" data-cycle-sum="12"></span></button>
              </div>

              <div class="form-check form-switch co-zakat">
                <input class="form-check-input" type="checkbox" role="switch" id="isZakat" name="is_zakat" value="1">
                <label class="form-check-label" for="isZakat">
                  <strong>احتسبها من الزكاة</strong>
                  <span>يُصرف مبلغك على الحالات المستحقة للزكاة وحدها، ويظهر ذلك في إيصالك. <a href="/faq#zakat">كيف؟</a></span>
                </label>
              </div>
            </section>

            {{-- ٣. الكافل --}}
            <section class="co-card" aria-labelledby="coWho">
              <h2 class="co-card__title" id="coWho"><span class="co-card__num num">3</span> بياناتك</h2>

              <p class="co-signed" data-checkout="signed" hidden>
                <i class="fa-regular fa-circle-user" aria-hidden="true"></i>
                <span>تكفل باسم <strong data-checkout="signed-name"></strong>، وتجد الكفالة في حسابك.</span>
              </p>

              <div data-checkout="guest">
                <p class="co-card__lede">ننشئ لك حساباً بهذا البريد تتابع منه الكفالة، ونرسل إليه رابط تعيين كلمة المرور.
                  لديك حساب؟ <a href="/login" data-checkout="login-link">سجّل الدخول</a>.</p>
                <div class="row g-3">
                  <div class="col-sm-6 field">
                    <label class="form-label" for="donorName">الاسم الكامل</label>
                    <input class="form-control form-control-lg" type="text" id="donorName" name="name"
                           autocomplete="name" data-validate="required|minlength:3" aria-describedby="err-donorName">
                    <p class="field__error" id="err-donorName" aria-live="polite"></p>
                  </div>
                  <div class="col-sm-6 field">
                    <label class="form-label" for="donorEmail">البريد الإلكتروني</label>
                    <input class="form-control form-control-lg" type="email" id="donorEmail" name="email" dir="ltr"
                           autocomplete="email" inputmode="email" data-validate="required|email" aria-describedby="err-donorEmail hint-email">
                    <p class="form-text" id="hint-email">إليه يصل الإيصال والتقارير.</p>
                    <p class="field__error" id="err-donorEmail" aria-live="polite"></p>
                  </div>
                  <div class="col-12 field">
                    <label class="form-label" for="donorPhone">رقم الجوال <span class="text-muted fw-normal">(اختياري)</span></label>
                    <input class="form-control form-control-lg" type="tel" id="donorPhone" name="phone" dir="ltr"
                           autocomplete="tel" inputmode="tel" data-validate="phone" aria-describedby="hint-phone err-donorPhone">
                    <p class="form-text" id="hint-phone">لإشعارك بوصول التقارير فقط.</p>
                    <p class="field__error" id="err-donorPhone" aria-live="polite"></p>
                  </div>
                </div>
              </div>
            </section>

            {{-- ٤. الدفع --}}
            <section class="co-card" aria-labelledby="coPay">
              <h2 class="co-card__title" id="coPay"><span class="co-card__num num">4</span> وسيلة الدفع</h2>

              <fieldset class="co-methods">
                <legend class="visually-hidden">اختر وسيلة الدفع</legend>
                <label class="co-method">
                  <input type="radio" name="method" value="card" checked>
                  <span class="co-method__icon" aria-hidden="true"><i class="fa-regular fa-credit-card"></i></span>
                  <span><strong>بطاقة</strong><small>تتفعّل الكفالة فوراً</small></span>
                </label>
                <label class="co-method">
                  <input type="radio" name="method" value="bank_transfer">
                  <span class="co-method__icon" aria-hidden="true"><i class="fa-solid fa-building-columns"></i></span>
                  <span><strong>تحويل بنكي</strong><small>تتفعّل حين يصل التحويل</small></span>
                </label>
              </fieldset>

              <div class="co-note" data-checkout="bank-note" hidden>
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>نحجز الكفالة باسمك، ونعرض لك بيانات الحساب ومرجع كفالتك لتكتبه في التحويل. تتفعّل حين يصل التحويل، ويبقى الحجز <b data-checkout="grace">5 أيام</b>.</span>
              </div>

              {{-- حقول البطاقة: بلا name عمداً، فلا تُرسَل (انظر رأس الملف) --}}
              <div class="row g-3" data-checkout="card-fields">
                <div class="col-12 field">
                  <label class="form-label" for="cardNumber">رقم البطاقة</label>
                  <div class="co-cardnum">
                    <input class="form-control form-control-lg num" type="text" id="cardNumber" autocomplete="cc-number" inputmode="numeric" dir="ltr"
                           placeholder="0000 0000 0000 0000" data-validate="required|card" aria-describedby="err-cardNumber">
                    <span class="co-cardnum__brands" aria-hidden="true"><i class="fa-brands fa-cc-visa"></i><i class="fa-brands fa-cc-mastercard"></i></span>
                  </div>
                  <p class="field__error" id="err-cardNumber" aria-live="polite"></p>
                </div>
                <div class="col-6 field">
                  <label class="form-label" for="cardExpiry">تاريخ الانتهاء</label>
                  <input class="form-control form-control-lg num" type="text" id="cardExpiry" autocomplete="cc-exp" inputmode="numeric" dir="ltr"
                         placeholder="MM/YY" data-validate="required|expiry" aria-describedby="err-cardExpiry">
                  <p class="field__error" id="err-cardExpiry" aria-live="polite"></p>
                </div>
                <div class="col-6 field">
                  <label class="form-label" for="cardCvc">رمز الأمان <span class="text-muted fw-normal">(CVC)</span></label>
                  <input class="form-control form-control-lg num" type="password" id="cardCvc" autocomplete="cc-csc" inputmode="numeric" dir="ltr"
                         placeholder="123" maxlength="4" data-validate="required|cvc" aria-describedby="err-cardCvc">
                  <p class="field__error" id="err-cardCvc" aria-live="polite"></p>
                </div>
                <div class="col-12 field">
                  <label class="form-label" for="cardHolder">الاسم على البطاقة</label>
                  <input class="form-control form-control-lg" type="text" id="cardHolder" autocomplete="cc-name" dir="ltr"
                         data-validate="required|minlength:3" aria-describedby="err-cardHolder">
                  <p class="field__error" id="err-cardHolder" aria-live="polite"></p>
                </div>
              </div>

              <p class="co-secure">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <span>اتصال مشفّر. بيانات بطاقتك تُعالَج لدى بوابة الدفع مباشرة، ولا تمرّ بخوادمنا ولا نحتفظ بها.</span>
              </p>
            </section>

            {{-- الإرسال: ما سيُخصم ومتى، قبل الزرّ لا بعده --}}
            <div class="co-submit">
              <p class="co-plan" data-checkout="plan" aria-live="polite"></p>
              <p class="co-error" data-checkout="error" role="alert" hidden></p>
              <button class="btn btn-cta btn-lg w-100" type="submit" id="checkoutSubmit"><i class="fa-solid fa-lock" aria-hidden="true"></i> تفعيل الكفالة</button>
              <p class="co-terms">بالمتابعة توافق على <a href="/transparency#child-policy">شروط الكفالة وسياسة الخصوصية</a>. تستطيع الإيقاف متى شئت بلا رسوم.</p>
            </div>
          </form>
        </div>

        {{-- ══ الملخص ══ --}}
        <aside class="col-lg-5 order-1 order-lg-2" aria-label="ملخص الكفالة">
          <div class="co-summary">
            <div class="co-summary__child">
              <img src="{{ $kid['photo'] }}" alt="" width="64" height="64" data-checkout="photo">
              <div>
                <strong data-checkout="name">{{ $kid['name'] }}</strong>
                <span>{{ $kid['program_name'] }} · <span class="num" dir="ltr" data-checkout="id">{{ $kid['id'] }}</span></span>
              </div>
            </div>

            <dl class="co-summary__lines">
              <div><dt>{{ $shared ? 'المتبقي من '.($her ? 'حاجتها' : 'حاجته') : ($her ? 'حاجتها' : 'حاجته').' الشهرية' }}</dt><dd class="num" data-checkout="remaining">{{ \App\Support\PublicChild::money($remaining) }}</dd></div>
              <div><dt>كفالتك الشهرية</dt><dd class="num" data-checkout="price">{{ \App\Support\PublicChild::money($suggested) }}</dd></div>
              <div><dt>رسوم المنصة</dt><dd class="co-summary__free">بلا رسوم</dd></div>
            </dl>

            {{-- للمجموع خطّافه: الدورية تغيّره (سنوية = 12 شهراً)، لا السطر الشهري --}}
            <div class="co-summary__total">
              <span>الإجمالي اليوم <small data-checkout="cycle"></small></span>
              <strong class="num" data-checkout="total">{{ \App\Support\PublicChild::money($suggested) }}</strong>
            </div>

            <ul class="co-summary__trust">
              <li><i class="fa-solid fa-hand" aria-hidden="true"></i> إيقاف بنقرة من حسابك، بلا رسوم</li>
              <li><i class="fa-solid fa-file-lines" aria-hidden="true"></i> تقرير مصوّر كل ثلاثة أشهر</li>
              <li><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> أين يذهب كل دولار في <a href="/transparency">تقارير الشفافية</a></li>
            </ul>
          </div>
        </aside>
      </div>
      @endif
    </div>
  </main>
@endsection
