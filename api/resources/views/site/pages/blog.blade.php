@extends('site.layouts.legacy')

@section('title')المدونة | كفالة@endsection
@section('description')نكتب عن آلية عملنا وقراراتنا وأخطائنا: الشفافية، سياسات الحماية، قصص الأطفال، وتقارير الحملات.@endsection
@section('canonical', url('/blog'))

@section('content')
  <main id="main">

    <!-- ══ الرأس ══ -->
    <section class="blog-hero" aria-labelledby="blogTitle">
      <div class="container">
        <nav aria-label="مسار التنقل">
          <ol class="breadcrumb">
            <li><a href="/">الرئيسية</a></li>
            <li><span aria-current="page">المدونة</span></li>
          </ol>
        </nav>
        <div class="blog-hero__row">
          <div>
            <h1 class="blog-hero__title" id="blogTitle">من عملنا الميداني</h1>
            <p class="blog-hero__lede">
              كيف نتحقق من الحالات، ولماذا نرفض بعضها، وما الذي أخفقنا فيه.
              هذه مدونة عمل، لا نشرة علاقات عامة.
            </p>
          </div>
          <p class="blog-hero__count"><b class="num" id="blogTotal">0</b> مقالاً</p>
        </div>

        <!-- المقال المميّز: يُملأ من الخادم، ويُخفى عند البحث أو اختيار تصنيف -->
        <div id="blogFeatured"></div>
      </div>
    </section>

    <!-- ══ التصنيفات والبحث، ثم المقالات ══ -->
    <section class="section blog-list" id="blogTop" aria-labelledby="blogListTitle">
      <div class="container">
        <div class="blog-bar">
          <div class="blog-bar__cats" role="group" aria-label="التصنيف" id="blogCats"></div>
          <label class="blog-bar__search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <span class="sr-only">ابحث في المقالات</span>
            <input type="search" id="blogSearch" placeholder="ابحث في المقالات" autocomplete="off">
          </label>
        </div>

        <h2 class="sr-only" id="blogListTitle">المقالات</h2>
        <p class="blog-status" id="blogStatus" role="status" aria-live="polite"></p>
        <div class="mag-grid" id="blogGrid" aria-busy="true">
          <div class="mag-skeleton"></div><div class="mag-skeleton"></div><div class="mag-skeleton"></div>
        </div>
        <nav class="pager" id="blogPager" aria-label="صفحات المقالات" hidden></nav>
      </div>
    </section>

    <!-- ══ النشرة: النموذج نفسه الذي يتولاه forms.js ══ -->
    <section class="blog-news" aria-labelledby="blogNewsTitle">
      <div class="container blog-news__inner">
        <div>
          <h2 id="blogNewsTitle">مقالٌ جديد كل أسبوعين، في بريدك</h2>
          <p>ما رأيناه في الميدان، وما تعلّمناه، وأين ذهب المال. بلا إعلانات، وإلغاء الاشتراك بنقرة.</p>
        </div>
        <form class="newsletter blog-news__form" data-validate-form data-action="newsletter" data-source="blog"
              data-success-message="تم اشتراكك، يصلك المقال القادم في بريدك" novalidate>
          <div class="field">
            <label class="sr-only" for="blogNewsEmail">بريدك الإلكتروني</label>
            <div class="field__control">
              <input class="input" type="email" id="blogNewsEmail" name="email" placeholder="بريدك الإلكتروني"
                     autocomplete="email" dir="ltr" data-validate="required|email" aria-describedby="err-blogNews">
            </div>
            <p class="field__error" id="err-blogNews" aria-live="polite"></p>
          </div>
          <button class="btn btn--cta" type="submit">اشترك</button>
        </form>
      </div>
    </section>
  </main>
@endsection

@push('after-footer')
  <button class="to-top" type="button" id="toTop" aria-label="العودة إلى أعلى الصفحة">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush
