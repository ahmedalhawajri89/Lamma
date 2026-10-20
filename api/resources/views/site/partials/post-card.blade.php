{{--
  بطاقة مقال: الصورة بشارة التصنيف، ثم العنوان والمقتطف والتاريخ وزمن القراءة،
  والعنوان رابطٌ ممتد فالبطاقة كلها تُضغط. توأمها في blog.js (magCard) بالأصناف
  نفسها: ما يتغيّر هنا يتغيّر هناك.
--}}
@php
  $minutes = (int) $post->read_minutes;
  $read = match (true) {
      $minutes <= 1 => __('دقيقة واحدة'),
      $minutes === 2 => __('دقيقتان'),
      $minutes <= 10 => __(':n دقائق', ['n' => $minutes]),
      default => __(':n دقيقة', ['n' => $minutes]),
  };
  // بلغة الصفحة إن تُرجم، وإلا العربي معلَّماً lang/dir داخل صفحةٍ بلغةٍ أخرى
  $lang = \App\Http\Resources\PostResource::contentLocale($post);
  $langAttr = $lang !== app()->getLocale()
      ? new \Illuminate\Support\HtmlString(' lang="'.e($lang).'" dir="'.e(\App\Support\Locale::dir($lang)).'"')
      : '';
@endphp
<article class="mag-card" data-reveal @isset($delay) data-reveal-delay="{{ $delay }}" @endisset>
  <div class="mag-card__media">
    @if ($post->cover_path)
      <img src="{{ asset('storage/'.$post->cover_path) }}" alt="" loading="lazy" width="480" height="300">
    @else
      <span class="mag-card__mark"></span>
    @endif
    @if ($post->category)
      <span class="mag-card__tag">{{ \App\Support\Locale::pick($post->category->name_ar, $post->category->name_en) }}</span>
    @endif
  </div>
  <div class="mag-card__body">
    <h3 class="mag-card__title"{{ $langAttr }}><a href="{{ lurl('/post?slug=') }}{{ urlencode($post->slug) }}">{{ $post->translated('title') }}</a></h3>
    <p class="mag-card__excerpt"{{ $langAttr }}>{{ $post->translated('excerpt') }}</p>
    <p class="mag-card__meta"><time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time> <span aria-hidden="true">·</span> <span>{{ __(':time قراءة', ['time' => $read]) }}</span></p>
  </div>
</article>
