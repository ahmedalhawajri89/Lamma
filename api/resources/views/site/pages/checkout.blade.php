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

@section('title'){{ $kid ? __('كفالة :name', ['name' => $kid['name']]) : __('إتمام الكفالة') }}@endsection
@section('description'){{ __('خطوة أخيرة لتفعيل كفالتك. دفع مشفّر، ولا نحتفظ ببيانات بطاقتك.') }}@endsection
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
            <h1>{{ __('اكتملت كفالة :name', ['name' => $kid['name']]) }}</h1>
            <p>{{ __($her ? 'كفالتها مكتملة الآن، شكراً لك. وهناك أطفال آخرون ينتظرون من يقف معهم.' : 'كفالته مكتملة الآن، شكراً لك. وهناك أطفال آخرون ينتظرون من يقف معهم.') }}</p>
          @else
            <h1>{{ __('لم نجد هذه الحالة') }}</h1>
            <p>{{ __('ربما تغيّر رابطها أو لم يعد ملفها منشوراً.') }}</p>
          @endif
          <a class="btn btn-cta btn-lg" href="{{ lurl('/orphans') }}">{{ __('أطفال ينتظرون من يقف معهم') }}</a>
        </section>
      @else

      <header class="co-head">
        <a class="co-back" href="{{ lurl('/orphan?id=') }}{{ urlencode($kid['id']) }}"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> {{ __('ملف :name', ['name' => $kid['name']]) }}</a>
        <h1 class="co-head__title">{{ __($shared ? 'أكمل كفالة :name' : 'كفالة :name', ['name' => $kid['name']]) }}</h1>
        <ol class="co-steps" aria-label="{{ __('مراحل الكفالة') }}">
          <li class="is-done"><span aria-hidden="true"><i class="fa-solid fa-check"></i></span> {{ __('اختيار الطفل') }}</li>
          <li aria-current="step"><span class="num" aria-hidden="true">2</span> {{ __('المبلغ والدفع') }}</li>
          <li><span class="num" aria-hidden="true">3</span> {{ __('التأكيد') }}</li>
        </ol>
      </header>

      <div class="row g-4 g-xl-5" id="checkoutSummary">

        {{-- ══ النموذج ══ --}}
        <div class="col-lg-7 order-2 order-lg-1">
          {{-- البوابة تجريبية: لا يظن أحد أنه دفع مالاً حقيقياً --}}
          <div class="co-demo" data-checkout="demo" role="note" hidden>
            <i class="fa-solid fa-flask" aria-hidden="true"></i>
            <span><strong>{{ __('وضع تجريبي:') }}</strong> {{ __('لا يُخصم أي مال. للقبول') }} <span class="num" dir="ltr">4242 4242 4242 4242</span>{{ __('، وللرفض') }} <span class="num" dir="ltr">4000 0000 0000 0002</span>{{ __('، بأي تاريخ قادم وأي رمز.') }}</span>
          </div>

          <div data-checkout="blocked" class="co-blocked co-blocked--inline" role="status" hidden>
            <p data-checkout="blocked-text"></p>
            <a class="btn btn-primary" href="{{ lurl('/orphans') }}">{{ __('اختر طفلاً آخر ينتظر كافلاً') }}</a>
          </div>

          {{-- method=post: إن تعطّل JS لا تذهب الحقول في الرابط --}}
          <form id="checkoutForm" class="co-form" data-validate-form novalidate method="post" action="#">

            {{-- ١. المبلغ --}}
            <section class="co-card" aria-labelledby="coAmount">
              <div class="co-card__head">
                <h2 class="co-card__title" id="coAmount"><span class="co-card__num num">1</span> {{ __('مبلغ الكفالة الشهري') }}</h2>
                {{-- عملة الكافل: يُخصم منه بها مبلغٌ ثابت كل شهر. تملؤها checkout.js من الإعدادات --}}
                <label class="co-currency" for="coCurrency" hidden>
                  <span class="visually-hidden">{{ __('العملة') }}</span>
                  <select class="form-select form-select-sm" id="coCurrency" dir="ltr"></select>
                </label>
              </div>

              <div class="co-presets" role="group" aria-label="{{ __('مبالغ مقترحة') }}">
                @foreach ($presets as $v)
                  <button type="button" class="co-preset" data-amount="{{ $v }}" data-usd="{{ $v }}" aria-pressed="{{ $v == $suggested ? 'true' : 'false' }}">
                    <b class="num">{{ \App\Support\PublicChild::money($v) }}</b>
                    <span>{{ __($v == $remaining ? ($shared ? 'المتبقي كاملاً' : 'الكفالة كاملة') : ($v == $price ? 'سعر البرنامج' : 'نصف المتبقي')) }}</span>
                  </button>
                @endforeach
              </div>

              <div class="field">
                <label class="form-label" for="monthlyAmount">{{ __('أو اكتب مبلغاً') }} <span class="text-muted fw-normal" data-checkout="range">({{ $remaining > 5 ? __('من $5 حتى :max', ['max' => \App\Support\PublicChild::money($remaining)]) : __('المتبقي :amount كاملاً', ['amount' => \App\Support\PublicChild::money($remaining)]) }})</span></label>
                <div class="input-group input-group-lg co-amount">
                  <span class="input-group-text num" data-checkout="symbol">$</span>
                  <input class="form-control num" type="text" id="monthlyAmount" inputmode="decimal" dir="ltr" lang="en"
                         value="{{ $suggested }}" data-validate="required" aria-describedby="hint-amount err-monthlyAmount">
                  <span class="input-group-text">{{ __('في الشهر') }}</span>
                </div>
                <p class="form-text" id="hint-amount"></p>
                <p class="form-text co-fx" data-checkout="fx" hidden></p>
                <p class="field__error" id="err-monthlyAmount" aria-live="polite"></p>
              </div>
            </section>

            {{-- ٢. التكرار والزكاة --}}
            <section class="co-card" aria-labelledby="coCycle">
              <h2 class="co-card__title" id="coCycle"><span class="co-card__num num">2</span> {{ __('كيف تدفع؟') }}</h2>
              <div class="co-cycles" data-chips role="group" aria-label="{{ __('تكرار الدفع') }}">
                <button class="chip co-cycle is-active" type="button" data-value="monthly"><b>{{ __('شهرياً') }}</b><span class="num" data-cycle-sum="1"></span></button>
                <button class="chip co-cycle" type="button" data-value="quarterly"><b>{{ __('كل 3 أشهر') }}</b><span class="num" data-cycle-sum="3"></span></button>
                <button class="chip co-cycle" type="button" data-value="yearly"><b>{{ __('سنوياً') }}</b><span class="num" data-cycle-sum="12"></span></button>
              </div>

              <div class="form-check form-switch co-zakat">
                <input class="form-check-input" type="checkbox" role="switch" id="isZakat" name="is_zakat" value="1">
                <label class="form-check-label" for="isZakat">
                  <strong>{{ __('احتسبها من الزكاة') }}</strong>
                  <span>{{ __('يُصرف مبلغك على الحالات المستحقة للزكاة وحدها، ويظهر ذلك في إيصالك.') }} <a href="{{ lurl('/faq#zakat') }}">{{ __('كيف؟') }}</a></span>
                </label>
              </div>
            </section>

            {{-- ٣. الكافل --}}
            <section class="co-card" aria-labelledby="coWho">
              <h2 class="co-card__title" id="coWho"><span class="co-card__num num">3</span> {{ __('بياناتك') }}</h2>

              <p class="co-signed" data-checkout="signed" hidden>
                <i class="fa-regular fa-circle-user" aria-hidden="true"></i>
                <span>{{ __('تكفل باسم') }} <strong data-checkout="signed-name"></strong>{{ __('، وتجد الكفالة في حسابك.') }}</span>
              </p>

              <div data-checkout="guest">
                <p class="co-card__lede">{{ __('ننشئ لك حساباً بهذا البريد تتابع منه الكفالة، ونرسل إليه رابط تعيين كلمة المرور. لديك حساب؟') }} <a href="{{ lurl('/login') }}" data-checkout="login-link">{{ __('سجّل الدخول') }}</a>.</p>
                <div class="row g-3">
                  <div class="col-sm-6 field">
                    <label class="form-label" for="donorName">{{ __('الاسم الكامل') }}</label>
                    <input class="form-control form-control-lg" type="text" id="donorName" name="name"
                           autocomplete="name" data-validate="required|minlength:3" aria-describedby="err-donorName">
                    <p class="field__error" id="err-donorName" aria-live="polite"></p>
                  </div>
                  <div class="col-sm-6 field">
                    <label class="form-label" for="donorEmail">{{ __('البريد الإلكتروني') }}</label>
                    <input class="form-control form-control-lg" type="email" id="donorEmail" name="email" dir="ltr"
                           autocomplete="email" inputmode="email" data-validate="required|email" aria-describedby="err-donorEmail hint-email">
                    <p class="form-text" id="hint-email">{{ __('إليه يصل الإيصال والتقارير.') }}</p>
                    <p class="field__error" id="err-donorEmail" aria-live="polite"></p>
                  </div>
                  <div class="col-12 field">
                    <label class="form-label" for="donorPhone">{{ __('رقم الجوال') }} <span class="text-muted fw-normal">{{ __('(اختياري)') }}</span></label>
                    <input class="form-control form-control-lg" type="tel" id="donorPhone" name="phone" dir="ltr"
                           autocomplete="tel" inputmode="tel" data-validate="phone" aria-describedby="hint-phone err-donorPhone">
                    <p class="form-text" id="hint-phone">{{ __('لإشعارك بوصول التقارير فقط.') }}</p>
                    <p class="field__error" id="err-donorPhone" aria-live="polite"></p>
                  </div>
                </div>
              </div>
            </section>

            {{-- ٤. الدفع --}}
            <section class="co-card" aria-labelledby="coPay">
              <h2 class="co-card__title" id="coPay"><span class="co-card__num num">4</span> {{ __('وسيلة الدفع') }}</h2>

              <fieldset class="co-methods">
                <legend class="visually-hidden">{{ __('اختر وسيلة الدفع') }}</legend>
                <label class="co-method">
                  <input type="radio" name="method" value="card" checked>
                  <span class="co-method__icon" aria-hidden="true"><i class="fa-regular fa-credit-card"></i></span>
                  <span><strong>{{ __('بطاقة') }}</strong><small>{{ __('تتفعّل الكفالة فوراً') }}</small></span>
                </label>
                <label class="co-method">
                  <input type="radio" name="method" value="bank_transfer">
                  <span class="co-method__icon" aria-hidden="true"><i class="fa-solid fa-building-columns"></i></span>
                  <span><strong>{{ __('تحويل بنكي') }}</strong><small>{{ __('تتفعّل حين يصل التحويل') }}</small></span>
                </label>
              </fieldset>

              <div class="co-note" data-checkout="bank-note" hidden>
                <i class="fa-solid fa-circle-info" aria-hidden="true"></i>
                <span>{{ __('نحجز الكفالة باسمك، ونعرض لك بيانات الحساب ومرجع كفالتك لتكتبه في التحويل. تتفعّل حين يصل التحويل، ويبقى الحجز') }} <b data-checkout="grace">{{ __('5 أيام') }}</b>.</span>
              </div>

              {{-- حقول البطاقة: بلا name عمداً، فلا تُرسَل (انظر رأس الملف) --}}
              <div class="row g-3" data-checkout="card-fields">
                <div class="col-12 field">
                  <label class="form-label" for="cardNumber">{{ __('رقم البطاقة') }}</label>
                  <div class="co-cardnum">
                    <input class="form-control form-control-lg num" type="text" id="cardNumber" autocomplete="cc-number" inputmode="numeric" dir="ltr"
                           placeholder="0000 0000 0000 0000" data-validate="required|card" aria-describedby="err-cardNumber">
                    <span class="co-cardnum__brands" aria-hidden="true"><i class="fa-brands fa-cc-visa"></i><i class="fa-brands fa-cc-mastercard"></i></span>
                  </div>
                  <p class="field__error" id="err-cardNumber" aria-live="polite"></p>
                </div>
                <div class="col-6 field">
                  <label class="form-label" for="cardExpiry">{{ __('تاريخ الانتهاء') }}</label>
                  <input class="form-control form-control-lg num" type="text" id="cardExpiry" autocomplete="cc-exp" inputmode="numeric" dir="ltr"
                         placeholder="MM/YY" data-validate="required|expiry" aria-describedby="err-cardExpiry">
                  <p class="field__error" id="err-cardExpiry" aria-live="polite"></p>
                </div>
                <div class="col-6 field">
                  <label class="form-label" for="cardCvc">{{ __('رمز الأمان') }} <span class="text-muted fw-normal">(CVC)</span></label>
                  <input class="form-control form-control-lg num" type="password" id="cardCvc" autocomplete="cc-csc" inputmode="numeric" dir="ltr"
                         placeholder="123" maxlength="4" data-validate="required|cvc" aria-describedby="err-cardCvc">
                  <p class="field__error" id="err-cardCvc" aria-live="polite"></p>
                </div>
                <div class="col-12 field">
                  <label class="form-label" for="cardHolder">{{ __('الاسم على البطاقة') }}</label>
                  <input class="form-control form-control-lg" type="text" id="cardHolder" autocomplete="cc-name" dir="ltr"
                         data-validate="required|minlength:3" aria-describedby="err-cardHolder">
                  <p class="field__error" id="err-cardHolder" aria-live="polite"></p>
                </div>
              </div>

              <p class="co-secure">
                <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                <span>{{ __('اتصال مشفّر. بيانات بطاقتك تُعالَج لدى بوابة الدفع مباشرة، ولا تمرّ بخوادمنا ولا نحتفظ بها.') }}</span>
              </p>
            </section>

            {{-- الإفصاح: أين يذهب المبلغ، قبل القرار لا بعده (يُحفظ مع الكفالة) --}}
            <section class="co-card co-disclose" aria-labelledby="coDisclose">
              <h2 class="co-card__title" id="coDisclose"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> {{ __('كيف يُصرف مبلغك') }}</h2>
              <ul class="co-disclose__list">
                <li><i class="fa-solid fa-child-reaching" aria-hidden="true"></i> <span data-checkout="share">{{ $her ? __('مبلغك لـ:name وحدها.', ['name' => $kid['name']]) : __('مبلغك لـ:name وحده.', ['name' => $kid['name']]) }}</span></li>
                <li><i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                  <span>{{ __('يُقتطع :fee٪ من كل دفعة لتشغيل المنصة (الدفع والمتابعة والتقارير)، والباقي يُحوَّل كل شهر إلى :association التي تعرف أسرته وتزوره.', [
                      'fee' => rtrim(rtrim(number_format(\App\Models\Payment::currentFeePercent(), 2, '.', ''), '0'), '.'),
                      'association' => $kid['association']['name'] ?? __('جمعيته الشريكة'),
                  ]) }}</span></li>
                <li><i class="fa-solid fa-piggy-bank" aria-hidden="true"></i> <span>{{ __('ما لا يُصرف في شهره يبقى للطفل نفسه عند جمعيته، وتراه في حسابك تحت «أين ذهب مالك» بنداً بنداً.') }}</span></li>
              </ul>
              <div class="field">
                <div class="form-check co-consent">
                  <input class="form-check-input" type="checkbox" id="disclosure" name="disclosure" value="1" data-validate="required"
                         data-required-message="{{ __('اقرأ كيف يُصرف مبلغك ووافق عليه للمتابعة.') }}" aria-describedby="err-disclosure">
                  <label class="form-check-label" for="disclosure">{{ __('قرأتُ كيف يُصرف مبلغي، وأوافق.') }}</label>
                </div>
                <p class="field__error" id="err-disclosure" aria-live="polite"></p>
              </div>
            </section>

            {{-- الإرسال: ما سيُخصم ومتى، قبل الزرّ لا بعده --}}
            <div class="co-submit">
              <p class="co-plan" data-checkout="plan" aria-live="polite"></p>
              <p class="co-error" data-checkout="error" role="alert" hidden></p>
              <button class="btn btn-cta btn-lg w-100" type="submit" id="checkoutSubmit"><i class="fa-solid fa-lock" aria-hidden="true"></i> {{ __('تفعيل الكفالة') }}</button>
              <p class="co-terms">{{ __('بالمتابعة توافق على') }} <a href="{{ lurl('/transparency#child-policy') }}">{{ __('شروط الكفالة وسياسة الخصوصية') }}</a>{{ __('. تستطيع الإيقاف متى شئت بلا رسوم.') }}</p>
            </div>
          </form>
        </div>

        {{-- ══ الملخص ══ --}}
        <aside class="col-lg-5 order-1 order-lg-2" aria-label="{{ __('ملخص الكفالة') }}">
          <div class="co-summary">
            <div class="co-summary__child">
              <img src="{{ $kid['photo'] }}" alt="" width="64" height="64" data-checkout="photo">
              <div>
                <strong data-checkout="name">{{ $kid['name'] }}</strong>
                <span>{{ $kid['program_name'] }} · <span class="num" dir="ltr" data-checkout="id">{{ $kid['id'] }}</span></span>
              </div>
            </div>

            <dl class="co-summary__lines">
              <div><dt>{{ __($shared ? ($her ? 'المتبقي من حاجتها' : 'المتبقي من حاجته') : ($her ? 'حاجتها الشهرية' : 'حاجته الشهرية')) }}</dt><dd class="num" data-checkout="remaining">{{ \App\Support\PublicChild::money($remaining) }}</dd></div>
              <div><dt>{{ __('كفالتك الشهرية') }}</dt><dd class="num" data-checkout="price">{{ \App\Support\PublicChild::money($suggested) }}</dd></div>
              {{-- لا يُضاف شيءٌ فوق المبلغ؛ والتشغيل منه لا عليه (الإفصاح في النموذج) --}}
              <div><dt>{{ __('رسوم فوق المبلغ') }}</dt><dd class="co-summary__free">{{ __('بلا رسوم') }}</dd></div>
              <div><dt>{{ __('منه للتشغيل') }}</dt><dd class="num">{{ rtrim(rtrim(number_format(\App\Models\Payment::currentFeePercent(), 2, '.', ''), '0'), '.') }}{{ \App\Support\Locale::isDefault() ? '٪' : '%' }}</dd></div>
            </dl>

            {{-- للمجموع خطّافه: الدورية تغيّره (سنوية = 12 شهراً)، لا السطر الشهري --}}
            <div class="co-summary__total">
              <span>{{ __('الإجمالي اليوم') }} <small data-checkout="cycle"></small></span>
              <strong class="num" data-checkout="total">{{ \App\Support\PublicChild::money($suggested) }}</strong>
            </div>

            <ul class="co-summary__trust">
              <li><i class="fa-solid fa-hand" aria-hidden="true"></i> {{ __('إيقاف بنقرة من حسابك، بلا رسوم') }}</li>
              <li><i class="fa-solid fa-file-lines" aria-hidden="true"></i> {{ __('تقرير مصوّر كل ثلاثة أشهر') }}</li>
              <li><i class="fa-solid fa-chart-pie" aria-hidden="true"></i> {{ __('أين يذهب كل دولار في') }} <a href="{{ lurl('/transparency') }}">{{ __('تقارير الشفافية') }}</a></li>
            </ul>
          </div>
        </aside>
      </div>
      @endif
    </div>
  </main>
@endsection
