@extends('site.layouts.legacy')

@section('title')الصفحة غير موجودة | كفالة@endsection
@section('description')الصفحة التي تبحث عنها غير موجودة.@endsection
@section('robots', 'noindex')

@section('content')
  <main id="main">
    <section class="notfound">
      <div class="container container--narrow">
        <p class="notfound__code">٤٠٤</p>
        <h1 style="font-size:var(--text-2xl);margin-block:var(--space-4)">
          لم نجد هذه الصفحة
        </h1>
        <p class="section-lede" style="margin-inline:auto;max-inline-size:34rem">
          ربما تغيّر الرابط أو حُذفت الصفحة. لكن الأطفال ما زالوا هنا،
          ويمكنك الوصول إليهم من الروابط أدناه.
        </p>

        <div class="cluster cluster--center" style="margin-block-start:var(--space-8)">
          <a class="btn btn--cta btn--lg" href="/orphans">
            تصفّح كل الأطفال
            <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
          </a>
          <a class="btn btn--outline btn--lg" href="/">الصفحة الرئيسية</a>
        </div>

        <div class="cluster cluster--center" style="margin-block-start:var(--space-10);gap:var(--space-5)">
          <a class="link-arrow" href="/programs">برامج الكفالة</a>
          <a class="link-arrow" href="/transparency">الشفافية</a>
          <a class="link-arrow" href="/faq">الأسئلة الشائعة</a>
          <a class="link-arrow" href="/contact">تواصل معنا</a>
        </div>
      </div>
    </section>
  </main>
@endsection
