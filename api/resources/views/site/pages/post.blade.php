@extends('site.layouts.app')

{{--
  صفحة المقال (PostPageController). $body نصٌّ نُظِّف بـ RichText قبل وصوله
  (الوسوم المسموحة وحدها)، فيُطبع بلا تهريب؛ وكل ما سواه مهرَّب.
--}}
@php
  if ($post) {
      $minutes = (int) $post->read_minutes;
      $read = match (true) {
          $minutes <= 1 => __('دقيقة واحدة'),
          $minutes === 2 => __('دقيقتان'),
          $minutes <= 10 => __(':n دقائق', ['n' => $minutes]),
          default => __(':n دقيقة', ['n' => $minutes]),
      };
      /*
       * النصّ بلغة الصفحة إن تُرجم، وإلا العربي: يُعلَّم lang/dir فيُقرأ
       * ويُرسم من اليمين داخل صفحةٍ إنجليزية، ومعه سطرٌ يقول إنه بالعربية.
       */
      $title = $post->translated('title');
      $excerpt = $post->translated('excerpt');
      $lang = \App\Http\Resources\PostResource::contentLocale($post);
      $foreign = $lang !== app()->getLocale();
      $langAttr = $foreign ? new \Illuminate\Support\HtmlString(' lang="'.e($lang).'" dir="'.e(\App\Support\Locale::dir($lang)).'"') : '';
      $category = $post->category ? \App\Support\Locale::pick($post->category->name_ar, $post->category->name_en) : null;
      $cover = $post->cover_path ? asset('storage/'.$post->cover_path) : null;
      $url = url(lurl('/post?slug='.urlencode($post->slug)));
      $schema = [
          '@context' => 'https://schema.org',
          '@type' => 'Article',
          'headline' => $title,
          'description' => $excerpt,
          'datePublished' => $post->published_at?->toIso8601String(),
          'dateModified' => $post->updated_at?->toIso8601String(),
          'mainEntityOfPage' => $url,
          'image' => $cover ? [$cover] : null,
          'author' => $post->author ? ['@type' => 'Person', 'name' => $post->author->name] : ['@type' => 'Organization', 'name' => $site['name']],
          'publisher' => ['@type' => 'Organization', 'name' => $site['name'], 'logo' => ['@type' => 'ImageObject', 'url' => asset('assets/img/logo.svg')]],
      ];
  }
@endphp

@if ($post)
  @section('title'){{ $title }}@endsection
  @section('description'){{ $excerpt }}@endsection
  @section('og_type', 'article')
  @section('og_title'){{ $title }}@endsection
  @if ($cover)
    @section('og_image', $cover)
  @endif
  @section('canonical', $url)
@else
  @section('title', __('لم نجد هذا المقال'))
  @section('robots', 'noindex')
@endif

@push('styles')
  <link rel="stylesheet" href="/assets/css/blog.css">
  @if ($post)
    <script type="application/ld+json">@json(array_filter($schema), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP)</script>
  @endif
@endpush

@section('content')
  <main id="main">
  @if (! $post)
    <section class="post-missing">
      <div class="container">
        <span class="post-missing__icon" aria-hidden="true"><i class="fa-regular fa-newspaper"></i></span>
        <h1>{{ __('لم نجد هذا المقال') }}</h1>
        <p>{{ __('ربما حُذف أو تغيّر رابطه. المقالات كلها في المدونة.') }}</p>
        <a class="btn btn-primary btn-lg" href="{{ lurl('/blog') }}">{{ __('العودة إلى المدونة') }}</a>
      </div>
    </section>
  @else
    {{-- شريط تقدّم القراءة: يتبع التمرير بـ CSS وحده (animation-timeline) --}}
    <div class="post-progress" aria-hidden="true"><span></span></div>

    <article class="post" aria-labelledby="postTitle">
      <header class="post-head">
        <div class="container">
          <nav aria-label="{{ __('مسار التنقل') }}">
            <ol class="breadcrumb post-crumbs">
              <li class="breadcrumb-item"><a href="{{ lurl('/') }}">{{ __('الرئيسية') }}</a></li>
              <li class="breadcrumb-item"><a href="{{ lurl('/blog') }}">{{ __('المدونة') }}</a></li>
              @if ($post->category)
                <li class="breadcrumb-item"><a href="{{ lurl('/blog?cat=') }}{{ urlencode($post->category->slug) }}">{{ $category }}</a></li>
              @endif
            </ol>
          </nav>
          <div class="post-head__text">
            @if ($post->category)
              <a class="post-tag" href="{{ lurl('/blog?cat=') }}{{ urlencode($post->category->slug) }}">{{ $category }}</a>
            @endif
            <h1 class="post-title" id="postTitle"{{ $langAttr }}>{{ $title }}</h1>
            @if ($excerpt)
              <p class="post-lede"{{ $langAttr }}>{{ $excerpt }}</p>
            @endif
            @if ($foreign)
              <p class="small text-muted mt-3 mb-0"><i class="fa-solid fa-language me-1" aria-hidden="true"></i> {{ __('هذا المقال متاحٌ بالعربية فقط حالياً.') }}</p>
            @endif
          </div>
        </div>
      </header>

      @if ($cover)
        <figure class="post-cover container">
          <img src="{{ $cover }}" alt="" width="1200" height="750" fetchpriority="high">
        </figure>
      @endif

      <div class="container">
        <div class="row g-4 g-xl-5">
          {{-- ══ النصّ: عمودٌ عريض مقروء ══ --}}
          <div class="col-lg-8">
            <div class="post-body"{{ $langAttr }}>{!! $body !!}</div>

            <footer class="post-end">
              <p>{{ __('هل وجدت المقال مفيداً؟ أرسله لمن يهمّه.') }}</p>
              @include('site.partials.share', ['url' => $url, 'title' => $title, 'label' => __('شارك المقال')])
            </footer>
          </div>

          {{-- ══ الجانب: يلتصق أثناء القراءة ══ --}}
          <aside class="col-lg-4" aria-label="{{ __('عن المقال') }}">
            <div class="post-aside">
              @if (count($toc) > 1)
                <nav class="post-card post-toc" aria-labelledby="tocTitle">
                  <h2 class="post-card__title" id="tocTitle">{{ __('في هذا المقال') }}</h2>
                  <ol{{ $langAttr }}>
                    @foreach ($toc as $item)
                      <li><a href="#{{ $item['id'] }}">{{ $item['title'] }}</a></li>
                    @endforeach
                  </ol>
                </nav>
              @endif

              <div class="post-card post-about">
                <div class="post-meta__by">
                  <span class="post-meta__avatar" aria-hidden="true">@include('site.partials.logo-mark', ['size' => 40])</span>
                  <span><b>{{ $post->author?->name ?? __('فريق :name', ['name' => $site['name']]) }}</b><span>{{ __('من فريق المنصة') }}</span></span>
                </div>
                <dl class="post-facts">
                  <div><dt><i class="fa-regular fa-calendar" aria-hidden="true"></i> {{ __('نُشر') }}</dt><dd><time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time></dd></div>
                  <div><dt><i class="fa-regular fa-clock" aria-hidden="true"></i> {{ __('القراءة') }}</dt><dd>{{ $read }}</dd></div>
                  @if ($post->category)
                    <div><dt><i class="fa-regular fa-folder" aria-hidden="true"></i> {{ __('التصنيف') }}</dt><dd><a href="{{ lurl('/blog?cat=') }}{{ urlencode($post->category->slug) }}">{{ $category }}</a></dd></div>
                  @endif
                </dl>
                <div>
                  <p class="post-card__title">{{ __('شارك المقال') }}</p>
                  @include('site.partials.share', ['url' => $url, 'title' => $title, 'label' => __('شارك المقال')])
                </div>
              </div>

              {{-- الدعوة بجانب القراءة لا بعدها وحدها --}}
              <div class="post-card post-invite">
                <span class="post-invite__icon" aria-hidden="true"><i class="fa-solid fa-hand-holding-heart"></i></span>
                <h2>{{ __('كل ما تقرؤه هنا يحدث لطفلٍ ينتظر كافلاً') }}</h2>
                <p>{{ __('ملفات موثّقة ومزارة ميدانياً، وتقرير مصوّر كل ثلاثة أشهر.') }}</p>
                <a class="btn btn-cta w-100" href="{{ lurl('/orphans') }}">{{ __('اكفل يتيماً') }} <i class="fa-solid fa-arrow-left ms-1" aria-hidden="true"></i></a>
              </div>
            </div>
          </aside>
        </div>
      </div>
    </article>

    @if ($related->isNotEmpty())
      <section class="post-related" aria-labelledby="relatedTitle">
        <div class="container">
          <div class="post-related__head">
            <h2 class="kf-title" id="relatedTitle">{{ __('اقرأ أيضاً') }}</h2>
            <a class="link-arrow" href="{{ lurl('/blog') }}">{{ __('كل المقالات') }} <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
          </div>
          <div class="mag-grid">
            @foreach ($related as $i => $r)
              @include('site.partials.post-card', ['post' => $r, 'delay' => $i * 80])
            @endforeach
          </div>
        </div>
      </section>
    @endif
  @endif
  </main>
@endsection

@push('after-footer')
  <button class="to-top" type="button" id="toTop" aria-label="{{ __('العودة إلى أعلى الصفحة') }}">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush
