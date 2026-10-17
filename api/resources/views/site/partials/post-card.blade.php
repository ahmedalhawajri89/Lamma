{{--
  بطاقة مقال: الصورة بشارة التصنيف، ثم العنوان والمقتطف والتاريخ وزمن القراءة،
  والعنوان رابطٌ ممتد فالبطاقة كلها تُضغط. توأمها في blog.js (magCard) بالأصناف
  نفسها: ما يتغيّر هنا يتغيّر هناك.
--}}
@php
  $minutes = (int) $post->read_minutes;
  $read = match (true) {
      $minutes <= 1 => 'دقيقة واحدة',
      $minutes === 2 => 'دقيقتان',
      $minutes <= 10 => $minutes.' دقائق',
      default => $minutes.' دقيقة',
  };
@endphp
<article class="mag-card" data-reveal @isset($delay) data-reveal-delay="{{ $delay }}" @endisset>
  <div class="mag-card__media">
    @if ($post->cover_path)
      <img src="{{ asset('storage/'.$post->cover_path) }}" alt="" loading="lazy" width="480" height="300">
    @else
      <span class="mag-card__mark"></span>
    @endif
    @if ($post->category)
      <span class="mag-card__tag">{{ $post->category->name_ar }}</span>
    @endif
  </div>
  <div class="mag-card__body">
    <h3 class="mag-card__title"><a href="/post?slug={{ urlencode($post->slug) }}">{{ $post->title }}</a></h3>
    <p class="mag-card__excerpt">{{ $post->excerpt }}</p>
    <p class="mag-card__meta"><time datetime="{{ $post->published_at?->toDateString() }}">{{ $post->published_at?->translatedFormat('j F Y') }}</time> <span aria-hidden="true">·</span> <span>{{ $read }} قراءة</span></p>
  </div>
</article>
