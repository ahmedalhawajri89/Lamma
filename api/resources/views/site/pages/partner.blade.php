@extends('site.layouts.app')

{{--
  صفحة الجمعية الشريكة (PartnerPageController): من هي، ودليل توثيقها، وأرقامٌ
  من قاعدة البيانات، وأطفالها الذين ينتظرون (partner.js من الـ API).
--}}
@php
  $date = fn ($d) => $d ? $d->locale(app()->getLocale())->translatedFormat('F Y') : null;
  $place = collect([$partner['city'], $partner['country']])->filter()->implode(\App\Support\Locale::isDefault() ? '، ' : ', ');
@endphp

@section('title'){{ $partner['name'] }}@endsection
@section('description'){{ __('جمعية شريكة موثّقة في :place: تعرف أسر الأطفال وتزورها، وتتابع كل طفل في بيته ومدرسته.', ['place' => $partner['country']]) }}@endsection
@section('canonical', url(lurl('/partners/'.$partner['slug'])))

@push('styles')
  <link rel="stylesheet" href="/assets/css/kids.css">
  <link rel="stylesheet" href="/assets/css/partner.css">
@endpush

@section('content')
  <main id="main">

    <section class="pt-hero" aria-labelledby="ptTitle">
      <div class="container">
        <nav aria-label="{{ __('مسار التنقل') }}">
          <ol class="breadcrumb pt-hero__crumbs">
            <li class="breadcrumb-item"><a href="{{ lurl('/') }}">{{ __('الرئيسية') }}</a></li>
            <li class="breadcrumb-item"><a href="{{ lurl('/about') }}">{{ __('عن لمّة') }}</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $partner['name'] }}</li>
          </ol>
        </nav>

        <div class="row g-4 g-lg-5 align-items-start">
          <div class="col-lg-7">
            <p class="pt-hero__eyebrow"><i class="fa-solid fa-circle-check" aria-hidden="true"></i> {{ __('جمعية شريكة موثّقة') }}</p>
            <h1 class="pt-hero__title" id="ptTitle">{{ $partner['name'] }}</h1>
            @if ($place)
              <p class="pt-hero__place"><i class="fa-solid fa-location-dot" aria-hidden="true"></i> {{ $place }}</p>
            @endif
            @if ($partner['about'])
              <p class="pt-hero__lede">{{ $partner['about'] }}</p>
            @endif
          </div>

          {{-- دليل التوثيق: ما تحقّقنا منه، وبأي تاريخ --}}
          <aside class="col-lg-5">
            <div class="pt-proof">
              <h2 class="pt-proof__title">{{ __('ما تحقّقنا منه') }}</h2>
              <ul class="pt-proof__list">
                @if ($partner['verified_at'])
                  <li><i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                    <span><strong>{{ __('موثّقة منذ :date', ['date' => $date($partner['verified_at'])]) }}</strong><small>{{ __('راجعنا تسجيلها وزرنا مقرّها وأسرها.') }}</small></span></li>
                @endif
                @if ($partner['registration'])
                  <li><i class="fa-solid fa-file-signature" aria-hidden="true"></i>
                    <span><strong>{{ __('مسجّلة رسمياً') }} <span class="num" dir="ltr">{{ $partner['registration'] }}</span></strong>@if ($partner['authority'])<small>{{ $partner['authority'] }}</small>@endif</span></li>
                @endif
                @if ($partner['safeguarding_at'])
                  <li><i class="fa-solid fa-child-reaching" aria-hidden="true"></i>
                    <span><strong>{{ __('وقّعت سياسة حماية الطفل') }}</strong><small>{{ $date($partner['safeguarding_at']) }}</small></span></li>
                @endif
                <li><i class="fa-solid fa-house-chimney-user" aria-hidden="true"></i>
                  <span><strong>{{ __('أطفالها في أسرهم') }}</strong><small>{{ __('لا ننشر طفلاً في دار رعاية، ونؤكّد مع من يعيش كل طفل بزيارة كل عام.') }}</small></span></li>
              </ul>
            </div>
          </aside>
        </div>

        {{-- أرقامٌ من قاعدة البيانات، لا مكتوبةٌ في الصفحة --}}
        <dl class="pt-stats">
          <div><dt>{{ __('أطفال منشورون') }}</dt><dd class="num">{{ number_format($stats['children']) }}</dd></div>
          <div><dt>{{ __('مكفولون الآن') }}</dt><dd class="num">{{ number_format($stats['sponsored']) }}</dd></div>
          <div><dt>{{ __('أسر تتابعها') }}</dt><dd class="num">{{ number_format($stats['families']) }}</dd></div>
          <div><dt>{{ __('زيارات في 12 شهراً') }}</dt><dd class="num">{{ number_format($stats['visits']) }}</dd></div>
        </dl>
      </div>
    </section>

    <section class="kf-section pt-kids" aria-labelledby="ptKidsTitle" data-partner-section>
      <div class="container">
        <header class="kf-head">
          <h2 id="ptKidsTitle">{{ __('أطفالٌ تتابعهم الجمعية، ينتظرون من يقف معهم') }}</h2>
        </header>
        <div class="row g-4" id="partnerKids" data-association="{{ $partner['slug'] }}" aria-live="polite"></div>
      </div>
    </section>

  </main>
@endsection
