@extends('site.layouts.app')

{{--
  المدونة: blog.js يملأ المميّز والتصنيفات والشبكة والصفحات من الـ API،
  والتصنيف والبحث والصفحة في الرابط (?cat=&q=&page=).
  شريط النشرة الذي كان هنا حُذف: الفوتر تحته يحمل النموذج نفسه.
--}}
@section('title', __('المدونة'))
@section('description', __('نكتب عن آلية عملنا وقراراتنا وأخطائنا: الشفافية، سياسات الحماية، قصص الأطفال، وتقارير الحملات.'))
@section('canonical', url(lurl('/blog')))

@push('styles')
  <link rel="stylesheet" href="/assets/css/blog.css">
@endpush

@section('content')
  <main id="main">

    {{-- ══ الرأس: العنوان والبحث، ثم المقال المميّز ══ --}}
    <section class="blog-hero" aria-labelledby="blogTitle">
      <div class="container">
        <nav aria-label="{{ __('مسار التنقل') }}">
          <ol class="breadcrumb post-crumbs">
            <li class="breadcrumb-item"><a href="{{ lurl('/') }}">{{ __('الرئيسية') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ __('المدونة') }}</li>
          </ol>
        </nav>
        <div class="blog-hero__row">
          <div>
            <h1 class="blog-hero__title" id="blogTitle">{{ __('من عملنا الميداني') }}</h1>
            <p class="blog-hero__lede">{{ __('كيف نتحقق من الحالات، ولماذا نرفض بعضها، وما الذي أخفقنا فيه. مدونة عمل، لا نشرة علاقات عامة.') }}</p>
          </div>
          <label class="blog-search">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <span class="visually-hidden">{{ __('ابحث في المقالات') }}</span>
            <input type="search" id="blogSearch" placeholder="{{ __('ابحث في المقالات') }}" autocomplete="off" enterkeyhint="search">
          </label>
        </div>

        {{-- المميّز: يُملأ من الخادم، ويُخفى عند البحث أو اختيار تصنيف --}}
        <div id="blogFeatured"></div>
      </div>
    </section>

    {{-- ══ التصنيفات، ثم المقالات ══ --}}
    <section class="blog-list" id="blogTop" aria-labelledby="blogListTitle">
      <div class="container">
        <div class="blog-bar">
          <div class="blog-cats" role="group" aria-label="{{ __('التصنيف') }}" id="blogCats"></div>
          <p class="blog-count"><b class="num" id="blogTotal">0</b> {{ __('مقالاً') }}</p>
        </div>

        <h2 class="visually-hidden" id="blogListTitle">{{ __('المقالات') }}</h2>
        <p class="blog-status" id="blogStatus" role="status" aria-live="polite"></p>
        <div class="mag-grid" id="blogGrid" aria-busy="true">
          <div class="mag-skeleton"></div><div class="mag-skeleton"></div><div class="mag-skeleton"></div>
        </div>
        <nav class="blog-pager" id="blogPager" aria-label="{{ __('صفحات المقالات') }}" hidden></nav>
      </div>
    </section>

  </main>
@endsection

@push('after-footer')
  <button class="to-top" type="button" id="toTop" aria-label="{{ __('العودة إلى أعلى الصفحة') }}">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush
