@extends('site.layouts.app')

{{--
  الأسئلة الشائعة (FaqPageController): الأسئلة وأجوبتها من الخادم في
  <details> تُفتح بلا JS، ومعها FAQPage لمحركات البحث. faq.js يضيف البحث
  والتصنيف، ويفتح السؤال الذي في الرابط (/faq#zakat).
--}}
@php
  $wa = 'https://wa.me/'.ltrim((string) $contact['phoneHref'], '+');
  $schema = [
      '@context' => 'https://schema.org',
      '@type' => 'FAQPage',
      'mainEntity' => $faqs->map(fn ($f) => [
          '@type' => 'Question',
          'name' => $f['q'],
          'acceptedAnswer' => ['@type' => 'Answer', 'text' => $f['a']],
      ])->values()->all(),
  ];
@endphp

@section('title')الأسئلة الشائعة@endsection
@section('description')إجابات مباشرة عن الكفالة: كيف نتحقق، ما نسبة المصاريف الإدارية، هل تُحتسب من الزكاة، وكيف توقفها.@endsection
@section('canonical', url(lurl('/faq')))

@push('styles')
  <link rel="stylesheet" href="/assets/css/faq.css">
  @if ($faqs->isNotEmpty())
    <script type="application/ld+json">@json($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)</script>
  @endif
@endpush

@section('content')
  <main id="main">

    {{-- ══ الصدر والبحث ══ --}}
    <section class="fq-hero" aria-labelledby="fqTitle">
      <div class="container">
        <nav aria-label="مسار التنقل">
          <ol class="breadcrumb fq-crumbs">
            <li class="breadcrumb-item"><a href="{{ lurl('/') }}">الرئيسية</a></li>
            <li class="breadcrumb-item active" aria-current="page">الأسئلة الشائعة</li>
          </ol>
        </nav>
        <h1 class="fq-title" id="fqTitle">أسئلة يطرحها الناس<br><em>قبل أن يبدأوا.</em></h1>
        <p class="fq-lede">أجبنا عنها بصراحة، بما فيها الأسئلة المحرجة عن المال والمصاريف.</p>

        <label class="fq-search">
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
          <span class="visually-hidden">ابحث في الأسئلة</span>
          <input type="search" id="faqSearch" placeholder="ابحث: الزكاة، الإيقاف، التقارير…" autocomplete="off" enterkeyhint="search">
        </label>
      </div>
    </section>

    <section class="fq-main">
      <div class="container">
        <div class="row g-4 g-xl-5">
          <div class="col-lg-8">
            @if ($categories->count() > 1)
              <div class="fq-cats" role="group" aria-label="التصنيف">
                <button type="button" class="fq-cat" data-faq-cat="" aria-pressed="true">الكل <span class="num">{{ $faqs->count() }}</span></button>
                @foreach ($categories as $name => $n)
                  <button type="button" class="fq-cat" data-faq-cat="{{ $name }}" aria-pressed="false">{{ $name }} <span class="num">{{ $n }}</span></button>
                @endforeach
              </div>
            @endif

            <p class="fq-status" id="faqStatus" role="status" aria-live="polite"></p>

            <div class="fq-list" id="faqItems">
              @foreach ($faqs as $f)
                <details class="fq-item" id="{{ $f['id'] }}" data-category="{{ $f['category'] }}" @if ($loop->first) open @endif>
                  <summary>
                    <span class="fq-item__icon" aria-hidden="true"><i class="fa-solid {{ $f['icon'] }}"></i></span>
                    <span class="fq-item__q">{{ $f['q'] }}</span>
                    <span class="fq-item__chev" aria-hidden="true"><i class="fa-solid fa-chevron-down"></i></span>
                  </summary>
                  <div class="fq-item__a"><p>{{ $f['a'] }}</p></div>
                </details>
              @endforeach
            </div>

            <div class="fq-empty" id="faqEmpty" hidden>
              <span class="fq-empty__icon" aria-hidden="true"><i class="fa-regular fa-comment-dots"></i></span>
              <h2>لم نجد سؤالاً بهذا البحث</h2>
              <p>اسألنا مباشرة، ونجيبك خلال 72 ساعة.</p>
              <a class="btn btn-primary" href="{{ lurl('/contact') }}">اطرح سؤالك</a>
            </div>
          </div>

          {{-- ══ لم تجد جوابك؟ ══ --}}
          <aside class="col-lg-4">
            <div class="fq-aside">
              <div class="fq-help">
                <span class="fq-help__icon" aria-hidden="true"><i class="fa-regular fa-comments"></i></span>
                <h2>لم تجد جوابك؟</h2>
                <p>اسألنا عن أي شيء، حتى أدقّ بنود الميزانية.</p>
                <a class="fq-help__link fq-help__link--wa" href="{{ $wa }}" target="_blank" rel="noopener noreferrer"><i class="fa-brands fa-whatsapp" aria-hidden="true"></i> واتساب <span>أسرع ردّ</span></a>
                <a class="fq-help__link" href="{{ lurl('/contact') }}"><i class="fa-regular fa-envelope" aria-hidden="true"></i> نموذج التواصل <span>خلال 72 ساعة</span></a>
              </div>
              <a class="fq-side-card" href="{{ lurl('/transparency') }}">
                <i class="fa-solid fa-chart-pie" aria-hidden="true"></i>
                <span><strong>صفحة الشفافية</strong><span>الميزانية وأين يذهب كل دولار، محدّثة مع كل صرف.</span></span>
              </a>
            </div>
          </aside>
        </div>
      </div>
    </section>

  </main>
@endsection

@push('after-footer')
  <button class="to-top" type="button" id="toTop" aria-label="العودة إلى أعلى الصفحة">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush
