@extends('site.layouts.legacy')

@section('title')مقال | كفالة@endsection
@section('description')مقالات من فريق كفالة عن عملهم الميداني.@endsection

@section('content')
  <main id="main">
    <!--
      يُملأ من /assets/js/modules/features.js عبر initPostDetail()،
      قراءةً من /api/v1/public/posts/{slug} حسب ?slug= في العنوان.
    -->
    <article id="postDetail" aria-busy="true">
      <section class="section section--tight">
        <div class="container container--narrow">
          <p class="muted">جارٍ تحميل المقال…</p>
        </div>
      </section>
    </article>
  </main>
@endsection

@push('after-footer')
  <button class="to-top" type="button" id="toTop" aria-label="العودة إلى أعلى الصفحة">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush
