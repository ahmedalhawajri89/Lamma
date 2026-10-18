{{--
  أزرار المشاركة: روابط المنصات نفسها، بلا مكتبة ولا سكربت من خارج الموقع
  (سياسة أمان المحتوى لا تسمح بغير 'self'). ومعها نسخ الرابط، وزرّ المشاركة
  الأصلي للجوال يظهره profile.js حيث يدعمه المتصفح.

  $url رابط الصفحة المطلق، و$title نصّ المشاركة.
--}}
@php
  $u = rawurlencode($url);
  $t = rawurlencode($title);
  $networks = [
      ['واتساب', 'fa-whatsapp', "https://wa.me/?text={$t}%20{$u}", 'whatsapp'],
      ['إكس', 'fa-x-twitter', "https://twitter.com/intent/tweet?text={$t}&url={$u}", 'x'],
      ['فيسبوك', 'fa-facebook-f', "https://www.facebook.com/sharer/sharer.php?u={$u}", 'facebook'],
      ['تلغرام', 'fa-telegram', "https://t.me/share/url?url={$u}&text={$t}", 'telegram'],
      ['لينكدإن', 'fa-linkedin-in', "https://www.linkedin.com/sharing/share-offsite/?url={$u}", 'linkedin'],
  ];
@endphp
<div class="share" role="group" aria-label="{{ $label ?? __('شارك') }}">
  @foreach ($networks as [$name, $icon, $href, $key])
    <a class="share__btn share__btn--{{ $key }}" href="{{ $href }}" target="_blank" rel="noopener noreferrer"
       aria-label="{{ __('شارك عبر :network (تفتح في نافذة جديدة)', ['network' => __($name)]) }}" title="{{ __($name) }}"><i class="fa-brands {{ $icon }}" aria-hidden="true"></i></a>
  @endforeach
  <a class="share__btn" href="mailto:?subject={{ $t }}&body={{ $u }}" aria-label="{{ __('شارك عبر البريد') }}" title="{{ __('البريد') }}"><i class="fa-regular fa-envelope" aria-hidden="true"></i></a>
  <button type="button" class="share__btn" data-copy-link="{{ $url }}" aria-label="{{ __('انسخ الرابط') }}" title="{{ __('انسخ الرابط') }}"><i class="fa-solid fa-link" aria-hidden="true"></i></button>
  <button type="button" class="share__btn share__btn--native" data-share data-share-title="{{ $title }}" data-share-url="{{ $url }}" aria-label="{{ __('مشاركة') }}" title="{{ __('مشاركة') }}" hidden><i class="fa-solid fa-share-nodes" aria-hidden="true"></i></button>
</div>
