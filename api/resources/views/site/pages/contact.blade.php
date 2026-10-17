@extends('site.layouts.legacy')

@section('title')تواصل معنا | كفالة@endsection
@section('description')اسألنا عن أي بند في أي تقرير. نلتزم بالرد خلال ٧٢ ساعة.@endsection
@section('canonical', url('/contact'))

@section('content')
  <main id="main">

    <section class="page-hero">
      <div class="container">
        <div class="page-hero__inner">
          <nav aria-label="مسار التنقل">
            <ol class="breadcrumb">
              <li><a href="/">الرئيسية</a></li>
              <li><span aria-current="page">تواصل معنا</span></li>
            </ol>
          </nav>
          <h1 class="page-hero__title">اسأل عن أي شيء</h1>
          <p class="page-hero__lede">
            بما في ذلك الأسئلة الصعبة عن المال والمصاريف. نلتزم بالرد خلال ٧٢ ساعة.
          </p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container">
        <div class="split">

          <!-- بيانات التواصل -->
          <div class="contact-info">
            <h2>بيانات التواصل</h2>

            <ul class="contact-list" role="list">
              <li>
                <i class="fa-solid fa-phone" aria-hidden="true"></i>
                <span>الهاتف وواتساب</span>
                <strong><a href="tel:+970599000000" dir="ltr">+970 599 000 000</a></strong>
              </li>
              <li>
                <i class="fa-regular fa-envelope" aria-hidden="true"></i>
                <span>البريد الإلكتروني</span>
                <strong><a href="mailto:info@kafala.org">info@kafala.org</a></strong>
              </li>
              <li>
                <i class="fa-solid fa-location-dot" aria-hidden="true"></i>
                <span>المقر الرئيسي</span>
                <strong>غزة، فلسطين</strong>
              </li>
              <li>
                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                <span>ساعات العمل</span>
                <strong>من الأحد إلى الخميس، ٩ صباحاً حتى ٥ مساءً</strong>
              </li>
            </ul>

            <div class="contact-note">
              <h3>للاستفسارات المالية</h3>
              <p>
                إن كان سؤالك عن بند في الميزانية أو تقرير مالي، أرسله على
                <a href="mailto:finance@kafala.org">finance@kafala.org</a>
                ليصل مباشرة إلى الإدارة المالية دون وسيط.
              </p>
            </div>
          </div>

          <!-- النموذج -->
          <div class="form-panel">
            <h2>أرسل لنا رسالة</h2>

            <form data-validate-form data-action="contact"
                  data-success-message="وصلتنا رسالتك، سنرد خلال ٧٢ ساعة بإذن الله" novalidate
                  style="margin-block-start:var(--space-6)">

              <div class="field" style="margin-block-end:var(--space-4)">
                <label class="field__label" for="contactName">الاسم <span class="req" aria-hidden="true">*</span></label>
                <div class="field__control">
                  <i class="fa-regular fa-user field__icon" aria-hidden="true"></i>
                  <input class="input" type="text" id="contactName" name="name"
                         autocomplete="name" data-validate="required|minlength:3"
                         aria-describedby="err-contactName">
                </div>
                <p class="field__error" id="err-contactName" aria-live="polite"></p>
              </div>

              <div class="field" style="margin-block-end:var(--space-4)">
                <label class="field__label" for="contactEmail">البريد الإلكتروني <span class="req" aria-hidden="true">*</span></label>
                <div class="field__control">
                  <i class="fa-regular fa-envelope field__icon" aria-hidden="true"></i>
                  <input class="input" type="email" id="contactEmail" name="email"
                         autocomplete="email" inputmode="email" data-validate="required|email"
                         aria-describedby="err-contactEmail">
                </div>
                <p class="field__error" id="err-contactEmail" aria-live="polite"></p>
              </div>

              <div class="field" style="margin-block-end:var(--space-4)">
                <label class="field__label" for="contactTopic">موضوع الرسالة</label>
                <select class="select" id="contactTopic" name="topic">
                  <option value="general">استفسار عام</option>
                  <option value="sponsorship">سؤال عن الكفالة</option>
                  <option value="finance">استفسار مالي أو عن الميزانية</option>
                  <option value="report">مشكلة في تقرير</option>
                  <option value="partner">شراكة أو تطوّع</option>
                  <option value="complaint">شكوى</option>
                </select>
              </div>

              <div class="field" style="margin-block-end:var(--space-6)">
                <label class="field__label" for="contactMessage">الرسالة <span class="req" aria-hidden="true">*</span></label>
                <textarea class="textarea" id="contactMessage" name="message" rows="6"
                          placeholder="اكتب سؤالك بالتفصيل…"
                          data-validate="required|minlength:15"
                          aria-describedby="err-contactMessage"></textarea>
                <p class="field__error" id="err-contactMessage" aria-live="polite"></p>
              </div>

              <!-- مصيدة: مخفية عن البشر وعن قارئات الشاشة، والروبوت يملؤها. -->
              <div class="sr-only" aria-hidden="true">
                <label for="contactWebsite">لا تملأ هذا الحقل</label>
                <input type="text" id="contactWebsite" name="website"
                       tabindex="-1" autocomplete="off">
              </div>

              <div class="field" style="margin-block-end:var(--space-6)">
                <label class="checkbox">
                  <input type="checkbox" name="consent" required
                         data-validate="required" data-required-message="يلزم الموافقة لنتمكّن من الرد عليك"
                         aria-describedby="err-consent">
                  <span>أوافق على استخدام بياناتي للرد على استفساري فقط، ولن تُشارك مع أي جهة أخرى.</span>
                </label>
                <p class="field__error" id="err-consent" aria-live="polite"></p>
              </div>

              <button class="btn btn--primary btn--lg btn--block" type="submit">
                إرسال الرسالة
                <i class="fa-regular fa-paper-plane btn__icon" aria-hidden="true"></i>
              </button>
            </form>
          </div>

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
