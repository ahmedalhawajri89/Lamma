@extends('site.layouts.app')

{{--
  صفحة الشكر. تُملأ مما ردّ به الخادم عند الكفالة (checkout.js يحفظه في
  الجلسة)، لا من الرابط: «?ref=» وحده لا يكفي لعرض «تمّت الكفالة»، ولا تُقرأ
  منه بيانات. فرابطٌ مُتداول أو مُخمَّن يعرض «لا كفالة حديثة» لا تفاصيل غيرك.

  `data-g="مذكّر|مؤنّث"`: نصٌّ يُكتب بصيغة جنس الطفل حين تُعرف.
--}}
@section('title'){{ __('تمّت الكفالة') }}@endsection
@section('description'){{ __('تم تفعيل كفالتك بنجاح.') }}@endsection
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

          <p class="ty-demo" data-thanks-when="demo" hidden><i class="fa-solid fa-flask" aria-hidden="true"></i> {{ __('وضع تجريبي: لم يُخصم أي مال.') }}</p>

          <div data-thanks-when="paid" hidden>
            <h1 class="ty-title">{{ __('تمّت الكفالة. شكراً لأنك مع') }} <span data-thanks="name">…</span>.</h1>
            <p class="ty-lede">{{ __('جعلها الله في ميزان حسناتك.') }} <span data-g="{{ __('ملفه الكامل') }}|{{ __('ملفها الكامل') }}">{{ __('ملفه الكامل') }}</span> {{ __('في حسابك الآن، ويصلك أول تقرير مصوّر خلال تسعين يوماً.') }}</p>
          </div>

          <div data-thanks-when="pending" hidden>
            <h1 class="ty-title">{{ __('حجزنا كفالة') }} <span data-thanks="name">…</span> {{ __('باسمك.') }}</h1>
            <p class="ty-lede">{{ __('بقي أن تحوّل المبلغ. تتفعّل الكفالة حين يصلنا تحويلك، ويبقى الحجز حتى') }} <strong data-thanks="hold">…</strong>.</p>
          </div>
        </header>

        {{-- ── تمّت: إيصالٌ مختصر ── --}}
        <section class="ty-receipt" data-thanks-when="paid" hidden aria-label="{{ __('تفاصيل الكفالة') }}">
          <dl>
            <div><dt>{{ __('المبلغ') }}</dt><dd><b class="num" data-thanks="amount">…</b> <span data-thanks="cycle">{{ __('شهرياً') }}</span></dd></div>
            <div><dt>{{ __('القسط القادم') }}</dt><dd data-thanks="next">…</dd></div>
            <div><dt>{{ __('مرجع الكفالة') }}</dt><dd><span class="num" dir="ltr" data-thanks="ref">…</span></dd></div>
            <div><dt>{{ __('رقم الإيصال') }}</dt><dd><span class="num" dir="ltr" data-thanks="receipt">…</span></dd></div>
          </dl>
          <p class="ty-receipt__mail"><i class="fa-regular fa-envelope" aria-hidden="true"></i> {{ __('أرسلنا الإيصال إلى بريدك، وتجده في حسابك متى شئت.') }}</p>
        </section>

        {{-- ── حجز: بيانات التحويل، وكلٌّ منها يُنسخ بنقرة ── --}}
        <section class="ty-bank" data-thanks-when="pending" hidden aria-labelledby="tyBankTitle">
          <h2 class="ty-h2" id="tyBankTitle">{{ __('بيانات التحويل') }}</h2>
          <ol class="ty-bank__steps">
            <li>{{ __('حوّل') }} <b class="num" data-thanks="amount">…</b> {{ __('إلى الحساب أدناه.') }}</li>
            <li>{{ __('اكتب') }} <b>{{ __('مرجع الكفالة') }}</b> {{ __('في خانة البيان، به نطابق تحويلك.') }}</li>
            <li>{{ __('نفعّل الكفالة حين يصل، ونرسل لك الإيصال.') }}</li>
          </ol>
          <dl class="ty-bank__list">
            <div><dt>{{ __('مرجع الكفالة') }}</dt><dd><span class="num" dir="ltr" data-thanks="ref">…</span><button type="button" class="ty-copy" data-copy="ref" aria-label="{{ __('نسخ مرجع الكفالة') }}"><i class="fa-regular fa-copy" aria-hidden="true"></i></button></dd></div>
            <div><dt>IBAN</dt><dd><span class="num" dir="ltr" data-thanks="bank-iban">…</span><button type="button" class="ty-copy" data-copy="bank-iban" aria-label="{{ __('نسخ رقم IBAN') }}"><i class="fa-regular fa-copy" aria-hidden="true"></i></button></dd></div>
            <div><dt>{{ __('اسم الحساب') }}</dt><dd data-thanks="bank-account">…</dd></div>
            <div><dt>{{ __('البنك') }}</dt><dd data-thanks="bank-name">…</dd></div>
            <div><dt>SWIFT</dt><dd><span class="num" dir="ltr" data-thanks="bank-swift">…</span><button type="button" class="ty-copy" data-copy="bank-swift" aria-label="{{ __('نسخ رمز SWIFT') }}"><i class="fa-regular fa-copy" aria-hidden="true"></i></button></dd></div>
          </dl>
          <p class="ty-note"><i class="fa-solid fa-circle-info" aria-hidden="true"></i> {{ __('إن لم يصل التحويل حتى نهاية المهلة يُلغى الحجز وحده، بلا أي رسوم، ويعود الطفل لمن يكفله.') }}</p>
        </section>

        <p class="ty-account" data-thanks-when="account" hidden>
          <i class="fa-solid fa-key" aria-hidden="true"></i>
          <span><strong>{{ __('أنشأنا لك حساباً.') }}</strong> {{ __('أرسلنا إلى بريدك رابطاً تعيّن منه كلمة مرورك، ومنه تتابع الكفالة وتقاريرها.') }}</span>
        </p>

        {{-- ── ما بعد اليوم ── --}}
        <section class="ty-next" aria-labelledby="tyNextTitle">
          <h2 class="ty-h2" id="tyNextTitle">{{ __('ماذا بعد؟') }}</h2>
          <ol class="ty-timeline">
            <li><span class="ty-timeline__when">{{ __('اليوم') }}</span><strong data-g="{{ __('ملفه الكامل في حسابك') }}|{{ __('ملفها الكامل في حسابك') }}">{{ __('ملفه الكامل في حسابك') }}</strong><span data-g="{{ __('قصته واحتياجاته وصورته الحقيقية، لك وحدك.') }}|{{ __('قصتها واحتياجاتها وصورتها الحقيقية، لك وحدك.') }}">{{ __('قصته واحتياجاته وصورته الحقيقية، لك وحدك.') }}</span></li>
            <li><span class="ty-timeline__when">{{ __('خلال 90 يوماً') }}</span><strong>{{ __('أول تقرير مصوّر') }}</strong><span data-g="{{ __('درجاته وصحته، وكشف بما صُرف له.') }}|{{ __('درجاتها وصحتها، وكشف بما صُرف لها.') }}">{{ __('درجاته وصحته، وكشف بما صُرف له.') }}</span></li>
            <li><span class="ty-timeline__when">{{ __('كل 3 أشهر') }}</span><strong>{{ __('زيارة ميدانية وتقرير جديد') }}</strong><span>{{ __('وتستطيع الإيقاف أو التعديل من حسابك متى شئت.') }}</span></li>
          </ol>
        </section>

        <div class="ty-actions">
          <a class="btn btn-primary btn-lg" href="{{ lurl('/dashboard') }}">{{ __('افتح حسابي') }} <i class="fa-solid fa-arrow-left ms-1" aria-hidden="true"></i></a>
          <a class="btn btn-outline-primary btn-lg" href="{{ lurl('/orphans') }}">{{ __('اكفل طفلاً آخر') }}</a>
        </div>
        <button type="button" class="ty-share" data-share data-share-url="{{ lurl('/orphans') }}" data-share-title="{{ __('أطفال ينتظرون من يكفلهم على :name', ['name' => $site['name']]) }}">
          <i class="fa-solid fa-share-nodes" aria-hidden="true"></i> {{ __('ادعُ غيرك ليكفل طفلاً') }}
        </button>
      </div>

      {{-- لا كفالة حديثة في هذه الجلسة: صفحة الشكر لا تُفتح بالرابط وحده --}}
      <div class="ty ty--none" id="thanksNone" hidden>
        <span class="ty-seal ty-seal--muted" aria-hidden="true"><i class="fa-solid fa-receipt"></i></span>
        <h1 class="ty-title">{{ __('لا كفالة حديثة لعرضها') }}</h1>
        <p class="ty-lede">{{ __('كفالاتك كلها في حسابك، ومعها إيصالاتها.') }}</p>
        <div class="ty-actions">
          <a class="btn btn-primary btn-lg" href="{{ lurl('/dashboard') }}">{{ __('افتح حسابي') }}</a>
          <a class="btn btn-outline-primary btn-lg" href="{{ lurl('/orphans') }}">{{ __('تصفّح الأطفال') }}</a>
        </div>
      </div>

    </div>
  </main>
@endsection
