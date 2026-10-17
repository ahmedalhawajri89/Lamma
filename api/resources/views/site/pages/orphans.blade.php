@extends('site.layouts.legacy')

@section('title')الأيتام بانتظار الكفالة | كفالة@endsection
@section('description')تصفّح الحالات الموثّقة وابحث حسب البرنامج أو الدولة. كل حالة مزارة ميدانياً ومراجَعة من لجنة مستقلة.@endsection
@section('canonical', url('/orphans'))

@section('content')
  <main id="main">

    <!-- ══ الصدر: من ينتظر، بأرقام حيّة ووجوه ══ -->
    <section class="kids-hero" aria-labelledby="kidsHeroTitle">
      <div class="container kids-hero__inner">
        <div class="kids-hero__text">
          <nav aria-label="مسار التنقل">
            <ol class="breadcrumb breadcrumb--on-ink">
              <li><a href="/">الرئيسية</a></li>
              <li><span aria-current="page">الأيتام</span></li>
            </ol>
          </nav>
          <h1 class="kids-hero__title" id="kidsHeroTitle">لكل طفلٍ هنا حلم،<br>وينقصه من يقف معه.</h1>
          <p class="kids-hero__lede">
            زار مشرفونا كل طفل في بيته، وتحقّقت لجنة مستقلة من أوراقه. اختر من تكفله،
            وتصلك أخباره كل ثلاثة أشهر.
          </p>
          <dl class="kids-hero__stats">
            <div><dt>ينتظرون كافلاً</dt><dd class="num" data-hero-stat="all">0</dd></div>
            <div><dt>حالة عاجلة</dt><dd class="num" data-hero-stat="urgent">0</dd></div>
            <div><dt>دولة</dt><dd class="num" data-hero-stat="countries">0</dd></div>
          </dl>
        </div>
        <!-- وجوهٌ ممن لهم صور: تُملأ من الخادم، وتُخفى إن لم تكن صور -->
        <div class="kids-hero__faces" id="heroFaces" aria-hidden="true"></div>
      </div>
    </section>

    <section class="section section--catalog" id="catalogTop">
      <div class="container">
        <!-- ══ شريط الاختيار: البرامج، ثم الجنس والعمر والدولة، ثم الترتيب ══ -->
        <div class="fbar" role="search" aria-label="تصفية الأطفال">
          <div class="fbar__programs" role="group" aria-label="البرنامج" data-group="program"></div>
          <div class="fbar__row">
            <div class="fbar__pills">
              <button type="button" class="pill" data-menu="gender" aria-haspopup="dialog" aria-expanded="false">
                <span class="pill__label">الجنس</span><span class="pill__value" data-pill-value="gender"></span>
                <i class="fa-solid fa-chevron-down pill__caret" aria-hidden="true"></i>
              </button>
              <button type="button" class="pill" data-menu="age" aria-haspopup="dialog" aria-expanded="false">
                <span class="pill__label">العمر</span><span class="pill__value" data-pill-value="age"></span>
                <i class="fa-solid fa-chevron-down pill__caret" aria-hidden="true"></i>
              </button>
              <button type="button" class="pill" data-menu="country" aria-haspopup="dialog" aria-expanded="false">
                <span class="pill__label">الدولة</span><span class="pill__value" data-pill-value="country"></span>
                <i class="fa-solid fa-chevron-down pill__caret" aria-hidden="true"></i>
              </button>
              <button type="button" class="pill pill--urgent" data-filter="urgent" data-value="1" aria-pressed="false">
                <span class="pill__dot" aria-hidden="true"></span> عاجلة <span class="pill__count num" data-urgent-count></span>
              </button>
            </div>
            <div class="fbar__end">
              <label class="fbar__search">
                <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                <span class="sr-only">ابحث بالاسم أو المدينة أو الرمز</span>
                <input type="search" data-filter-search placeholder="اسم، مدينة، أو KFL" autocomplete="off">
              </label>
              <label class="fbar__sort">
                <span class="sr-only">الترتيب</span>
                <select class="select" id="catalogSort"></select>
              </label>
            </div>
          </div>
          <!-- قائمة الخيار المفتوح: تحت زرّه على الشاشة العريضة، ومن الأسفل على الجوال -->
          <div class="fmenu" id="filterMenu" role="dialog" aria-modal="false" hidden></div>
        </div>

        <div class="catalog__bar">
          <p class="catalog__count" role="status" aria-live="polite">
            <span id="catalogCount"><strong class="num">0</strong> طفلاً</span>
            <span class="catalog__range num" id="catalogRange"></span>
          </p>
          <div class="active-filters" id="activeFilters" hidden></div>
        </div>

        <h2 class="sr-only">الأطفال المتاحون للكفالة</h2>
        <div class="kids kids--catalog" id="catalogGrid" aria-busy="true">
          <div class="kid-skeleton"></div><div class="kid-skeleton"></div><div class="kid-skeleton"></div>
          <div class="kid-skeleton"></div><div class="kid-skeleton"></div><div class="kid-skeleton"></div>
        </div>

        <nav class="pager" id="catalogPager" aria-label="صفحات النتائج" hidden></nav>
      </div>
    </section>

    <!-- طمأنة -->
    <section class="section section--surface">
      <div class="container">
        <div class="block-head">
          <div class="block-head__text">
            <p class="meta-label">قبل أن تختار</p>
            <h2>ثلاثة أشياء يطمئنك أن تعرفها</h2>
          </div>
        </div>

        <ol class="columns" style="--cols:3">
          <li>
            <span class="columns__num num">01</span>
            <h3>لا التزام</h3>
            <p>يمكنك إيقاف الكفالة متى شئت بلا رسوم. نطلب فقط إشعاراً بشهر لتأمين بديل.</p>
          </li>
          <li>
            <span class="columns__num num">02</span>
            <h3>تقرير كل ٣ أشهر</h3>
            <p>صورة حديثة ودرجات وتقرير صحي وكشف بما صُرف. يصلك على بريدك وفي حسابك.</p>
          </li>
          <li>
            <span class="columns__num num">03</span>
            <h3>خصوصية محفوظة</h3>
            <p>لا نشارك بياناتك مع أي جهة، ولا نعرض بيانات الطفل الكاملة إلا لكافله.</p>
          </li>
        </ol>
      </div>
    </section>

  </main>
@endsection

@push('after-footer')
  <button class="to-top" type="button" id="toTop" aria-label="العودة إلى أعلى الصفحة">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush
