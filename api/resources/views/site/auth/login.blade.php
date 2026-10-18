@extends('site.layouts.auth')

{{--
  تسجيل الدخول. النموذج يرسله forms.js (data-action="login") ثم ينتقل إلى
  ?redirect= إن كانت على الموقع نفسه، وإلا إلى الحساب. واستعادة كلمة المرور
  في البطاقة نفسها (signin.js) لا في نافذة: البريد ينتقل معها، ورجوعٌ بنقرة.

  ما يطلبه الدخول المتاح (WCAG 2.2): مدير كلمات المرور يملأ الحقلين، واللصق
  مسموح، وكلمة المرور تُظهَر بزرّ، والأخطاء تحت حقولها بـ aria-describedby.
  ولا حدّ أدنى لطول كلمة المرور هنا: ذلك شرط التسجيل لا الدخول.
--}}
@section('title')تسجيل الدخول@endsection
@section('description')ادخل إلى حسابك لمتابعة كفالاتك وتقاريرها.@endsection
@section('canonical', url('/login'))

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
        {{-- الدخول --}}
        <section data-acc-view="login" aria-labelledby="loginTitle">
          <h1 class="acc-title" id="loginTitle">أهلاً بعودتك</h1>
          <p class="acc-lede">ادخل لتتابع كفالاتك، وتقرأ آخر تقاريرها.</p>

          <form class="acc-form" data-validate-form data-action="login" data-redirect="/dashboard" novalidate>
            <div class="field">
              <label class="form-label" for="email">البريد الإلكتروني</label>
              <div class="acc-input">
                <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                <input class="form-control form-control-lg" type="email" id="email" name="email" dir="ltr"
                       autocomplete="username" inputmode="email" autocapitalize="off" spellcheck="false"
                       data-validate="required|email" aria-describedby="err-email">
              </div>
              <p class="field__error" id="err-email" aria-live="polite"></p>
            </div>

            <div class="field">
              <div class="acc-label-row">
                <label class="form-label" for="password">كلمة المرور</label>
                <button class="acc-link" type="button" data-acc-go="forgot">نسيت كلمة المرور؟</button>
              </div>
              <div class="acc-input">
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <input class="form-control form-control-lg" type="password" id="password" name="password" dir="ltr"
                       autocomplete="current-password" data-validate="required"
                       aria-describedby="caps-password err-password">
                <button class="acc-input__toggle" type="button" data-password-toggle="password" aria-label="إظهار كلمة المرور" aria-pressed="false">
                  <i class="fa-regular fa-eye" aria-hidden="true"></i>
                </button>
              </div>
              <p class="acc-caps" id="caps-password" data-caps-for="password" hidden><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> مفتاح الأحرف الكبيرة (Caps Lock) مفعّل</p>
              <p class="field__error" id="err-password" aria-live="polite"></p>
            </div>

            <div class="form-check form-switch acc-remember">
              <input class="form-check-input" type="checkbox" role="switch" id="remember" name="remember" checked>
              <label class="form-check-label" for="remember">ابقني مسجّلاً على هذا الجهاز</label>
            </div>

            <button class="btn btn-primary btn-lg w-100" type="submit">تسجيل الدخول <i class="fa-solid fa-arrow-left acc-btn__arrow" aria-hidden="true"></i></button>
          </form>

          <p class="acc-secure"><i class="fa-solid fa-shield-halved" aria-hidden="true"></i> اتصالٌ مشفّر، ولا نشارك بريدك مع أي جهة.</p>

          <div class="acc-switch">
            <span>جديد على {{ $site['name'] }}؟</span>
            <a href="/register">أنشئ حساباً <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
          </div>
        </section>

        {{-- استعادة كلمة المرور: في البطاقة نفسها --}}
        <section data-acc-view="forgot" aria-labelledby="forgotTitle" hidden>
          <button class="acc-backlink" type="button" data-acc-go="login"><i class="fa-solid fa-arrow-right" aria-hidden="true"></i> العودة إلى الدخول</button>
          <span class="acc-view-icon" aria-hidden="true"><i class="fa-solid fa-key"></i></span>
          <h1 class="acc-title" id="forgotTitle">نسيت كلمة المرور؟</h1>
          <p class="acc-lede">اكتب بريدك، ونرسل إليه رابطاً تعيّن منه كلمةً جديدة خلال دقائق.</p>

          <form class="acc-form" data-validate-form data-action="forgot" novalidate>
            <div class="field">
              <label class="form-label" for="resetEmail">البريد الإلكتروني</label>
              <div class="acc-input">
                <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                <input class="form-control form-control-lg" type="email" id="resetEmail" name="email" dir="ltr"
                       autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false"
                       data-validate="required|email" aria-describedby="err-resetEmail">
              </div>
              <p class="field__error" id="err-resetEmail" aria-live="polite"></p>
            </div>
            <button class="btn btn-primary btn-lg w-100" type="submit">أرسل الرابط <i class="fa-regular fa-paper-plane acc-btn__arrow" aria-hidden="true"></i></button>
          </form>
        </section>

        {{-- بعد الإرسال: الجملة نفسها أكان البريد مسجّلاً أم لا، فلا تكشف الصفحة من له حساب --}}
        <section data-acc-view="sent" aria-labelledby="sentTitle" hidden>
          <span class="acc-view-icon acc-view-icon--ok" aria-hidden="true"><i class="fa-solid fa-envelope-circle-check"></i></span>
          <h1 class="acc-title" id="sentTitle" tabindex="-1">تحقّق من بريدك</h1>
          <p class="acc-lede">إن كان <b dir="ltr" data-acc-email></b> مسجّلاً لدينا، فسيصله رابط تعيين كلمة المرور خلال دقائق.</p>
          <ul class="acc-tips">
            <li><i class="fa-regular fa-folder-open" aria-hidden="true"></i> لم يصل؟ انظر في مجلد الرسائل غير المرغوب فيها.</li>
            <li><i class="fa-regular fa-clock" aria-hidden="true"></i> الرابط صالحٌ ساعةً واحدة، فاستعمله قريباً.</li>
          </ul>
          <button class="btn btn-outline-primary btn-lg w-100" type="button" data-acc-go="login">العودة إلى الدخول</button>
        </section>
      </div>
    </main>

    {{-- ══ الجانب: ما ينتظرك في حسابك (توضيحي) ══ --}}
    <aside class="acc-aside" aria-label="ما ينتظرك في حسابك">
      <div class="acc-aside__inner">
        <p class="acc-aside__kicker">ما ينتظرك في حسابك</p>
        <h2 class="acc-aside__title">طفلك، وأخباره،<br>وأين ذهب كل دولار.</h2>

        <div class="acc-mock" aria-hidden="true">
          <div class="acc-mock__card acc-mock__card--main">
            <div class="acc-mock__who">
              <img src="/assets/img/kids/girl-08.svg" alt="" width="56" height="56">
              <div><b>أمنية، 8 سنوات</b><span>الكفالة التعليمية</span></div>
              <span class="acc-mock__tag">تقرير جديد</span>
            </div>
            <div class="acc-mock__row"><span>اللغة العربية</span><span class="acc-mock__bar"><span style="--v:92%"></span></span><b>92</b></div>
            <div class="acc-mock__row"><span>الرياضيات</span><span class="acc-mock__bar"><span style="--v:85%"></span></span><b>85</b></div>
            <div class="acc-mock__split"><span style="--w:40%;--c:var(--color-brand)"></span><span style="--w:34%;--c:var(--color-cta)"></span><span style="--w:26%;--c:var(--color-brand-border)"></span></div>
            <p class="acc-mock__note">كيف صُرفت كفالتك هذا الربع</p>
          </div>
          <div class="acc-mock__chip acc-mock__chip--a"><i class="fa-solid fa-house-user"></i><span><b>زيارة ميدانية</b>قبل 3 أيام</span></div>
          <div class="acc-mock__chip acc-mock__chip--b"><i class="fa-solid fa-envelope-open-text"></i><span><b>رسالة من أمنية</b>راجعها المشرف</span></div>
        </div>

        <ul class="acc-aside__points">
          <li><i class="fa-solid fa-file-lines" aria-hidden="true"></i> تقرير مصوّر كل ثلاثة أشهر</li>
          <li><i class="fa-solid fa-receipt" aria-hidden="true"></i> إيصالات كل دفعاتك</li>
          <li><i class="fa-solid fa-hand" aria-hidden="true"></i> إيقافٌ أو تعديلٌ بنقرة</li>
        </ul>
        <p class="acc-aside__demo">نموذجٌ توضيحي بأسماء غير حقيقية</p>
      </div>
    </aside>
  </div>
@endsection
