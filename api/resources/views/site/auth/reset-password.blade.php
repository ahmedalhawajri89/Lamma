@extends('site.layouts.legacy', ['chrome' => false])

@section('title')كلمة مرور جديدة | كفالة@endsection
@section('description')عيّن كلمة مرور جديدة لحسابك في كفالة.@endsection
@section('robots', 'noindex')

@push('styles')
  <link rel="stylesheet" href="/assets/css/auth.css">
  <style>
    @view-transition { navigation: auto; }
    .auth__aside { view-transition-name: auth-aside; }
    .auth__form { view-transition-name: auth-form; }
  </style>
@endpush

@section('content')
  <a class="skip-link" href="#main">تخطَّ إلى المحتوى الرئيسي</a>

  <div class="auth">

    <main class="auth__main" id="main">
      <div class="auth__form">

        <div class="auth__bar">
          <a class="brand" href="/">
            <img class="brand__mark" src="/assets/img/logo.svg" alt="" width="36" height="36">
            <span class="brand__name">كفالة</span>
          </a>
          <div class="auth__tools" data-theme-slot>
            <a class="auth__back" href="/login">
              العودة لتسجيل الدخول
              <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            </a>
          </div>
        </div>

        <div class="auth__head">
          <h1>كلمة مرور جديدة</h1>
          <p>اختر كلمة مرور لم تستعملها من قبل.</p>
        </div>

        <!--
          الرمز والبريد يأتيان من رابط البريد (`?token=&email=`) ويُملآن
          من initResetPassword() في features.js. حقلان مخفيان لا ظاهران:
          الرمز ليس مما يُنسخ أو يُحرَّر باليد، وإظهاره يدعو للعبث به.
        -->
        <form data-validate-form data-action="reset" data-redirect="/login"
              id="resetPasswordForm" novalidate hidden>
          <input type="hidden" name="token" id="resetToken">
          <input type="hidden" name="email" id="resetEmailHidden">

          <p class="muted" style="margin-block-end:var(--space-5)">
            تعيين كلمة مرور جديدة لـ <strong data-reset-email></strong>
          </p>

          <div class="field">
            <label class="field__label" for="newPassword">كلمة المرور الجديدة</label>
            <div class="field__control">
              <i class="fa-solid fa-lock field__icon" aria-hidden="true"></i>
              <input class="input" type="password" id="newPassword" name="password"
                     autocomplete="new-password"
                     data-validate="required|minlength:8|lettersNumbers" aria-describedby="hint-newPassword err-newPassword">
              <button class="field__action" type="button"
                      data-password-toggle="newPassword" aria-label="إظهار كلمة المرور" aria-pressed="false">
                <i class="fa-regular fa-eye" aria-hidden="true"></i>
              </button>
            </div>
            <p class="field__hint" id="hint-newPassword">٨ أحرف على الأقل، فيها حرف ورقم.</p>
            <p class="field__error" id="err-newPassword" aria-live="polite"></p>
          </div>

          <div class="field">
            <label class="field__label" for="newPasswordConfirm">تأكيد كلمة المرور</label>
            <div class="field__control">
              <i class="fa-solid fa-lock field__icon" aria-hidden="true"></i>
              <input class="input" type="password" id="newPasswordConfirm" name="password_confirmation"
                     autocomplete="new-password"
                     data-validate="required|match:newPassword" aria-describedby="err-newPasswordConfirm">
            </div>
            <p class="field__error" id="err-newPasswordConfirm" aria-live="polite"></p>
          </div>

          <button class="btn btn--primary btn--lg btn--block" type="submit">حفظ كلمة المرور</button>
        </form>

        <!-- رابط بلا رمز: لا نعرض نموذجاً لا يمكن أن ينجح -->
        <div id="resetInvalid" hidden>
          <p class="muted" style="margin-block-end:var(--space-6)">
            هذا الرابط غير مكتمل أو انتهت صلاحيته. اطلب رابطاً جديداً من صفحة الدخول.
          </p>
          <a class="btn btn--primary btn--lg btn--block" href="/login">اذهب لتسجيل الدخول</a>
        </div>

      </div>
    </main>

    <aside class="auth__aside" aria-label="عن أمان حسابك">
      <div class="auth__aside-inner">
        <p class="auth__kicker">أمان حسابك</p>
        <h2>حسابك يبقى لك وحدك.</h2>
        <p class="auth__aside-text">
          تغيير كلمة المرور يُنهي كل الجلسات المفتوحة على أي جهاز،
          فلا يبقى دخولٌ قديم صالحاً بعد اليوم.
        </p>
      </div>
    </aside>


  </div>
@endsection
