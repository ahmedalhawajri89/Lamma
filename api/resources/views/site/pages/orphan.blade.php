@extends('site.layouts.app')

{{--
  صفحة الطفل: تُرسم من الخادم (OrphanPageController). $kid بشكل الـ API العام
  نفسه مع ما تحتاجه لوحة الكفالة (المتبقي، وهل يقبل كفالة)، أو null لرمزٍ لا
  طفل له. الأطفال المشابهون وحدهم يملؤهم JS (profile.js) ببطاقة الفهرس.
--}}
@php
  if ($kid) {
      $her = $kid['her'];
      $age = (int) $kid['age'];
      $ageLabel = $age.' '.($age >= 3 && $age <= 10 ? 'سنوات' : 'سنة');
      $funded = (int) $kid['funded'];
      $sponsors = (int) ($kid['sponsors'] ?? 0);
      $money = fn ($v) => \App\Support\PublicChild::money($v);
      $sponsorsLabel = match (true) {
          $sponsors === 1 => 'شخص واحد',
          $sponsors === 2 => 'شخصان',
          $sponsors <= 10 => $sponsors.' أشخاص',
          default => $sponsors.' شخصاً',
      };
      $state = ! $kid['accepting'] ? 'done' : ($funded > 0 ? 'shared' : 'open');
      $cta = ($state === 'shared' ? 'أكمل كفالة ' : 'اكفل ').$kid['name'];
      $place = collect([$kid['city'] ?? null, $kid['country'] ?? null])->filter()->join('، ');
  }
@endphp

@if ($kid)
  @section('title'){{ $kid['name'] }}، {{ $ageLabel }}@endsection
  @section('description'){{ $kid['dream'] ? ($her ? 'حلمها: ' : 'حلمه: ').$kid['dream'].'. ' : '' }}ملف موثّق من زيارة ميدانية في {{ $place }}. {{ $her ? 'كفالتها' : 'كفالته' }} {{ $money($kid['monthly']) }} في الشهر، وتصلك {{ $her ? 'أخبارها' : 'أخباره' }} كل ثلاثة أشهر.@endsection
  @section('og_title'){{ $state === 'done' ? 'اكتملت كفالة '.$kid['name'] : 'ساعد '.$kid['name'].'، '.$ageLabel.'، على تحقيق '.($her ? 'حلمها' : 'حلمه') }}@endsection
@else
  @section('title')لم نجد هذا الطفل@endsection
@endif
{{-- ملفات الأطفال لا تُفهرس في محركات البحث: تُشارَك برابطها ولا يُعثر عليها بالاسم --}}
@section('robots', 'noindex')

@push('styles')
  <link rel="stylesheet" href="/assets/css/kids.css">
  <link rel="stylesheet" href="/assets/css/orphan.css">
@endpush

@section('content')
  <main id="main">
  @if (! $kid)
    <section class="kid-missing">
      <div class="container">
        <span class="kid-missing__icon" aria-hidden="true"><i class="fa-solid fa-child"></i></span>
        <h1>لم نجد هذا الطفل</h1>
        <p>ربما تغيّر رابط ملفه أو لم يعد منشوراً. الأطفال الذين ينتظرون كافلاً في صفحتهم.</p>
        <a class="btn btn-cta btn-lg" href="{{ lurl('/orphans') }}">أطفال ينتظرون كافلاً</a>
      </div>
    </section>
  @else
    <section class="kid-profile">
      <div class="container">
        <nav aria-label="مسار التنقل">
          <ol class="breadcrumb kid-profile__crumbs">
            <li class="breadcrumb-item"><a href="{{ lurl('/') }}">الرئيسية</a></li>
            <li class="breadcrumb-item"><a href="{{ lurl('/orphans') }}">الأيتام</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $kid['name'] }}</li>
          </ol>
        </nav>

        <div class="row g-4 g-xl-5">
          {{-- ══ الملف ══ --}}
          <div class="col-lg-7 kid-main">
            <header class="kid-head">
              <div class="kid-head__tags">
                @if ($kid['urgent'])
                  <span class="kid-tag kid-tag--urgent"><span class="kid-tag__dot" aria-hidden="true"></span> حالة عاجلة</span>
                @endif
                <span class="kid-tag kid-tag--verified"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ $her ? 'زارها' : 'زاره' }} مشرف ميداني</span>
              </div>
              <h1 class="kid-head__name">{{ $kid['name'] }}<span>، {{ $ageLabel }}</span></h1>
              <p class="kid-head__meta">
                @if ($place)<span><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ $place }}</span>@endif
                @if ($kid['program_name'])<span><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> {{ $kid['program_name'] }}</span>@endif
                <span>رقم الملف <span class="num" dir="ltr">{{ $kid['id'] }}</span></span>
              </p>
            </header>

            @if ($kid['dream'])
              <figure class="kid-dream">
                <i class="fa-solid fa-quote-right kid-dream__mark" aria-hidden="true"></i>
                <blockquote class="kid-dream__text"><p>{{ $kid['dream'] }}</p></blockquote>
                <figcaption>{{ $her ? 'حلمها' : 'حلمه' }}، كما {{ $her ? 'قالته' : 'قاله' }} لمشرفنا</figcaption>
              </figure>
            @endif

            @if ($kid['story'])
              <div class="kid-story">
                <h2 class="kid-h2">{{ $her ? 'قصتها' : 'قصته' }}</h2>
                <p>{{ $kid['story'] }}</p>
              </div>
            @endif

            <dl class="kid-facts">
              <div><dt><i class="fa-solid fa-graduation-cap" aria-hidden="true"></i> الدراسة</dt><dd>{{ $kid['grade'] ?: 'غير مسجّلة' }}</dd></div>
              <div><dt><i class="fa-solid fa-heart-pulse" aria-hidden="true"></i> الصحة</dt><dd>{{ $kid['health'] ?: 'غير مسجّلة' }}</dd></div>
              <div><dt><i class="fa-solid fa-hand-holding-heart" aria-hidden="true"></i> البرنامج</dt><dd>{{ $kid['program_name'] ?: '—' }}</dd></div>
            </dl>

            <section class="kid-block" aria-labelledby="coversTitle">
              <h2 class="kid-h2" id="coversTitle">ما تغطيه كفالتك</h2>
              <ul class="kid-covers">
                <li><i class="fa-solid fa-bowl-food" aria-hidden="true"></i><div><strong>المعيشة</strong><span>غذاء وكسوة موسمية.</span></div></li>
                <li><i class="fa-solid fa-book-open" aria-hidden="true"></i><div><strong>التعليم</strong><span>رسوم وكتب وقرطاسية، ودروس تقوية عند الحاجة.</span></div></li>
                <li><i class="fa-solid fa-stethoscope" aria-hidden="true"></i><div><strong>الصحة</strong><span>فحص دوري وأدوية ومتابعة طبية.</span></div></li>
                <li><i class="fa-solid fa-hands-holding-child" aria-hidden="true"></i><div><strong>الدعم النفسي</strong><span>جلسات إرشاد وأنشطة مع {{ $her ? 'أقرانها' : 'أقرانه' }}.</span></div></li>
              </ul>
            </section>

            <section class="kid-block" aria-labelledby="afterTitle">
              <h2 class="kid-h2" id="afterTitle">ما يصلك بعد الكفالة</h2>
              <ol class="kid-after">
                <li><span class="kid-after__num num">1</span><div><strong>ملف {{ $kid['name'] }} كاملاً</strong><span>{{ $her ? 'قصتها واحتياجاتها وصورتها الحقيقية' : 'قصته واحتياجاته وصورته الحقيقية' }}، في حسابك وحدك.</span></div></li>
                <li><span class="kid-after__num num">2</span><div><strong>تقرير مصوّر كل ثلاثة أشهر</strong><span>{{ $her ? 'درجاتها وصحتها، وكشفٌ بما صُرف لها' : 'درجاته وصحته، وكشفٌ بما صُرف له' }} من كفالتك.</span></div></li>
                <li><span class="kid-after__num num">3</span><div><strong>زيارة ميدانية كل 90 يوماً</strong><span>{{ $her ? 'يزورها مشرفنا في بيتها' : 'يزوره مشرفنا في بيته' }} ويكتب لك ما رآه.</span></div></li>
              </ol>
              <p class="kid-note"><i class="fa-solid fa-lock" aria-hidden="true"></i> صورة الطفل وتقاريره التفصيلية لكافله وحده، حمايةً لخصوصيته.</p>
            </section>
          </div>

          {{-- ══ الرسم ولوحة الكفالة: تلتصقان أثناء القراءة ══ --}}
          <aside class="col-lg-5 kid-side" aria-label="الكفالة">
            <div class="kid-aside">
              <figure class="kid-art">
                <img src="{{ $kid['photo'] }}" alt="" width="400" height="400">
                <figcaption>رسم توضيحي. لا ننشر صور الأطفال إلا لكافليهم.</figcaption>
              </figure>

              <div @class(['kid-panel', 'kid-panel--done' => $state === 'done'])>
                <p class="kid-panel__price"><strong class="num">{{ $money($kid['monthly']) }}</strong> <span>{{ $her ? 'كفالتها' : 'كفالته' }} في الشهر</span></p>

                <div class="kid-panel__fund">
                  <span class="kid-panel__track" role="progressbar" aria-label="نسبة كفالة {{ $kid['name'] }}" aria-valuenow="{{ $funded }}" aria-valuemin="0" aria-valuemax="100"><span style="--v: {{ $funded }}%"></span></span>
                  <p class="kid-panel__status">
                    @if ($state === 'done')
                      <span>مكفول بالكامل</span>
                    @elseif ($state === 'shared')
                      <span>{{ $her ? 'يكفلها' : 'يكفله' }} {{ $sponsorsLabel }}</span>
                      <span>يتبقى <b class="num">{{ $money($kid['remaining']) }}</b> في الشهر</span>
                    @else
                      <span>لم {{ $her ? 'يكفلها' : 'يكفله' }} أحد بعد</span>
                      <span class="num">{{ $funded }}٪</span>
                    @endif
                  </p>
                </div>

                @if ($state === 'done')
                  <p class="kid-panel__done" role="status">
                    <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                    <span>اكتملت {{ $her ? 'كفالتها' : 'كفالته' }}، شكراً لكافلي{{ $her ? 'ها' : 'ه' }}.</span>
                  </p>
                  <a class="btn btn-primary btn-lg w-100" href="{{ lurl('/orphans') }}">أطفال ينتظرون كافلاً <i class="fa-solid fa-arrow-left ms-1" aria-hidden="true"></i></a>
                @else
                  @if ($state === 'shared')
                    <p class="kid-panel__share">تكمل {{ $her ? 'كفالتها' : 'كفالته' }} بأي مبلغ من <b class="num">$5</b> حتى <b class="num">{{ $money($kid['remaining']) }}</b>.</p>
                  @endif
                  <a class="btn btn-cta btn-lg w-100" href="{{ lurl('/checkout?id=') }}{{ urlencode($kid['id']) }}">{{ $cta }} <i class="fa-solid fa-arrow-left ms-1" aria-hidden="true"></i></a>
                  <ul class="kid-panel__assure">
                    <li><i class="fa-solid fa-lock" aria-hidden="true"></i> دفع مشفّر</li>
                    <li><i class="fa-solid fa-hand" aria-hidden="true"></i> إيقاف بنقرة بلا رسوم</li>
                    <li><a href="{{ lurl('/faq#zakat') }}"><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i> تُحتسب من الزكاة؟</a></li>
                  </ul>
                @endif

                <div class="kid-panel__sharebox">
                  <p class="kid-panel__sharelabel">شارك ملف {{ $kid['name'] }}{{ $state === 'done' ? '' : '، لعلّ أحداً يكفل'.($her ? 'ها' : 'ه') }}</p>
                  @include('site.partials.share', [
                      'url' => url(lurl('/orphan?id='.urlencode($kid['id']))),
                      'title' => $state === 'done' ? 'اكتملت كفالة '.$kid['name'] : 'ساعد '.$kid['name'].' على تحقيق '.($her ? 'حلمها' : 'حلمه'),
                      'label' => 'شارك ملف '.$kid['name'],
                  ])
                </div>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </section>

    {{-- ══ أطفال آخرون: بطاقة الفهرس نفسها، يملؤها profile.js ══ --}}
    <section class="kid-similar" aria-labelledby="similarTitle" data-similar data-program="{{ $kid['program'] }}" data-exclude="{{ $kid['id'] }}" hidden>
      <div class="container">
        <div class="kid-similar__head">
          <h2 class="kid-h2" id="similarTitle">{{ $state === 'done' ? 'أطفال ينتظرون كافلاً' : 'قد تودّ الوقوف معهم أيضاً' }}</h2>
          <a class="link-arrow" href="{{ lurl('/orphans') }}">كل الأطفال <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
        </div>
        <div class="row g-4" id="similarOrphans"></div>
      </div>
    </section>
  @endif
  </main>
@endsection

@push('after-footer')
  @if ($kid && $state !== 'done')
    {{-- شريط الإجراء على الجوال: المبلغ والزرّ في متناول الإبهام --}}
    <div class="action-bar" id="actionBar">
      <div class="action-bar__info">
        <span>{{ $state === 'shared' ? 'يتبقى من كفالة '.$kid['name'] : 'كفالة '.$kid['name'] }}</span>
        <strong class="num">{{ $money($state === 'shared' ? $kid['remaining'] : $kid['monthly']) }} في الشهر</strong>
      </div>
      <a class="btn btn-cta" href="{{ lurl('/checkout?id=') }}{{ urlencode($kid['id']) }}">{{ $state === 'shared' ? ($her ? 'أكمل كفالتها' : 'أكمل كفالته') : 'اكفل الآن' }}</a>
    </div>
  @endif

  <button class="to-top" type="button" id="toTop" aria-label="العودة إلى أعلى الصفحة">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush
