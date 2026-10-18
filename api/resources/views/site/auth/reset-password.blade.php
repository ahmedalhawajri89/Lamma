@extends('site.layouts.auth')

{{--
  تعيين كلمة مرور جديدة من رابط البريد. الخادم يفحص الرابط عند الفتح
  (ResetPasswordPageController) فتُرسم واحدةٌ من ثلاث حالات: النموذج، أو
  «انتهت صلاحية الرابط»، أو «الرابط غير مكتمل». فلا يكتب أحدٌ كلمته مرتين
  ثم يُقال له إن الرابط لا يصلح.

  النموذج يرسله forms.js (data-action="reset")، وشروط كلمة المرور تُعلَّم وهي
  تتحقق (signin.js)، والخادم يرفض فوقها ما ظهر في تسريبٍ معروف.
--}}
@section('title')كلمة مرور جديدة@endsection
@section('description')عيّن كلمة مرور جديدة لحسابك في كفالة.@endsection
@section('robots', 'noindex, nofollow')

@section('content')
  <div class="acc">

    {{-- ══ النموذج ══ --}}
    <main class="acc-main" id="main">
      <div class="acc-bar">
        <a class="kf-brand acc-brand" href="/">
          <img src="/assets/img/logo.svg" alt="" width="40" height="40">
          <span class="kf-brand__name"><span>{{ $site['name'] }}</span><span class="kf-brand__tag">{{ $site['tagline'] }}</span></span>
        </a>
        <div class="acc-bar__tools">
          <button class="btn btn-icon btn-icon--plain" type="button" id="themeToggle" aria-pressed="false" aria-label="الوضع الليلي">
            <i class="fa-solid fa-moon" aria-hidden="true"></i>
            <i class="fa-solid fa-sun" aria-hidden="true"></i>
          </button>
          <a class="acc-back" href="/">الرئيسية <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
        </div>
      </div>

      <div class="acc-panel">
        @if ($state === 'ready')
          <span class="acc-view-icon" aria-hidden="true"><i class="fa-solid fa-key"></i></span>
          <h1 class="acc-title">كلمة مرور جديدة</h1>
          <p class="acc-lede">لحساب <b dir="ltr">{{ $email }}</b>. اختر كلمةً لم تستعملها في موقعٍ آخر.</p>

          <form class="acc-form" data-validate-form data-action="reset" data-redirect="/login" novalidate>
            <input type="hidden" name="token" value="{{ $token }}">
            <input type="hidden" name="email" value="{{ $email }}">
            {{-- لمدير كلمات المرور: يحفظ الكلمة الجديدة باسم الحساب الصحيح --}}
            <input type="email" value="{{ $email }}" autocomplete="username" hidden aria-hidden="true" tabindex="-1">

            <div class="field">
              <label class="form-label" for="newPassword">كلمة المرور الجديدة</label>
              <div class="acc-input">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <input class="form-control form-control-lg" type="password" id="newPassword" name="password" dir="ltr"
                       autocomplete="new-password" autofocus data-validate="required|minlength:8|lettersNumbers"
                       aria-describedby="pwRules caps-newPassword err-newPassword">
                <button class="acc-input__toggle" type="button" data-password-toggle="newPassword" aria-label="إظهار كلمة المرور" aria-pressed="false">
                  <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
              </div>
              <p class="acc-caps" id="caps-newPassword" data-caps-for="newPassword" hidden><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> مفتاح الأحرف الكبيرة (Caps Lock) مفعّل</p>
              <ul class="acc-rules" id="pwRules" data-pw-rules="newPassword">
                <li data-rule="length"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> 8 أحرف</li>
                <li data-rule="letter"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> حرف</li>
                <li data-rule="digit"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> رقم</li>
              </ul>
              <p class="field__error" id="err-newPassword" aria-live="polite"></p>
            </div>

            <div class="field">
              <label class="form-label" for="newPasswordConfirm">أعد كتابتها</label>
              <div class="acc-input">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <input class="form-control form-control-lg" type="password" id="newPasswordConfirm" name="password_confirmation" dir="ltr"
                       autocomplete="new-password" data-validate="required|match:newPassword"
                       aria-describedby="caps-newPasswordConfirm err-newPasswordConfirm">
                <button class="acc-input__toggle" type="button" data-password-toggle="newPasswordConfirm" aria-label="إظهار كلمة المرور" aria-pressed="false">
                  <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
              </div>
              <p class="acc-caps" id="caps-newPasswordConfirm" data-caps-for="newPasswordConfirm" hidden><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> مفتاح الأحرف الكبيرة (Caps Lock) مفعّل</p>
              <p class="field__error" id="err-newPasswordConfirm" aria-live="polite"></p>
            </div>

            <button class="btn btn-primary btn-lg w-100" type="submit">احفظ كلمة المرور <i class="fa-solid fa-arrow-left acc-btn__arrow" aria-hidden="true"></i></button>
          </form>

          <p class="acc-secure"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> بعد الحفظ تخرج أجهزتك الأخرى من حسابك.</p>
        @else
          {{-- الرابط لا يصلح: لا نعرض نموذجاً لا يمكن أن ينجح، بل الطريق إلى رابطٍ جديد --}}
          <span class="acc-view-icon acc-view-icon--warn" aria-hidden="true"><i class="fa-solid fa-link-slash"></i></span>
          @if ($state === 'expired')
            <h1 class="acc-title">انتهت صلاحية الرابط</h1>
            <p class="acc-lede">الرابط صالحٌ ساعةً واحدة ولمرّةٍ واحدة. ربما استُعمل من قبل، أو طلبت رابطاً أحدث منه.</p>
          @else
            <h1 class="acc-title">الرابط غير مكتمل</h1>
            <p class="acc-lede">يبدو أن جزءاً منه سقط عند النسخ. افتحه من رسالة البريد مباشرةً، أو اطلب رابطاً جديداً.</p>
          @endif

          <ul class="acc-tips">
            <li><i class="fa-regular fa-envelope" aria-hidden="true"></i> افتح أحدث رسالةٍ منّا إن طلبت أكثر من رابط.</li>
            <li><i class="fa-regular fa-folder-open" aria-hidden="true"></i> لم تصلك؟ انظر في مجلد الرسائل غير المرغوب فيها.</li>
          </ul>

          <a class="btn btn-primary btn-lg w-100" href="/login?{{ http_build_query(array_filter(['email' => $email])) }}#forgot">اطلب رابطاً جديداً <i class="fa-solid fa-arrow-left acc-btn__arrow" aria-hidden="true"></i></a>
          <div class="acc-switch">
            <span>تذكّرت كلمة المرور؟</span>
            <a href="/login">سجّل الدخول <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
          </div>
        @endif
      </div>
    </main>

    {{-- ══ الجانب: ماذا يحدث حين تغيّر كلمتك ══ --}}
    <aside class="acc-aside" aria-label="عن أمان حسابك">
      <div class="acc-aside__inner">
        <p class="acc-aside__kicker">أمان حسابك</p>
        <h2 class="acc-aside__title">حسابك يبقى لك،<br>ولك وحدك.</h2>

        <div class="acc-mock" aria-hidden="true">
          <div class="acc-mock__card acc-mock__card--main">
            <ol class="acc-journey">
              <li class="is-now"><span>1</span><div><b>كلمةٌ جديدة</b><small>لم تستعملها في موقعٍ آخر</small></div></li>
              <li><span>2</span><div><b>نُخرج الأجهزة الأخرى</b><small>لا تبقى جلسةٌ قديمة صالحة</small></div></li>
              <li><span>3</span><div><b>ادخل من جديد</b><small>وكفالاتك كما تركتها</small></div></li>
            </ol>
          </div>
          <div class="acc-mock__chip acc-mock__chip--a"><i class="fa-solid fa-shield-halved"></i><span><b>مشفّرة</b>لا يراها أحدٌ منّا</span></div>
          <div class="acc-mock__chip acc-mock__chip--b"><i class="fa-solid fa-user-lock"></i><span><b>فحص التسريبات</b>نرفض الكلمات المكشوفة</span></div>
        </div>

        <ul class="acc-aside__points">
          <li><i class="fa-solid fa-clock" aria-hidden="true"></i> الرابط صالح ساعة واحدة</li>
          <li><i class="fa-solid fa-envelope-circle-check" aria-hidden="true"></i> نرسله إلى بريدك وحده</li>
        </ul>
      </div>
    </aside>
  </div>
@endsection
