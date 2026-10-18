@extends('site.layouts.app')

{{--
  صفحة الشكر. تُملأ مما ردّ به الخادم عند الكفالة (checkout.js يحفظه في
  الجلسة)، لا من الرابط: «?ref=» وحده لا يكفي لعرض «تمّت الكفالة»، ولا تُقرأ
  منه بيانات. فرابطٌ مُتداول أو مُخمَّن يعرض «لا كفالة حديثة» لا تفاصيل غيرك.

  `data-g="مذكّر|مؤنّث"`: نصٌّ يُكتب بصيغة جنس الطفل حين تُعرف.
--}}
@section('title')تمّت الكفالة@endsection
@section('description')تم تفعيل كفالتك بنجاح.@endsection
@section('robots', 'noindex, nofollow')

@push('styles')
  <link rel="stylesheet" href="/assets/css/thankyou.css">
@endpush

@section('content')
  <main id="main" class="thanks">
    <div class="container">

      <div class="ty" id="thanksCard">
        {{-- ── الرأس: ختمٌ ينبض مرة، ثم الجملة ── --}}
        <header class="ty-head">
          <span class="ty-seal" aria-hidden="true">
            <img data-thanks-photo alt="" width="96" height="96" hidden>
            <i class="fa-solid fa-heart"></i>
          </span>

          <p class="ty-demo" data-thanks-when="demo" hidden><i class="fa-solid fa-flask" aria-hidden="true"></i> وضع تجريبي: لم يُخصم أي مال.</p>

          <div data-thanks-when="paid" hidden>
            <h1 class="ty-title">تمّت الكفالة. شكراً لأنك مع <span data-thanks="name">…</span>.</h1>
            <p class="ty-lede">جعلها الله في ميزان حسناتك. <span data-g="ملفه الكامل|ملفها الكامل">ملفه الكامل</span> في حسابك الآن، ويصلك أول تقرير مصوّر خلال تسعين يوماً.</p>
          </div>

          <div data-thanks-when="pending" hidden>
            <h1 class="ty-title">حجزنا كفالة <span data-thanks="name">…</span> باسمك.</h1>
            <p class="ty-lede">بقي أن تحوّل المبلغ. تتفعّل الكفالة حين يصلنا تحويلك، ويبقى الحجز حتى <strong data-thanks="hold">…</strong>.</p>
          </div>
        </header>

        {{-- ── تمّت: إيصالٌ مختصر ── --}}
        <section class="ty-receipt" data-thanks-when="paid" hidden aria-label="تفاصيل الكفالة">
          <dl>
            <div><dt>المبلغ</dt><dd><b class="num" data-thanks="amount">…</b> <span data-thanks="cycle">شهرياً</span></dd></div>
            <div><dt>القسط القادم</dt><dd data-thanks="next">…</dd></div>
            <div><dt>مرجع الكفالة</dt><dd><span class="num" dir="ltr" data-thanks="ref">…</span></dd></div>
            <div><dt>رقم الإيصال</dt><dd><span class="num" dir="ltr" data-thanks="receipt">…</span></dd></div>
          </dl>
          <p class="ty-receipt__mail"><i class="fa-regular fa-envelope" aria-hidden="true"></i> أرسلنا الإيصال إلى بريدك، وتجده في حسابك متى شئت.</p>
        </section>

        {{-- ── حجز: بيانات التحويل، وكلٌّ منها يُنسخ بنقرة ── --}}
        <section class="ty-bank" data-thanks-when="pending" hidden aria-labelledby="tyBankTitle">
          <h2 class="ty-h2" id="tyBankTitle">بيانات التحويل</h2>
          <ol class="ty-bank__steps">
            <li>حوّل <b class="num" data-thanks="amount">…</b> إلى الحساب أدناه.</li>
            <li>اكتب <b>مرجع الكفالة</b> في خانة البيان، به نطابق تحويلك.</li>
            <li>نفعّل الكفالة حين يصل، ونرسل لك الإيصال.</li>
          </ol>
          <dl class="ty-bank__list">
            <div><dt>مرجع الكفالة</dt><dd><span class="num" dir="ltr" data-thanks="ref">…</span><button type="button" class="ty-copy" data-copy="ref" aria-label="نسخ مرجع الكفالة"><i class="fa-regular fa-copy" aria-hidden="true"></i></button></dd></div>
            <div><dt>IBAN</dt><dd><span class="num" dir="ltr" data-thanks="bank-iban">…</span><button type="button" class="ty-copy" data-copy="bank-iban" aria-label="نسخ رقم IBAN"><i class="fa-regular fa-copy" aria-hidden="true"></i></button></dd></div>
            <div><dt>اسم الحساب</dt><dd data-thanks="bank-account">…</dd></div>
            <div><dt>البنك</dt><dd data-thanks="bank-name">…</dd></div>
            <div><dt>SWIFT</dt><dd><span class="num" dir="ltr" data-thanks="bank-swift">…</span><button type="button" class="ty-copy" data-copy="bank-swift" aria-label="نسخ رمز SWIFT"><i class="fa-regular fa-copy" aria-hidden="true"></i></button></dd></div>
          </dl>
          <p class="ty-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> إن لم يصل التحويل حتى نهاية المهلة يُلغى الحجز وحده، بلا أي رسوم، ويعود الطفل لمن يكفله.</p>
        </section>

        <p class="ty-account" data-thanks-when="account" hidden>
          <i class="fa-solid fa-key" aria-hidden="true"></i>
          <span><strong>أنشأنا لك حساباً.</strong> أرسلنا إلى بريدك رابطاً تعيّن منه كلمة مرورك، ومنه تتابع الكفالة وتقاريرها.</span>
        </p>

        {{-- ── ما بعد اليوم ── --}}
        <section class="ty-next" aria-labelledby="tyNextTitle">
          <h2 class="ty-h2" id="tyNextTitle">ماذا بعد؟</h2>
          <ol class="ty-timeline">
            <li><span class="ty-timeline__when">اليوم</span><strong data-g="ملفه الكامل في حسابك|ملفها الكامل في حسابك">ملفه الكامل في حسابك</strong><span data-g="قصته واحتياجاته وصورته الحقيقية، لك وحدك.|قصتها واحتياجاتها وصورتها الحقيقية، لك وحدك.">قصته واحتياجاته وصورته الحقيقية، لك وحدك.</span></li>
            <li><span class="ty-timeline__when">خلال 90 يوماً</span><strong>أول تقرير مصوّر</strong><span data-g="درجاته وصحته، وكشف بما صُرف له.|درجاتها وصحتها، وكشف بما صُرف لها.">درجاته وصحته، وكشف بما صُرف له.</span></li>
            <li><span class="ty-timeline__when">كل 3 أشهر</span><strong>زيارة ميدانية وتقرير جديد</strong><span>وتستطيع الإيقاف أو التعديل من حسابك متى شئت.</span></li>
          </ol>
        </section>

        <div class="ty-actions">
          <a class="btn btn-primary btn-lg" href="/dashboard">افتح حسابي <i class="fa-solid fa-arrow-left ms-1" aria-hidden="true"></i></a>
          <a class="btn btn-outline-primary btn-lg" href="/orphans">اكفل طفلاً آخر</a>
        </div>
        <button type="button" class="ty-share" data-share data-share-url="/orphans" data-share-title="أطفال ينتظرون من يكفلهم على {{ $site['name'] }}">
          <i class="fa-solid fa-share-nodes" aria-hidden="true"></i> ادعُ غيرك ليكفل طفلاً
        </button>
      </div>

      {{-- لا كفالة حديثة في هذه الجلسة: صفحة الشكر لا تُفتح بالرابط وحده --}}
      <div class="ty ty--none" id="thanksNone" hidden>
        <span class="ty-seal ty-seal--muted" aria-hidden="true"><i class="fa-solid fa-receipt"></i></span>
        <h1 class="ty-title">لا كفالة حديثة لعرضها</h1>
        <p class="ty-lede">كفالاتك كلها في حسابك، ومعها إيصالاتها.</p>
        <div class="ty-actions">
          <a class="btn btn-primary btn-lg" href="/dashboard">افتح حسابي</a>
          <a class="btn btn-outline-primary btn-lg" href="/orphans">تصفّح الأطفال</a>
        </div>
      </div>

    </div>
  </main>
@endsection
