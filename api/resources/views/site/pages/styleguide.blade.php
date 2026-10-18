@extends('site.layouts.app')

@section('title')دليل الواجهة@endsection
@section('robots', 'noindex, nofollow')

@push('styles')
  <style>
    .sg-section { padding-block: 3.5rem; border-block-end: 1px solid var(--color-border); }
    .sg-section:last-of-type { border-block-end: 0; }
    .sg-label { font-family: var(--font-display); font-size: .8rem; font-weight: 700; color: var(--color-brand); margin-block-end: .35rem; }
    .sg-swatch { border: 1px solid var(--color-border); border-radius: var(--radius-md); overflow: hidden; background: var(--color-surface); block-size: 100%; }
    .sg-swatch__chip { block-size: 5rem; display: grid; place-items: center; font-family: var(--font-display); font-weight: 700; }
    .sg-swatch__meta { padding: .75rem 1rem; font-size: .85rem; }
    .sg-swatch__meta code { color: var(--color-text-muted); direction: ltr; unicode-bidi: isolate; }
    .sg-type > * { margin-block-end: .6rem; }
    .sg-kid img { aspect-ratio: 4 / 3; object-fit: cover; }
  </style>
@endpush

@section('content')
  <main id="main" class="container">

    <header class="sg-section" data-reveal>
      <p class="sg-label">دليل الواجهة</p>
      <h1 class="display-5">هوية كفالة على Bootstrap 5.3</h1>
      <p class="lead mb-0" style="max-inline-size: 42rem">
        معاينة لكل المكوّنات بألوان الموقع وخطوطه قبل نقل الصفحات إليها. جرّب الوضع الليلي من الزر في الأعلى،
        وتنقّل بلوحة المفاتيح لترى حلقات التركيز.
      </p>
    </header>

    <!-- ══ الألوان ══ -->
    <section class="sg-section" aria-labelledby="sgColors">
      <p class="sg-label">١</p>
      <h2 id="sgColors" class="h3 mb-4">الألوان ونسب التباين</h2>
      <div class="row g-3">
        <div class="col-6 col-md-4 col-lg-3" data-reveal>
          <div class="sg-swatch"><div class="sg-swatch__chip" style="background:var(--color-brand);color:var(--color-brand-contrast)">نص 7.8</div>
          <div class="sg-swatch__meta"><strong>الأساسي</strong><br><code>#0f5c5c</code></div></div>
        </div>
        <div class="col-6 col-md-4 col-lg-3" data-reveal data-reveal-delay="60">
          <div class="sg-swatch"><div class="sg-swatch__chip" style="background:var(--color-cta);color:var(--color-cta-contrast)">نص 6.8</div>
          <div class="sg-swatch__meta"><strong>الإجراء الرئيسي</strong><br><code>#e0a63b</code></div></div>
        </div>
        <div class="col-6 col-md-4 col-lg-3" data-reveal data-reveal-delay="120">
          <div class="sg-swatch"><div class="sg-swatch__chip" style="background:var(--color-canvas);color:var(--color-text)">نص 13.5</div>
          <div class="sg-swatch__meta"><strong>الخلفية</strong><br><code>#f7f5f0</code></div></div>
        </div>
        <div class="col-6 col-md-4 col-lg-3" data-reveal data-reveal-delay="180">
          <div class="sg-swatch"><div class="sg-swatch__chip" style="background:var(--color-ink);color:#fff">الأقسام الداكنة</div>
          <div class="sg-swatch__meta"><strong>الحبر</strong><br><code>#093a3a</code></div></div>
        </div>
        <div class="col-6 col-md-4 col-lg-3" data-reveal>
          <div class="sg-swatch"><div class="sg-swatch__chip" style="background:var(--color-surface);color:var(--color-text-muted)">مكتوم 6.5</div>
          <div class="sg-swatch__meta"><strong>النص المكتوم</strong><br><code>#4e5b5f</code></div></div>
        </div>
        <div class="col-6 col-md-4 col-lg-3" data-reveal data-reveal-delay="60">
          <div class="sg-swatch"><div class="sg-swatch__chip" style="background:var(--color-success-soft);color:var(--color-success)">نجاح 5.0</div>
          <div class="sg-swatch__meta"><strong>النجاح</strong><br><code>#1f8048</code></div></div>
        </div>
        <div class="col-6 col-md-4 col-lg-3" data-reveal data-reveal-delay="120">
          <div class="sg-swatch"><div class="sg-swatch__chip" style="background:var(--color-urgent-soft);color:var(--color-urgent)">عاجل 6.3</div>
          <div class="sg-swatch__meta"><strong>العاجل والخطأ</strong><br><code>#be123c</code></div></div>
        </div>
        <div class="col-6 col-md-4 col-lg-3" data-reveal data-reveal-delay="180">
          <div class="sg-swatch"><div class="sg-swatch__chip" style="background:var(--color-surface);color:var(--color-text);box-shadow:inset 0 0 0 2px var(--color-border-field)">حدّ 3.5</div>
          <div class="sg-swatch__meta"><strong>حدّ الحقول</strong><br><code>#858a84</code></div></div>
        </div>
      </div>
      <p class="form-text mt-3 mb-0">النسب في الوضع الفاتح. كلها فوق 4.5 للنص و3 لحدود الحقول، وفي الوضع الليلي أعلى منها.</p>
    </section>

    <!-- ══ الخطوط ══ -->
    <section class="sg-section" aria-labelledby="sgType">
      <p class="sg-label">٢</p>
      <h2 id="sgType" class="h3 mb-4">الخطوط</h2>
      <div class="row g-4 g-lg-5">
        <div class="col-lg-6 sg-type" data-reveal>
          <p class="display-6 mb-1">اكفل يتيماً، وتابع أثرك بنفسك.</p>
          <p class="h2">عنوان قسم: أين تذهب كل مئة دولار</p>
          <p class="h4">عنوان فرعي: تقرير مصوّر كل ثلاثة أشهر</p>
          <p class="form-text mb-0">العناوين بخط Readex Pro، وزن 700.</p>
        </div>
        <div class="col-lg-6 sg-type" data-reveal data-reveal-delay="100">
          <p class="lead">نص تمهيدي: زار مشرفٌ ميداني بيت كل طفل هنا قبل نشر ملفه. تعرّف عليه باسمه وحلمه، ثم قرّر.</p>
          <p>نص الجسم بخط IBM Plex Sans Arabic بحجم 16px وتباعد أسطر 1.7، وهو أريح للقراءة العربية الطويلة. الروابط <a href="#">تأخذ اللون الأساسي</a> وخطاً تحتها.</p>
          <p class="text-body-secondary small mb-0">نص صغير مكتوم للملاحظات والتواريخ: ١٢ يوليو ٢٠٢٦ · دقيقتان قراءة</p>
        </div>
      </div>
    </section>

    <!-- ══ الأزرار ══ -->
    <section class="sg-section" aria-labelledby="sgButtons">
      <p class="sg-label">٣</p>
      <h2 id="sgButtons" class="h3 mb-4">الأزرار</h2>
      <div class="d-flex flex-wrap align-items-center gap-3 mb-3" data-reveal>
        <button class="btn btn-cta btn-lg" type="button">اكفل يتيماً <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></button>
        <button class="btn btn-primary" type="button">احفظ التغييرات</button>
        <button class="btn btn-outline-primary" type="button">قارن البرامج</button>
        <button class="btn btn-soft" type="button">تواصل معنا</button>
        <button class="btn btn-link" type="button">كيف نضمن وصولها؟</button>
      </div>
      <div class="d-flex flex-wrap align-items-center gap-3" data-reveal data-reveal-delay="80">
        <button class="btn btn-primary btn-sm" type="button">صغير</button>
        <button class="btn btn-cta" type="button" disabled>معطّل</button>
        <button class="btn btn-primary" type="button" disabled>
          <span class="spinner-border spinner-border-sm" aria-hidden="true"></span> جارٍ الحفظ…
        </button>
      </div>
      <p class="form-text mt-3 mb-0">الذهبي للإجراء الرئيسي وحده، مرة في كل شاشة. كل الأزرار بارتفاع 44px على الأقل.</p>
    </section>

    <!-- ══ النماذج ══ -->
    <section class="sg-section" aria-labelledby="sgForms">
      <p class="sg-label">٤</p>
      <h2 id="sgForms" class="h3 mb-4">النماذج</h2>
      <form class="row g-4" novalidate data-reveal>
        <div class="col-md-6">
          <label class="form-label" for="sgName">الاسم الكامل</label>
          <input class="form-control" id="sgName" placeholder="مثلاً: سارة أحمد" autocomplete="name">
          <div class="form-text">كما يظهر في إيصالك.</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="sgEmail">البريد الإلكتروني</label>
          <input class="form-control is-invalid" id="sgEmail" type="email" value="sara@" aria-describedby="sgEmailErr" aria-invalid="true">
          <div class="invalid-feedback" id="sgEmailErr">اكتب بريداً صحيحاً، مثل name@example.com</div>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="sgProgram">البرنامج</label>
          <select class="form-select" id="sgProgram">
            <option>الكفالة الشاملة</option>
            <option>الكفالة التعليمية</option>
            <option>الكفالة الصحية</option>
          </select>
        </div>
        <div class="col-md-6">
          <label class="form-label" for="sgAmount">المبلغ الشهري</label>
          <input class="form-control is-valid" id="sgAmount" value="35" inputmode="decimal">
          <div class="valid-feedback">يغطي احتياج الطفل هذا الشهر.</div>
        </div>
        <div class="col-12">
          <fieldset>
            <legend class="form-label fs-6">من أين تُدفع؟</legend>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="sgKind" id="sgZakat" checked>
              <label class="form-check-label" for="sgZakat">من زكاتي</label>
            </div>
            <div class="form-check form-check-inline">
              <input class="form-check-input" type="radio" name="sgKind" id="sgSadaqa">
              <label class="form-check-label" for="sgSadaqa">من صدقتي</label>
            </div>
          </fieldset>
          <div class="form-check form-switch mt-3">
            <input class="form-check-input" type="checkbox" role="switch" id="sgNews" checked>
            <label class="form-check-label" for="sgNews">أرسل لي التقارير على بريدي</label>
          </div>
        </div>
      </form>
    </section>

    <!-- ══ البطاقات ══ -->
    <section class="sg-section" aria-labelledby="sgCards">
      <p class="sg-label">٥</p>
      <h2 id="sgCards" class="h3 mb-4">البطاقات</h2>
      <div class="row g-4">
        <div class="col-md-6 col-lg-4" data-reveal>
          <article class="card h-100 sg-kid">
            <img class="card-img-top" src="/assets/img/about-illustration.webp" alt="" width="560" height="420">
            <div class="card-body">
              <div class="d-flex justify-content-between align-items-start mb-2">
                <h3 class="card-title h5 mb-0">يوسف، 7 سنوات</h3>
                <span class="badge text-bg-danger">حالة عاجلة</span>
              </div>
              <p class="text-body-secondary small mb-3">الخليل، فلسطين · كفالة الأسرة</p>
              <div class="d-flex justify-content-between small mb-1"><span>مكفول 15٪</span><span>يتبقى $77</span></div>
              <div class="progress mb-4" role="progressbar" aria-label="نسبة التمويل" aria-valuenow="15" aria-valuemin="0" aria-valuemax="100">
                <div class="progress-bar" style="width: 15%"></div>
              </div>
              <a class="btn btn-cta w-100" href="#">اكفل يوسف</a>
            </div>
          </article>
        </div>
        <div class="col-md-6 col-lg-4" data-reveal data-reveal-delay="90">
          <article class="card h-100">
            <img class="card-img-top" src="/assets/img/about-illustration.webp" alt="" width="560" height="350">
            <div class="card-body">
              <span class="badge text-bg-light mb-2">شفافية</span>
              <h3 class="card-title h5">كيف نتحقق من كل حالة قبل نشرها؟</h3>
              <p class="card-text text-body-secondary">ثلاث مراحل تحقّق ميدانية ووثائقية تمرّ بها كل حالة قبل أن تظهر على المنصة.</p>
            </div>
            <div class="card-footer bg-transparent small text-body-secondary">٢٨ يوليو ٢٠٢٦ · ٦ دقائق قراءة</div>
          </article>
        </div>
        <div class="col-lg-4" data-reveal data-reveal-delay="180">
          <article class="card h-100 border-0 text-bg-primary">
            <div class="card-body d-flex flex-column">
              <p class="small opacity-75 mb-1">الكفالة الشاملة</p>
              <p class="display-5 fw-bold mb-0">$65</p>
              <p class="small opacity-75">في الشهر</p>
              <ul class="list-unstyled small flex-grow-1">
                <li class="mb-2"><i class="fa-solid fa-check me-2" aria-hidden="true"></i>غذاء وكسوة شهرية</li>
                <li class="mb-2"><i class="fa-solid fa-check me-2" aria-hidden="true"></i>رسوم ومستلزمات دراسية</li>
                <li class="mb-2"><i class="fa-solid fa-check me-2" aria-hidden="true"></i>تقرير مصوّر كل ٣ أشهر</li>
              </ul>
              <a class="btn btn-cta" href="#">اختر هذا البرنامج</a>
            </div>
          </article>
        </div>
      </div>
    </section>

    <!-- ══ الشارات والتنبيهات ══ -->
    <section class="sg-section" aria-labelledby="sgFeedback">
      <p class="sg-label">٦</p>
      <h2 id="sgFeedback" class="h3 mb-4">الشارات والتنبيهات</h2>
      <div class="d-flex flex-wrap gap-2 mb-4" data-reveal>
        <span class="badge text-bg-primary">موثّق</span>
        <span class="badge text-bg-warning">بانتظار الدفع</span>
        <span class="badge text-bg-success">مكفول</span>
        <span class="badge text-bg-danger">حالة عاجلة</span>
        <span class="badge bg-primary-subtle text-primary-emphasis">الكفالة التعليمية</span>
      </div>
      <div class="row g-3" data-reveal data-reveal-delay="80">
        <div class="col-md-6"><div class="alert alert-success mb-0" role="status"><i class="fa-solid fa-circle-check me-2" aria-hidden="true"></i>وصلت كفالتك. أرسلنا الإيصال إلى بريدك.</div></div>
        <div class="col-md-6"><div class="alert alert-warning mb-0" role="status"><i class="fa-solid fa-clock me-2" aria-hidden="true"></i>التحويل البنكي بانتظار التأكيد خلال ٣ أيام عمل.</div></div>
        <div class="col-md-6"><div class="alert alert-danger mb-0" role="alert"><i class="fa-solid fa-triangle-exclamation me-2" aria-hidden="true"></i>تعذّر الدفع. لم يُخصم أي مبلغ، جرّب بطاقة أخرى.</div></div>
        <div class="col-md-6"><div class="alert alert-info mb-0" role="status"><i class="fa-solid fa-circle-info me-2" aria-hidden="true"></i>يصلك التقرير الربعي القادم في أكتوبر.</div></div>
      </div>
    </section>

    <!-- ══ الطيّ والجدول ══ -->
    <section class="sg-section" aria-labelledby="sgData">
      <p class="sg-label">٧</p>
      <h2 id="sgData" class="h3 mb-4">الأسئلة والجداول</h2>
      <div class="row g-4 g-lg-5">
        <div class="col-lg-6" data-reveal>
          <div class="accordion" id="sgFaq">
            <div class="accordion-item">
              <h3 class="accordion-header">
                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#sgQ1" aria-expanded="true" aria-controls="sgQ1">كيف أتأكد أن كفالتي وصلت فعلاً للطفل؟</button>
              </h3>
              <div id="sgQ1" class="accordion-collapse collapse show" data-bs-parent="#sgFaq">
                <div class="accordion-body">يصلك تقرير مصوّر كل ثلاثة أشهر يكتبه المشرف بعد زيارة البيت والمدرسة، وفيه كيف صُرف مبلغك بنداً بنداً.</div>
              </div>
            </div>
            <div class="accordion-item">
              <h3 class="accordion-header">
                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sgQ2" aria-expanded="false" aria-controls="sgQ2">هل أستطيع إيقاف الكفالة في أي وقت؟</button>
              </h3>
              <div id="sgQ2" class="accordion-collapse collapse" data-bs-parent="#sgFaq">
                <div class="accordion-body">نعم، من حسابك وبنقرة واحدة، بلا رسوم وبلا اتصال.</div>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-6" data-reveal data-reveal-delay="100">
          <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
              <caption class="small">آخر الدفعات</caption>
              <thead><tr><th scope="col">الشهر</th><th scope="col">المبلغ</th><th scope="col">الحالة</th></tr></thead>
              <tbody>
                <tr><td>أكتوبر</td><td class="num">$35</td><td><span class="badge text-bg-success">صُرفت</span></td></tr>
                <tr><td>سبتمبر</td><td class="num">$35</td><td><span class="badge text-bg-success">صُرفت</span></td></tr>
                <tr><td>أغسطس</td><td class="num">$35</td><td><span class="badge text-bg-warning">قيد الصرف</span></td></tr>
              </tbody>
            </table>
          </div>
          <nav class="mt-4" aria-label="صفحات الدفعات">
            <ul class="pagination mb-0">
              <li class="page-item disabled"><span class="page-link">السابق</span></li>
              <li class="page-item active" aria-current="page"><span class="page-link">1</span></li>
              <li class="page-item"><a class="page-link" href="#">2</a></li>
              <li class="page-item"><a class="page-link" href="#">3</a></li>
              <li class="page-item"><a class="page-link" href="#">التالي</a></li>
            </ul>
          </nav>
        </div>
      </div>
    </section>

    <!-- ══ النافذة ══ -->
    <section class="sg-section" aria-labelledby="sgOverlay">
      <p class="sg-label">٨</p>
      <h2 id="sgOverlay" class="h3 mb-4">النوافذ والقائمة الجانبية</h2>
      <div class="d-flex flex-wrap gap-3" data-reveal>
        <button class="btn btn-primary" type="button" data-bs-toggle="modal" data-bs-target="#sgModal">افتح نافذة تأكيد</button>
        <button class="btn btn-outline-primary" type="button" data-bs-toggle="offcanvas" data-bs-target="#siteNav" aria-controls="siteNav">افتح القائمة الجانبية (على الجوال)</button>
      </div>
      <p class="form-text mt-3 mb-0">القائمة الجانبية هي قائمة الموقع نفسها، وتظهر درجاً على الشاشات الصغيرة فقط. تُغلق بزر Escape، والتركيز يبقى داخلها ويعود للزر بعد الإغلاق.</p>
    </section>
  </main>
@endsection

@push('after-footer')
  <div class="modal fade" id="sgModal" tabindex="-1" aria-labelledby="sgModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h2 class="modal-title h5" id="sgModalTitle">إيقاف الكفالة مؤقتاً؟</h2>
          <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="إغلاق"></button>
        </div>
        <div class="modal-body">
          <p class="mb-0">لن يُخصم شيء حتى تستأنفها. يبقى ملف يوسف في حسابك وتصلك تقاريره المنشورة.</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-soft" data-bs-dismiss="modal">تراجع</button>
          <button type="button" class="btn btn-primary" data-bs-dismiss="modal">أوقفها مؤقتاً</button>
        </div>
      </div>
    </div>
  </div>
@endpush
