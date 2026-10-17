@extends('site.layouts.legacy', ['chrome' => false])

@section('title')تمّت الكفالة | كفالة@endsection
@section('description')تم تفعيل كفالتك بنجاح.@endsection
@section('robots', 'noindex, nofollow')

@section('content')
  <a class="skip-link" href="#main">تخطَّ إلى المحتوى الرئيسي</a>

  <main id="main" class="thanks">
    <!-- تُملأ مما ردّ به الخادم عند الكفالة (checkout.js)، لا من الرابط -->
    <div class="thanks__card" id="thanksCard">

      <div class="thanks__seal">
        <i class="fa-solid fa-heart" aria-hidden="true"></i>
      </div>

      <p class="secure-note" data-thanks-when="demo" hidden>
        <i class="fa-solid fa-flask" aria-hidden="true"></i>
        <span>وضع تجريبي: لم يُخصم أي مال.</span>
      </p>

      <!-- تمّت الكفالة ودُفعت -->
      <div data-thanks-when="paid" hidden>
        <h1 style="font-size:var(--text-2xl)">
          تمّت الكفالة. شكراً لأنك مع <span data-thanks="name">…</span>.
        </h1>
        <p class="section-lede">
          جعله الله في ميزان حسناتك. يصلك أول تقرير مصوّر خلال تسعين يوماً،
          وتتابع كل شيء من لوحتك في أي وقت.
        </p>
      </div>

      <!-- حجزٌ بانتظار التحويل -->
      <div data-thanks-when="pending" hidden>
        <h1 style="font-size:var(--text-2xl)">
          حجزنا كفالة <span data-thanks="name">…</span> باسمك.
        </h1>
        <p class="section-lede">
          تتفعّل الكفالة حين يصل تحويلك. اكتب مرجع الكفالة في خانة البيان كي نطابقه.
          يبقى الحجز حتى <strong class="num" data-thanks="hold">…</strong>.
        </p>

        <dl class="thanks__bank">
          <div><dt>البنك</dt><dd data-thanks="bank-name">…</dd></div>
          <div><dt>اسم الحساب</dt><dd data-thanks="bank-account">…</dd></div>
          <div><dt>IBAN</dt><dd class="num" dir="ltr" data-thanks="bank-iban">…</dd></div>
          <div><dt>SWIFT</dt><dd class="num" dir="ltr" data-thanks="bank-swift">…</dd></div>
          <div><dt>المبلغ</dt><dd class="num" data-thanks="amount">…</dd></div>
          <div><dt>مرجع الكفالة (اكتبه في البيان)</dt><dd class="num" dir="ltr" data-thanks="ref">…</dd></div>
        </dl>
      </div>

      <div class="thanks__ref" data-thanks-when="paid" hidden>
        <div>
          <strong data-thanks="name">…</strong>
          <div class="field__hint">
            <span class="num" data-thanks="amount">…</span> <span data-thanks="cycle">شهرياً</span> ·
            مرجع <span class="num" dir="ltr" data-thanks="ref">…</span> ·
            إيصال <span class="num" dir="ltr" data-thanks="receipt">…</span>
          </div>
          <div class="field__hint">القسط القادم <span class="num" data-thanks="next">…</span></div>
        </div>
      </div>

      <p class="field__hint" data-thanks-when="account" hidden>
        <i class="fa-regular fa-envelope" aria-hidden="true"></i>
        أنشأنا لك حساباً، وأرسلنا إلى بريدك رابطاً تعيّن منه كلمة مرورك.
      </p>

      <div class="cluster cluster--center" style="width:100%">
        <a class="btn btn--primary btn--lg" href="/dashboard">
          افتح لوحتي
          <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
        </a>
        <a class="btn btn--outline btn--lg" href="/orphans">اكفل طفلاً آخر</a>
      </div>

      <a class="link-arrow" href="/">
        <i class="fa-solid fa-house" aria-hidden="true"></i>
        العودة للرئيسية
      </a>
    </div>

    <!-- لا كفالة حديثة في هذه الجلسة: صفحة الشكر لا تُفتح بالرابط وحده -->
    <div class="thanks__card" id="thanksNone" hidden>
      <h1 style="font-size:var(--text-xl)">لا كفالة حديثة لعرضها</h1>
      <p class="section-lede">كفالاتك كلها في لوحتك، ومعها إيصالاتها.</p>
      <div class="cluster cluster--center">
        <a class="btn btn--primary" href="/dashboard">افتح لوحتي</a>
        <a class="btn btn--outline" href="/orphans">تصفّح الأطفال</a>
      </div>
    </div>
  </main>
@endsection
