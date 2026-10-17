@extends('site.layouts.legacy', ['chrome' => false])

@section('title')إنشاء حساب | كفالة@endsection
@section('description')أنشئ حسابك في دقيقة وابدأ كفالتك الأولى.@endsection
@section('canonical', url('/register'))

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

  <div class="auth auth--flip">


    <main class="auth__main" id="main">
      <div class="auth__form">

        <div class="auth__bar">
          <a class="brand" href="/">
            <img class="brand__mark" src="/assets/img/logo.svg" alt="" width="36" height="36">
            <span class="brand__name">كفالة</span>
          </a>
          <div class="auth__tools" data-theme-slot>
            <a class="auth__back" href="/">
              العودة للرئيسية
              <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            </a>
          </div>
        </div>

        <div class="auth__head">
          <h1>أنشئ حسابك</h1>
          <p>دقيقة واحدة تفصلك عن كفالتك الأولى.</p>
        </div>


        <form data-validate-form data-action="register" data-redirect="/dashboard" novalidate>

          <div class="grid grid--2 auth__pair">
            <div class="field">
              <label class="field__label" for="firstName">الاسم الأول</label>
              <div class="field__control">
                <i class="fa-regular fa-user field__icon" aria-hidden="true"></i>
                <input class="input" type="text" id="firstName" name="given-name"
                       autocomplete="given-name"
                       data-validate="required|minlength:2" aria-describedby="err-firstName">
              </div>
              <p class="field__error" id="err-firstName" aria-live="polite"></p>
            </div>

            <div class="field">
              <label class="field__label" for="lastName">اسم العائلة</label>
              <div class="field__control">
                <i class="fa-regular fa-user field__icon" aria-hidden="true"></i>
                <input class="input" type="text" id="lastName" name="family-name"
                       autocomplete="family-name"
                       data-validate="required|minlength:2" aria-describedby="err-lastName">
              </div>
              <p class="field__error" id="err-lastName" aria-live="polite"></p>
            </div>
          </div>

          <div class="field">
            <label class="field__label" for="email">البريد الإلكتروني</label>
            <div class="field__control">
              <i class="fa-regular fa-envelope field__icon" aria-hidden="true"></i>
              <input class="input" type="email" id="email" name="email"
                     autocomplete="email" inputmode="email"
                     data-validate="required|email" aria-describedby="err-email">
            </div>
            <p class="field__error" id="err-email" aria-live="polite"></p>
          </div>

          <div class="field">
            <label class="field__label" for="phone">رقم الجوال</label>
            <div class="field__control">
              <i class="fa-solid fa-mobile-screen field__icon" aria-hidden="true"></i>
              <input class="input" type="tel" id="phone" name="tel"
                     autocomplete="tel" inputmode="tel" dir="ltr" placeholder="+970 599 000 000"
                     data-validate="required|phone" aria-describedby="err-phone">
            </div>
            <p class="field__error" id="err-phone" aria-live="polite"></p>
          </div>

          <div class="field">
            <label class="field__label" for="password">كلمة المرور</label>
            <div class="field__control">
              <i class="fa-solid fa-lock field__icon" aria-hidden="true"></i>
              <input class="input" type="password" id="password" name="password"
                     autocomplete="new-password" data-strength-for="password"
                     data-validate="required|minlength:8|lettersNumbers" aria-describedby="err-password passwordStrength">
              <button class="field__action" type="button"
                      data-password-toggle="password" aria-label="إظهار كلمة المرور" aria-pressed="false">
                <i class="fa-regular fa-eye" aria-hidden="true"></i>
              </button>
            </div>
            <p class="field__error" id="err-password" aria-live="polite"></p>
          </div>

          <div class="strength" id="passwordStrength">
            <div class="strength__bars" aria-hidden="true">
              <span class="strength__bar"></span>
              <span class="strength__bar"></span>
              <span class="strength__bar"></span>
              <span class="strength__bar"></span>
              <span class="strength__bar"></span>
            </div>
            <p class="strength__label" role="status" aria-live="polite">
              ٨ أحرف على الأقل، فيها حرف ورقم
            </p>
          </div>

          <div class="field">
            <label class="checkbox">
              <input type="checkbox" name="terms" required
                     data-validate="required" data-required-message="يلزم الموافقة على الشروط لإنشاء الحساب"
                     aria-describedby="err-terms">
              <span>
                أوافق على <a href="/transparency#child-policy">شروط الاستخدام وسياسة الخصوصية</a>،
                وأفهم أن بياناتي لن تُشارك مع أي جهة تسويقية.
              </span>
            </label>
            <p class="field__error" id="err-terms" aria-live="polite"></p>
          </div>

          <button class="btn btn--primary btn--lg btn--block" type="submit">إنشاء الحساب</button>
        </form>

        <div class="auth__switch">
          <p>لديك حساب بالفعل؟</p>
          <a class="btn btn--outline btn--lg btn--block" href="/login">تسجيل الدخول</a>
        </div>

      </div>
    </main>

    <aside class="auth__aside" aria-label="لماذا تنشئ حساباً">
      <div class="auth__aside-inner">
        <p class="auth__quote">وَيُطْعِمُونَ الطَّعَامَ عَلَىٰ حُبِّهِ مِسْكِينًا وَيَتِيمًا وَأَسِيرًا</p>
        <h2>ابدأ من حيث تستطيع، وتوقّف متى شئت.</h2>

        <ol class="auth__points" role="list">
          <li><span class="num">01</span><div><strong>الحساب مجاني</strong><p>لا رسوم على التسجيل، ولا حد أدنى مُلزم.</p></div></li>
          <li><span class="num">02</span><div><strong>تقرّر بعد أن تتصفّح</strong><p>كل الحالات أمامك قبل أن تختار أي شيء.</p></div></li>
          <li><span class="num">03</span><div><strong>كل شيء في مكان واحد</strong><p>التقارير والمدفوعات والرسائل، من أي جهاز.</p></div></li>
        </ol>

        <dl class="auth__stats">
          <div><dt>كافل يتابع أثره</dt><dd class="num">+1,500</dd></div>
          <div><dt>دولة نعمل فيها</dt><dd class="num">12</dd></div>
          <div><dt>يوماً بين كل تقرير</dt><dd class="num">90</dd></div>
        </dl>
      </div>
    </aside>


  </div>
@endsection
