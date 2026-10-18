@extends('site.layouts.app')

@section('title'){{ __('الأيتام بانتظار الكفالة') }}@endsection
@section('description'){{ __('تصفّح الحالات الموثّقة وابحث حسب البرنامج أو الدولة. كل حالة مزارة ميدانياً ومراجَعة من لجنة مستقلة.') }}@endsection
@section('canonical', url(lurl('/orphans')))

@push('styles')
  <link rel="stylesheet" href="/assets/css/kids.css">
  <link rel="stylesheet" href="/assets/css/orphans.css">
@endpush

@section('content')
  <main id="main">

    {{-- ══ الصدر: العنوان والأرقام الحيّة، والبحث أول ما تصله اليد ══ --}}
    <section class="cat-hero" aria-labelledby="catTitle">
      <div class="container">
        <nav aria-label="{{ __('مسار التنقل') }}">
          <ol class="breadcrumb cat-hero__crumbs">
            <li class="breadcrumb-item"><a href="{{ lurl('/') }}">{{ __('الرئيسية') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('الأيتام') }}</li>
          </ol>
        </nav>

        <div class="row g-4 g-lg-5 align-items-end">
          <div class="col-lg-7">
            <p class="kf-eyebrow cat-hero__eyebrow"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ __('زارهم مشرفونا في بيوتهم') }}</p>
            <h1 class="cat-hero__title" id="catTitle">{{ __('لكل طفلٍ هنا أسرةٌ وحلم،') }}<br><em>{{ __('وينقصهم من يقف معهم.') }}</em></h1>
            <p class="cat-hero__lede">{{ __('تحقّقت لجنة مستقلة من أوراق كل طفل قبل نشر ملفه. اختر طفلاً تكون من لمّته، وتصلك أخباره كل ثلاثة أشهر.') }}</p>
          </div>
          <div class="col-lg-5">
            <dl class="cat-stats">
              <div><dt>{{ __('ينتظرون من يقف معهم') }}</dt><dd class="num" data-hero-stat="all">0</dd></div>
              <div class="cat-stats__urgent"><dt>{{ __('حالات عاجلة') }}</dt><dd class="num" data-hero-stat="urgent">0</dd></div>
              <div><dt>{{ __('دولة') }}</dt><dd class="num" data-hero-stat="countries">0</dd></div>
            </dl>
          </div>
        </div>

        <form class="cat-search" role="search" action="{{ lurl('/orphans') }}" data-catalog-search>
          <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
          <label class="visually-hidden" for="catalogQ">{{ __('ابحث بالاسم أو المدينة أو الرمز') }}</label>
          <input type="search" id="catalogQ" name="q" data-filter-search placeholder="{{ __('ابحث باسم الطفل، أو المدينة، أو الرمز KFL') }}" autocomplete="off" enterkeyhint="search">
          <button class="btn btn-primary" type="submit">{{ __('ابحث') }}</button>
        </form>
      </div>
    </section>

    {{-- ══ الشريط: يلصق تحت الترويسة أثناء التصفّح ══ --}}
    <div class="cat-bar" id="catalogTop">
      <div class="container">
        <div class="cat-programs" role="group" aria-label="{{ __('البرنامج') }}" data-group="program"></div>

        <div class="cat-bar__row">
          {{-- الشاشة العريضة: قوائم منسدلة --}}
          <div class="cat-bar__filters d-none d-lg-flex" role="group" aria-label="{{ __('تصفية الأطفال') }}">
            @foreach (['gender' => 'الجنس', 'age' => 'العمر', 'country' => 'الدولة'] as $key => $label)
              <div class="dropdown">
                <button class="cat-pill dropdown-toggle" type="button" data-bs-toggle="dropdown" data-bs-offset="0,8" aria-expanded="false" data-pill="{{ $key }}">
                  <span class="cat-pill__label">{{ __($label) }}</span><span class="cat-pill__value" data-pill-value="{{ $key }}"></span>
                </button>
                <div class="dropdown-menu cat-menu" data-menu-for="{{ $key }}"></div>
              </div>
            @endforeach
            <button type="button" class="cat-pill cat-pill--urgent" data-filter="urgent" data-value="1" aria-pressed="false">
              <span class="cat-pill__dot" aria-hidden="true"></span> {{ __('عاجلة') }} <span class="num" data-urgent-count></span>
            </button>
          </div>

          {{-- الجوال: زرّ واحد يفتح لوحاً من الأسفل --}}
          <button class="cat-pill d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#filterSheet" aria-controls="filterSheet">
            <i class="fa-solid fa-sliders" aria-hidden="true"></i> {{ __('الفلاتر') }} <span class="cat-pill__badge num" data-filter-count hidden></span>
          </button>

          <label class="cat-sort">
            <span class="cat-sort__label">{{ __('الترتيب') }}</span>
            <select class="form-select" id="catalogSort"></select>
          </label>
        </div>
      </div>
    </div>

    {{-- ══ النتائج ══ --}}
    <section class="cat-results" aria-labelledby="catResultsTitle">
      <div class="container">
        <div class="cat-results__head">
          <h2 class="cat-results__title" id="catResultsTitle" role="status" aria-live="polite">
            <span id="catalogCount"><strong class="num">0</strong> {{ __('طفلاً') }}</span>
          </h2>
          <div class="cat-chips" id="activeFilters" hidden></div>
        </div>

        <div class="row g-4" id="catalogGrid" aria-busy="true">
          @for ($i = 0; $i < 8; $i++)
            <div class="col-sm-6 col-lg-4 col-xl-3"><div class="cat-skeleton" aria-hidden="true"></div></div>
          @endfor
        </div>

        <div class="cat-more" id="catalogMore" hidden>
          <p class="cat-more__text" id="catalogRange"></p>
          <span class="cat-more__track" aria-hidden="true"><span id="catalogProgress"></span></span>
          <button class="btn btn-outline-primary btn-lg" type="button" data-load-more>{{ __('عرض المزيد') }}</button>
        </div>
      </div>
    </section>

    {{-- ══ طمأنة قبل القرار: سطر واحد بلا بطاقات ══ --}}
    <section class="cat-assure" aria-label="{{ __('قبل أن تختار') }}">
      <div class="container">
        <ul class="cat-assure__list">
          <li><i class="fa-solid fa-hand" aria-hidden="true"></i><div><strong>{{ __('بلا التزام') }}</strong><span>{{ __('توقف الكفالة بنقرة من حسابك، بلا رسوم.') }}</span></div></li>
          <li><i class="fa-solid fa-file-lines" aria-hidden="true"></i><div><strong>{{ __('تقرير كل ثلاثة أشهر') }}</strong><span>{{ __('درجاته وصحته وكشف بما صُرف له.') }}</span></div></li>
          <li><i class="fa-solid fa-user-shield" aria-hidden="true"></i><div><strong>{{ __('خصوصية محفوظة') }}</strong><span>{{ __('لا نعرض بيانات الطفل الكاملة إلا لكافله.') }}</span></div></li>
        </ul>
      </div>
    </section>

  </main>
@endsection

@push('after-footer')
  {{-- لوح الفلاتر على الجوال: الخيارات نفسها أزراراً، و«اعرض النتائج» يغلقه --}}
  <div class="offcanvas offcanvas-bottom cat-sheet" tabindex="-1" id="filterSheet" aria-labelledby="filterSheetTitle">
    <div class="offcanvas-header">
      <h2 class="offcanvas-title h5" id="filterSheetTitle">{{ __('الفلاتر') }}</h2>
      <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="{{ __('إغلاق') }}"></button>
    </div>
    <div class="offcanvas-body" data-sheet></div>
    <div class="cat-sheet__foot">
      <button type="button" class="btn btn-link" data-reset>{{ __('مسح الكل') }}</button>
      <button type="button" class="btn btn-primary btn-lg flex-grow-1" data-bs-dismiss="offcanvas" data-sheet-apply>{{ __('اعرض النتائج') }}</button>
    </div>
  </div>

  <button class="to-top" type="button" id="toTop" aria-label="{{ __('العودة إلى أعلى الصفحة') }}">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush
