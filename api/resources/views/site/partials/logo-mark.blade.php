{{-- شعار لمّة داخل الصفحة لا <img>: ألوانه من tokens.css (--logo-leaf و--logo-gold)
     فيتبع الوضع الداكن. النسخة الملفّية assets/img/logo.svg للّوحة وما لا يقرأ CSS. --}}
<svg class="kf-mark {{ $class ?? '' }}" viewBox="0 0 48 48" width="{{ $size ?? 46 }}" height="{{ $size ?? 46 }}" aria-hidden="true" focusable="false">
  <path d="M22.60 45.00C20.38 33.76 13.92 25.39 3.60 20.40C2.35 34.32 8.81 42.69 22.60 45.00Z" fill="var(--logo-leaf, #064e3b)"/>
  <path d="M25.00 45.00C41.35 41.40 48.22 31.07 45.20 14.60C33.32 21.17 26.45 31.50 25.00 45.00Z" fill="var(--logo-leaf, #064e3b)"/>
  <path d="M23.60 34.50C34.76 25.58 36.73 14.66 29.40 2.40C21.32 11.88 19.35 22.79 23.60 34.50Z" fill="var(--logo-gold, #d5a85c)"/>
</svg>
