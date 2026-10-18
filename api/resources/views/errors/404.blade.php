@extends('site.layouts.app')

{{--
  الصفحة غير موجودة. بترويسة الموقع وفوتره، فلا يجد الزائر نفسه في طريقٍ
  مسدود: إجراءان واضحان (الأطفال، والرئيسية)، ثم أكثر ما يُقصد من الموقع.
  الرسم من مجموعة CC0 نفسها (assets/img/kids)، لا صورة طفلٍ حقيقي.
--}}
@section('title')الصفحة غير موجودة@endsection
@section('description')الصفحة التي تبحث عنها غير موجودة.@endsection
@section('robots', 'noindex')

@push('styles')
  <link rel="stylesheet" href="/assets/css/notfound.css">
@endpush

@php
  $links = [
      ['/programs', 'fa-hand-holding-heart', 'برامج الكفالة', 'من الشاملة إلى التعليمية، وما يغطيه كل برنامج.'],
      ['/transparency', 'fa-scale-balanced', 'الشفافية', 'أين يذهب كل دولار، بأرقام هذا الربع.'],
      ['/faq', 'fa-circle-question', 'الأسئلة الشائعة', 'الزكاة، والتقارير، والإيقاف المؤقت.'],
      ['/contact', 'fa-envelope', 'تواصل معنا', 'نردّ خلال يوم عمل واحد.'],
  ];
@endphp

@section('content')
  <main id="main" class="nf">
    <div class="container">
      <section class="nf-hero" aria-labelledby="nfTitle">
        <div class="nf-art" aria-hidden="true">
          <span class="nf-art__code">404</span>
          <span class="nf-art__circle"><img src="/assets/img/kids/girl-03.svg" alt="" width="220" height="220"></span>
          <span class="nf-art__chip"><i class="fa-solid fa-question"></i></span>
        </div>

        <div class="nf-text">
          <p class="kf-eyebrow">الصفحة غير موجودة</p>
          <h1 class="nf-title" id="nfTitle">لم نجد هذه الصفحة</h1>
          <p class="nf-lede">ربما تغيّر الرابط أو حُذفت الصفحة. لكنّ الأطفال ما زالوا هنا، وطريقك إليهم من هنا.</p>
          <div class="nf-actions">
            <a class="btn btn-cta btn-lg" href="/orphans">تصفّح الأطفال <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
            <a class="btn btn-outline-primary btn-lg" href="/">الصفحة الرئيسية</a>
          </div>
        </div>
      </section>

      <nav class="nf-more" aria-labelledby="nfMore">
        <h2 class="nf-more__title" id="nfMore">ربما تبحث عن</h2>
        <ul class="nf-links" role="list">
          @foreach ($links as [$href, $icon, $title, $text])
            <li>
              <a class="nf-link" href="{{ $href }}">
                <span class="nf-link__icon" aria-hidden="true"><i class="fa-solid {{ $icon }}"></i></span>
                <span class="nf-link__text"><b>{{ $title }}</b><small>{{ $text }}</small></span>
                <i class="fa-solid fa-arrow-left nf-link__arrow" aria-hidden="true"></i>
              </a>
            </li>
          @endforeach
        </ul>
      </nav>
    </div>
  </main>
@endsection
