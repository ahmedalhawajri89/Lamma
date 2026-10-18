@extends('site.layouts.auth')

{{--
  إنشاء حساب. forms.js يرسله (data-action="register") ثم ينتقل إلى الحساب.
  الحقول مسمّاة بقيم autocomplete (given-name, tel) ليملأها المتصفح، والربط
  بأسماء الـ API في forms.js.

  ثلاثة حقول فقط، ما يطلبه الخادم وحده: الاسم والبريد وكلمة المرور. الجوال
  (اختياري في الخادم) يُطلب حين يلزم لا عند التسجيل، والموافقة جملةٌ عند
  الزرّ. وشروط كلمة المرور تُرى وهي تتحقق (signin.js)، والخادم يرفض فوقها ما
  ظهر في تسريبٍ معروف (uncompromised) برسالةٍ عربية تحت الحقل.
--}}
@section('title')إنشاء حساب@endsection
@section('description')أنشئ حسابك مجاناً لتكفل يتيماً وتتابع تقاريره.@endsection
@section('canonical', url('/register'))

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

      <div class="acc-panel acc-panel--wide">
        <h1 class="acc-title">أنشئ حسابك</h1>
        <p class="acc-lede">دقيقةٌ واحدة تفصلك عن كفالتك الأولى. الحساب مجاني.</p>

        <form class="acc-form" data-validate-form data-action="register" data-redirect="/dashboard" novalidate>
          <div class="field">
            <label class="form-label" for="fullName">الاسم الكامل</label>
            <div class="acc-input">
              <i class="fa-regular fa-user" aria-hidden="true"></i>
              <input class="form-control form-control-lg" type="text" id="fullName" name="name"
                     autocomplete="name" data-validate="required|minlength:3" aria-describedby="err-fullName">
            </div>
            <p class="field__error" id="err-fullName" aria-live="polite"></p>
          </div>

          <div class="field">
            <label class="form-label" for="email">البريد الإلكتروني</label>
            <div class="acc-input">
              <i class="fa-regular fa-envelope" aria-hidden="true"></i>
              <input class="form-control form-control-lg" type="email" id="email" name="email" dir="ltr"
                     autocomplete="email" inputmode="email" autocapitalize="off" spellcheck="false"
                     data-validate="required|email" aria-describedby="err-email">
            </div>
            <p class="field__error" id="err-email" aria-live="polite"></p>
          </div>

          <div class="field">
            <label class="form-label" for="password">كلمة المرور</label>
            <div class="acc-input">
              <i class="fa-solid fa-lock" aria-hidden="true"></i>
              <input class="form-control form-control-lg" type="password" id="password" name="password" dir="ltr"
                     autocomplete="new-password" data-validate="required|minlength:8|lettersNumbers"
                     aria-describedby="pwRules caps-password err-password">
              <button class="acc-input__toggle" type="button" data-password-toggle="password" aria-label="إظهار كلمة المرور" aria-pressed="false">
                <i class="fa-regular fa-eye" aria-hidden="true"></i>
              </button>
            </div>
            <p class="acc-caps" id="caps-password" data-caps-for="password" hidden><i class="fa-solid fa-arrow-up" aria-hidden="true"></i> مفتاح الأحرف الكبيرة (Caps Lock) مفعّل</p>
            {{-- الشروط تُعلَّم وهي تتحقق (signin.js)، وهي شروط الخادم نفسها --}}
            <ul class="acc-rules" id="pwRules" data-pw-rules="password">
              <li data-rule="length"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> 8 أحرف</li>
              <li data-rule="letter"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> حرف</li>
              <li data-rule="digit"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> رقم</li>
            </ul>
            <p class="field__error" id="err-password" aria-live="polite"></p>
          </div>

          {{-- الموافقة بجملةٍ صريحة عند الزرّ لا بخانة: إنشاء الحساب هو الموافقة، وتُرسل terms معه --}}
          <input type="hidden" name="terms" value="on">
          <button class="btn btn-primary btn-lg w-100" type="submit">إنشاء الحساب <i class="fa-solid fa-arrow-left acc-btn__arrow" aria-hidden="true"></i></button>
          <p class="acc-consent">بإنشاء الحساب توافق على <a href="/transparency#child-policy">شروط الاستخدام وسياسة الخصوصية</a>. لا نشارك بياناتك مع أي جهة.</p>
        </form>

        <div class="acc-switch">
          <span>لديك حساب بالفعل؟</span>
          <a href="/login">سجّل الدخول <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
        </div>
      </div>
    </main>

    {{-- ══ الجانب: من الحساب إلى الأثر في ثلاث خطوات ══ --}}
    <aside class="acc-aside" aria-label="لماذا تنشئ حساباً">
      <div class="acc-aside__inner">
        <p class="acc-aside__kicker acc-aside__verse">﴿وَيُطْعِمُونَ الطَّعَامَ عَلَىٰ حُبِّهِ مِسْكِينًا وَيَتِيمًا وَأَسِيرًا﴾</p>
        <h2 class="acc-aside__title">ابدأ من حيث تستطيع،<br>وتوقّف متى شئت.</h2>

        <div class="acc-mock" aria-hidden="true">
          <div class="acc-mock__card acc-mock__card--main">
            <ol class="acc-journey">
              <li class="is-now"><span>1</span><div><b>أنشئ حسابك</b><small>الآن، مجاناً</small></div></li>
              <li><span>2</span><div><b>اختر طفلاً</b><small>من ملفاتٍ موثّقة ميدانياً</small></div></li>
              <li><span>3</span><div><b>تابع أثره</b><small>تقرير مصوّر كل ثلاثة أشهر</small></div></li>
            </ol>
          </div>
          <div class="acc-mock__chip acc-mock__chip--a"><i class="fa-solid fa-gift"></i><span><b>الحساب مجاني</b>بلا رسوم تسجيل</span></div>
          <div class="acc-mock__chip acc-mock__chip--b"><i class="fa-solid fa-hand"></i><span><b>إيقاف بنقرة</b>بلا أسئلة ولا رسوم</span></div>
        </div>

        <ul class="acc-aside__points">
          <li><i class="fa-solid fa-user-shield" aria-hidden="true"></i> بياناتك لا تُشارك</li>
          <li><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> يمكن احتسابها من الزكاة</li>
          <li><i class="fa-solid fa-mobile-screen" aria-hidden="true"></i> من أي جهاز</li>
        </ul>
      </div>
    </aside>
  </div>
@endsection
