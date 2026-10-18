@extends('site.layouts.auth')

{{--
  تعيين كلمة مرور جديدة من رابط البريد. الخادم يفحص الرابط عند الفتح
  (ResetPasswordPageController) فتُرسم واحدةٌ من ثلاث حالات: النموذج، أو
  «انتهت صلاحية الرابط»، أو «الرابط غير مكتمل». فلا يكتب أحدٌ كلمته مرتين
  ثم يُقال له إن الرابط لا يصلح.

  النموذج يرسله forms.js (data-action="reset")، وشروط كلمة المرور تُعلَّم وهي
  تتحقق (signin.js)، والخادم يرفض فوقها ما ظهر في تسريبٍ معروف.
--}}
@section('title'){{ __('كلمة مرور جديدة') }}@endsection
@section('description'){{ __('عيّن كلمة مرور جديدة لحسابك في :name.', ['name' => $site['name']]) }}@endsection
@section('robots', 'noindex, nofollow')

@section('content')
  <div class="acc">

    {{-- ══ النموذج ══ --}}
    <main class="acc-main" id="main">
      <div class="acc-bar">
        <a class="kf-brand acc-brand" href="{{ lurl('/') }}">
          <img src="/assets/img/logo.svg" alt="" width="40" height="40">
          <span class="kf-brand__name"><span>{{ $site['name'] }}</span><span class="kf-brand__tag">{{ $site['tagline'] }}</span></span>
        </a>
        <div class="acc-bar__tools">
          @include('site.partials.lang-switch')
          <button class="btn btn-icon btn-icon--plain" type="button" id="themeToggle" aria-pressed="false" aria-label="{{ __('الوضع الليلي') }}">
            <i class="fa-solid fa-moon" aria-hidden="true"></i>
            <i class="fa-solid fa-sun" aria-hidden="true"></i>
          </button>
          <a class="acc-back" href="{{ lurl('/') }}">{{ __('الرئيسية') }} <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
        </div>
      </div>

      <div class="acc-panel">
        @if ($state === 'ready')
          <span class="acc-view-icon" aria-hidden="true"><i class="fa-solid fa-key"></i></span>
          <h1 class="acc-title">{{ __('كلمة مرور جديدة') }}</h1>
          <p class="acc-lede">{{ __('لحساب') }} <b dir="ltr">{{ $email }}</b>{{ __('. اختر كلمةً لم تستعملها في موقعٍ آخر.') }}</p>

          <form class="acc-form" data-validate-form data-action="reset" data-redirect="{{ lurl('/login') }}" novalidate>
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">
            {{-- لمدير كلمات المرور: يحفظ الكلمة الجديدة باسم الحساب الصحيح --}}
            <input type="email" value="{{ $email }}" autocomplete="username" hidden aria-hidden="true" tabindex="-1">

            <div class="field">
              <label class="form-label" for="newPassword">{{ __('كلمة المرور الجديدة') }}</label>
              <div class="acc-input">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <input class="form-control form-control-lg" type="password" id="newPassword" name="password" dir="ltr"
                       autocomplete="new-password" autofocus data-validate="required|minlength:8|lettersNumbers"
                       aria-describedby="pwRules caps-newPassword err-newPassword">
                <button class="acc-input__toggle" type="button" data-password-toggle="newPassword" aria-label="{{ __('إظهار كلمة المرور') }}" aria-pressed="false">
                  <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
              </div>
              <p class="acc-caps" id="caps-newPassword" data-caps-for="newPassword" hidden><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> {{ __('مفتاح الأحرف الكبيرة (Caps Lock) مفعّل') }}</p>
              <ul class="acc-rules" id="pwRules" data-pw-rules="newPassword">
                <li data-rule="length"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ __('8 أحرف') }}</li>
                <li data-rule="letter"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ __('حرف') }}</li>
                <li data-rule="digit"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ __('رقم') }}</li>
              </ul>
              <p class="field__error" id="err-newPassword" aria-live="polite"></p>
            </div>

            <div class="field">
              <label class="form-label" for="newPasswordConfirm">{{ __('أعد كتابتها') }}</label>
              <div class="acc-input">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <input class="form-control form-control-lg" type="password" id="newPasswordConfirm" name="password_confirmation" dir="ltr"
                       autocomplete="new-password" data-validate="required|match:newPassword"
                       aria-describedby="caps-newPasswordConfirm err-newPasswordConfirm">
                <button class="acc-input__toggle" type="button" data-password-toggle="newPasswordConfirm" aria-label="{{ __('إظهار كلمة المرور') }}" aria-pressed="false">
                  <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
              </div>
              <p class="acc-caps" id="caps-newPasswordConfirm" data-caps-for="newPasswordConfirm" hidden><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> {{ __('مفتاح الأحرف الكبيرة (Caps Lock) مفعّل') }}</p>
              <p class="field__error" id="err-newPasswordConfirm" aria-live="polite"></p>
            </div>

            <button class="btn btn-primary btn-lg w-100" type="submit">{{ __('احفظ كلمة المرور') }} <i class="fa-solid fa-arrow-left acc-btn__arrow" aria-hidden="true"></i></button>
          </form>

          <p class="acc-secure"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> {{ __('بعد الحفظ تخرج أجهزتك الأخرى من حسابك.') }}</p>
        @else
          {{-- الرابط لا يصلح: لا نعرض نموذجاً لا يمكن أن ينجح، بل الطريق إلى رابطٍ جديد --}}
          <span class="acc-view-icon acc-view-icon--warn" aria-hidden="true"><i class="fa-solid fa-link-slash"></i></span>
          @if ($state === 'expired')
            <h1 class="acc-title">{{ __('انتهت صلاحية الرابط') }}</h1>
            <p class="acc-lede">{{ __('الرابط صالحٌ ساعةً واحدة ولمرّةٍ واحدة. ربما استُعمل من قبل، أو طلبت رابطاً أحدث منه.') }}</p>
          @else
            <h1 class="acc-title">{{ __('الرابط غير مكتمل') }}</h1>
            <p class="acc-lede">{{ __('يبدو أن جزءاً منه سقط عند النسخ. افتحه من رسالة البريد مباشرةً، أو اطلب رابطاً جديداً.') }}</p>
          @endif

          <ul class="acc-tips">
            <li><i class="fa-regular fa-envelope" aria-hidden="true"></i> {{ __('افتح أحدث رسالةٍ منّا إن طلبت أكثر من رابط.') }}</li>
            <li><i class="fa-regular fa-folder-open" aria-hidden="true"></i> {{ __('لم تصلك؟ انظر في مجلد الرسائل غير المرغوب فيها.') }}</li>
          </ul>

          <a class="btn btn-primary btn-lg w-100" href="{{ lurl('/login?') }}{{ http_build_query(array_filter(['email' => $email])) }}#forgot">{{ __('اطلب رابطاً جديداً') }} <i class="fa-solid fa-arrow-left acc-btn__arrow" aria-hidden="true"></i></a>
          <div class="acc-switch">
            <span>{{ __('تذكّرت كلمة المرور؟') }}</span>
            <a href="{{ lurl('/login') }}">{{ __('سجّل الدخول') }} <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
          </div>
        @endif
      </div>
    </main>

    {{-- ══ الجانب: ماذا يحدث حين تغيّر كلمتك ══ --}}
    <aside class="acc-aside" aria-label="{{ __('عن أمان حسابك') }}">
      <div class="acc-aside__inner">
        <p class="acc-aside__kicker">{{ __('أمان حسابك') }}</p>
        <h2 class="acc-aside__title">{{ __('حسابك يبقى لك،') }}<br>{{ __('ولك وحدك.') }}</h2>

        <div class="acc-mock" aria-hidden="true">
          <div class="acc-mock__card acc-mock__card--main">
            <ol class="acc-journey">
              <li class="is-now"><span>1</span><div><b>{{ __('كلمةٌ جديدة') }}</b><small>{{ __('لم تستعملها في موقعٍ آخر') }}</small></div></li>
              <li><span>2</span><div><b>{{ __('نُخرج الأجهزة الأخرى') }}</b><small>{{ __('لا تبقى جلسةٌ قديمة صالحة') }}</small></div></li>
              <li><span>3</span><div><b>{{ __('ادخل من جديد') }}</b><small>{{ __('وكفالاتك كما تركتها') }}</small></div></li>
            </ol>
          </div>
          <div class="acc-mock__chip acc-mock__chip--a"><i class="fa-solid fa-shield-halved"></i><span><b>{{ __('مشفّرة') }}</b>{{ __('لا يراها أحدٌ منّا') }}</span></div>
          <div class="acc-mock__chip acc-mock__chip--b"><i class="fa-solid fa-user-lock"></i><span><b>{{ __('فحص التسريبات') }}</b>{{ __('نرفض الكلمات المكشوفة') }}</span></div>
        </div>

        <ul class="acc-aside__points">
          <li><i class="fa-solid fa-clock" aria-hidden="true"></i> {{ __('الرابط صالح ساعة واحدة') }}</li>
          <li><i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i> {{ __('نرسله إلى بريدك وحده') }}</li>
        </ul>
      </div>
    </aside>
  </div>
@endsection
