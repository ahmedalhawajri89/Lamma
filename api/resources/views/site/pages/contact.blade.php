@extends('site.layouts.app')

{{--
  التواصل (ContactPageController): طرقٌ سريعة أولاً (واتساب واتصال وبريد)، ثم
  أكثر الأسئلة شيوعاً، ثم النموذج. النموذج يرسله forms.js إلى /public/contact
  (data-action="contact")، وفيه حقل مصيدة للروبوتات، والخادم يحدّه بست رسائل
  في الدقيقة.
--}}
@php
  $wa = 'https://wa.me/'.ltrim((string) $contact['phoneHref'], '+');
  $topics = [
      'general' => 'استفسار عام',
      'sponsorship' => 'سؤال عن الكفالة',
      'finance' => 'استفسار مالي',
      'report' => 'مشكلة في تقرير',
      'partner' => 'شراكة أو تطوّع',
      'complaint' => 'شكوى',
  ];
@endphp

@section('title')تواصل معنا@endsection
@section('description')اسألنا عن أي بند في أي تقرير. نلتزم بالرد خلال 72 ساعة.@endsection
@section('canonical', url(lurl('/contact')))

@push('styles')
  <link rel="stylesheet" href="/assets/css/contact.css">
@endpush

@section('content')
  <main id="main">

    {{-- ══ الصدر وطرق التواصل السريعة ══ --}}
    <section class="ct-hero" aria-labelledby="ctTitle">
      <div class="container">
        <nav aria-label="مسار التنقل">
          <ol class="breadcrumb ct-crumbs">
            <li class="breadcrumb-item"><a href="{{ lurl('/') }}">الرئيسية</a></li>
            <li class="breadcrumb-item active" aria-current="page">تواصل معنا</li>
          </ol>
        </nav>
        <h1 class="ct-title" id="ctTitle">اسأل عن أي شيء،<br><em>حتى الأسئلة الصعبة عن المال.</em></h1>
        <p class="ct-lede">نردّ على كل رسالة خلال 72 ساعة، وننشر الجواب إن كان يهمّ غيرك.</p>

        <ul class="ct-quick">
          <li>
            <a class="ct-quick__item ct-quick__item--wa" href="{{ $wa }}" target="_blank" rel="noopener noreferrer">
              <span class="ct-quick__icon" aria-hidden="true"><i class="fa-brands fa-whatsapp"></i></span>
              <span><strong>واتساب</strong><span>أسرع ردّ في ساعات العمل</span></span>
              <i class="fa-solid fa-arrow-left ct-quick__go" aria-hidden="true"></i>
            </a>
          </li>
          <li>
            <a class="ct-quick__item" href="tel:{{ $contact['phoneHref'] }}">
              <span class="ct-quick__icon" aria-hidden="true"><i class="fa-solid fa-phone"></i></span>
              <span><strong>اتصال</strong><span dir="ltr" class="num">{{ $contact['phone'] }}</span></span>
              <i class="fa-solid fa-arrow-left ct-quick__go" aria-hidden="true"></i>
            </a>
          </li>
          <li>
            <a class="ct-quick__item" href="mailto:{{ $contact['email'] }}">
              <span class="ct-quick__icon" aria-hidden="true"><i class="fa-regular fa-envelope"></i></span>
              <span><strong>البريد</strong><span>{{ $contact['email'] }}</span></span>
              <i class="fa-solid fa-arrow-left ct-quick__go" aria-hidden="true"></i>
            </a>
          </li>
        </ul>
      </div>
    </section>

    <section class="ct-main">
      <div class="container">
        <div class="row g-4 g-xl-5">

          {{-- ══ النموذج ══ --}}
          <div class="col-lg-7">
            <div class="ct-form-card">
              <h2 class="ct-h2">أرسل لنا رسالة</h2>
              <p class="ct-form-card__lede">يصلك ردّنا على بريدك خلال 72 ساعة.</p>

              <form id="contactForm" data-validate-form data-action="contact"
                    data-success-message="وصلتنا رسالتك، سنرد خلال 72 ساعة بإذن الله" novalidate>

                <fieldset class="ct-topics">
                  <legend class="form-label">موضوع الرسالة</legend>
                  <div class="ct-topics__list">
                    @foreach ($topics as $value => $label)
                      <label class="ct-topic">
                        <input type="radio" name="topic" value="{{ $value }}" @checked($loop->first)>
                        <span>{{ $label }}</span>
                      </label>
                    @endforeach
                  </div>
                </fieldset>

                <div class="row g-3">
                  <div class="col-sm-6 field">
                    <label class="form-label" for="contactName">الاسم</label>
                    <input class="form-control form-control-lg" type="text" id="contactName" name="name"
                           autocomplete="name" data-validate="required|minlength:3" aria-describedby="err-contactName">
                    <p class="field__error" id="err-contactName" aria-live="polite"></p>
                  </div>
                  <div class="col-sm-6 field">
                    <label class="form-label" for="contactEmail">البريد الإلكتروني</label>
                    <input class="form-control form-control-lg" type="email" id="contactEmail" name="email" dir="ltr"
                           autocomplete="email" inputmode="email" data-validate="required|email" aria-describedby="err-contactEmail">
                    <p class="field__error" id="err-contactEmail" aria-live="polite"></p>
                  </div>
                  <div class="col-12 field">
                    <label class="form-label" for="contactMessage">رسالتك</label>
                    <textarea class="form-control" id="contactMessage" name="message" rows="6" maxlength="5000"
                              placeholder="اكتب سؤالك بالتفصيل، وإن كان عن تقرير فاذكر شهره أو رقم الكفالة"
                              data-validate="required|minlength:15" aria-describedby="hint-contactMessage err-contactMessage"></textarea>
                    <p class="form-text ct-count" id="hint-contactMessage" aria-live="polite"><span class="num" data-count>0</span> حرفاً، والحدّ الأدنى 15</p>
                    <p class="field__error" id="err-contactMessage" aria-live="polite"></p>
                  </div>
                </div>

                {{-- مصيدة: مخفية عن البشر وعن قارئات الشاشة، والروبوت يملؤها --}}
                <div class="visually-hidden" aria-hidden="true">
                  <label for="contactWebsite">لا تملأ هذا الحقل</label>
                  <input type="text" id="contactWebsite" name="website" tabindex="-1" autocomplete="off">
                </div>

                <div class="field ct-consent">
                  <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="contactConsent" name="consent" required
                           data-validate="required" data-required-message="يلزم الموافقة لنتمكّن من الرد عليك" aria-describedby="err-consent">
                    <label class="form-check-label" for="contactConsent">أوافق على استخدام بياناتي للرد على استفساري فقط، ولن تُشارك مع أي جهة.</label>
                  </div>
                  <p class="field__error" id="err-consent" aria-live="polite"></p>
                </div>

                <button class="btn btn-primary btn-lg w-100" type="submit">إرسال الرسالة <i class="fa-regular fa-paper-plane ms-1" aria-hidden="true"></i></button>
              </form>
            </div>
          </div>

          {{-- ══ الجانب: أسئلة شائعة وبيانات ══ --}}
          <aside class="col-lg-5">
            <div class="ct-aside">
              @if ($faqs->isNotEmpty())
                <section class="ct-card" aria-labelledby="ctFaqTitle">
                  <h2 class="ct-card__title" id="ctFaqTitle">لعلّ جوابك هنا</h2>
                  <div class="ct-faqs">
                    @foreach ($faqs as $f)
                      <details class="ct-faq">
                        <summary>{{ $f['q'] }}</summary>
                        <p>{{ $f['a'] }}</p>
                      </details>
                    @endforeach
                  </div>
                  <a class="link-arrow" href="{{ lurl('/faq') }}">كل الأسئلة الشائعة <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
                </section>
              @endif

              <section class="ct-card" aria-labelledby="ctInfoTitle">
                <h2 class="ct-card__title" id="ctInfoTitle">بيانات التواصل</h2>
                <ul class="ct-info">
                  <li><i class="fa-solid fa-location-dot" aria-hidden="true"></i><span><b>المقر الرئيسي</b>{{ $contact['address'] }}</span></li>
                  <li><i class="fa-regular fa-clock" aria-hidden="true"></i><span><b>ساعات العمل</b>من الأحد إلى الخميس، 9 صباحاً حتى 5 مساءً</span></li>
                  <li><i class="fa-solid fa-scale-balanced" aria-hidden="true"></i><span><b>سؤالٌ مالي؟</b>اذكر البند أو الشهر في رسالتك، وراجع قبلها <a href="{{ lurl('/transparency') }}">صفحة الشفافية</a>: الأرقام فيها تتحدّث مع كل صرف.</span></li>
                </ul>
              </section>
            </div>
          </aside>
        </div>
      </div>
    </section>

  </main>
@endsection

@push('after-footer')
  <button class="to-top" type="button" id="toTop" aria-label="العودة إلى أعلى الصفحة">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush
