@extends('site.layouts.legacy')

@section('title')تفاصيل الحالة | كفالة@endsection
@section('description')ملف موثّق لحالة يتيم: قصته، ظروفه الدراسية والصحية، وما تغطيه كفالتك بالتفصيل.@endsection
@section('robots', 'noindex')

@section('content')
  <main id="main">
    <div id="orphanDetail" aria-busy="true">

      <section class="section section--tight">
        <div class="container">
          <nav aria-label="مسار التنقل" class="profile__crumbs">
            <ol class="breadcrumb">
              <li><a href="/">الرئيسية</a></li>
              <li><a href="/orphans">الأيتام</a></li>
              <li><span aria-current="page" data-field="name">…</span></li>
            </ol>
          </nav>

          <div class="profile">

            <!-- الملف -->
            <div class="profile__main">
              <header class="profile__head">
                <p class="kid__urgent" id="orphanUrgent">حالة عاجلة</p>
                <h1 class="profile__name"><span data-field="name">…</span><span class="profile__age">، <span data-field="age">…</span></span></h1>
                <p class="profile__meta">
                  <span data-field="location">…</span>
                  <span>رقم الملف <span class="num" data-field="id">…</span></span>
                </p>
              </header>

              <blockquote class="profile__dream">
                <p data-field="dream">…</p>
                <footer data-field="dream-label">حلمه</footer>
              </blockquote>

              <div class="profile__story">
                <p data-field="story">…</p>
                <p class="profile__note">
                  <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
                  القصة موثّقة من زيارة ميدانية، ومنشورة بموافقة الوصي القانوني على الطفل.
                </p>
              </div>

              <dl class="profile__facts">
                <div><dt>المرحلة الدراسية</dt><dd data-field="grade">…</dd></div>
                <div><dt>الحالة الصحية</dt><dd data-field="health">…</dd></div>
                <div><dt>البرنامج</dt><dd data-field="program">…</dd></div>
              </dl>

              <section class="profile__block" aria-labelledby="coversTitle">
                <h2 id="coversTitle">ما تغطيه كفالتك</h2>
                <ul class="check-list profile__covers" role="list">
                  <li><strong>المعيشة:</strong> غذاء وكسوة موسمية.</li>
                  <li><strong>التعليم:</strong> رسوم وكتب وقرطاسية، ودروس تقوية عند الحاجة.</li>
                  <li><strong>الصحة:</strong> فحص دوري وأدوية ومتابعة طبية.</li>
                  <li><strong>الدعم النفسي:</strong> جلسات إرشاد وأنشطة مع أقرانه.</li>
                  <li><strong>المتابعة:</strong> زيارة ميدانية كل ٩٠ يوماً وتقرير مصوّر.</li>
                </ul>
              </section>

              <section class="profile__block" aria-labelledby="reportsTitle">
                <h2 id="reportsTitle">ما يصلك بعد الكفالة</h2>
                <ol class="timeline" role="list">
                  <li>
                    <time datetime="2026-07-01">يوليو ٢٠٢٦</time>
                    <strong>تقرير الربع الثالث</strong>
                    <p>تحسّن ملحوظ في التحصيل الدراسي، وفحص صحي دوري بلا ملاحظات.</p>
                  </li>
                  <li>
                    <time datetime="2026-04-01">أبريل ٢٠٢٦</time>
                    <strong>تقرير الربع الثاني</strong>
                    <p>صرف مستلزمات الفصل الثاني، وزيارة ميدانية للأسرة.</p>
                  </li>
                  <li>
                    <time datetime="2026-01-01">يناير ٢٠٢٦</time>
                    <strong>تقرير الربع الأول</strong>
                    <p>تسجيل في العام الدراسي، وصرف الكسوة الشتوية.</p>
                  </li>
                </ol>
                <p class="profile__note">
                  <i class="fa-solid fa-lock" aria-hidden="true"></i>
                  التقارير التفصيلية والصور متاحة للكافل فقط، حمايةً لخصوصية الطفل.
                </p>
              </section>
            </div>

            <!-- الصورة ولوحة الكفالة: تلتصقان أثناء القراءة -->
            <aside class="profile__aside" aria-label="الكفالة">
              <figure class="profile__media">
                <img data-field="photo" src="/assets/img/orphan-omnia.webp" alt="" width="800" height="1000">
                <figcaption>صورة توضيحية. لا ننشر صور الأطفال إلا لكافليهم.</figcaption>
              </figure>

              <div class="sponsor-panel">
                <p class="sponsor-panel__price">
                  <strong class="num" data-field="price">$0</strong>
                  <span>في الشهر</span>
                </p>

                <div class="sponsor-panel__fund">
                  <span class="kid__track" id="orphanFund"><span></span></span>
                  <span>مكفول <b class="num" data-field="funded">0٪</b>، ويتبقى <b class="num" data-field="remaining">$0</b> في الشهر</span>
                </div>

                <a class="btn btn--cta btn--lg btn--block" data-sponsor-link href="/checkout">
                  اكفل <span data-field="name">…</span>
                  <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
                </a>

                <p class="sponsor-panel__hint">
                  <i class="fa-solid fa-lock" aria-hidden="true"></i>
                  دفع مشفّر، وتستطيع الإيقاف متى شئت بلا رسوم.
                </p>
              </div>
            </aside>

          </div>
        </div>
      </section>

      <!-- حالات مشابهة -->
      <section class="section section--surface">
        <div class="container">
          <div class="section-head section-head--split" data-reveal>
            <div>
              <p class="meta-label">حالات أخرى</p>
              <h2>قد تودّ الوقوف معهم أيضاً</h2>
            </div>
            <div class="slider__nav">
              <button class="btn-icon" type="button" data-slider-prev aria-label="السابق">
                <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
              </button>
              <button class="btn-icon" type="button" data-slider-next aria-label="التالي">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
              </button>
            </div>
          </div>

          <div class="slider" data-slider data-slider-label="حالات مشابهة">
            <div class="slider__viewport" data-slider-viewport>
              <div class="slider__track" id="similarOrphans"></div>
            </div>
            <div class="slider__progress"><div class="slider__progress-bar" data-slider-progress></div></div>
          </div>
        </div>
      </section>

    </div>
  </main>
@endsection

@push('after-footer')
  <div class="action-bar" id="actionBar">
    <div class="action-bar__info">
      <span data-field="name">…</span>
      <strong class="num" data-field="price">$0</strong>
    </div>
    <a class="btn btn--cta" data-sponsor-link href="/checkout">اكفل <span data-field="name">…</span></a>
  </div>

  <button class="to-top" type="button" id="toTop" aria-label="العودة إلى أعلى الصفحة">
    <i class="fa-solid fa-arrow-up" aria-hidden="true"></i>
  </button>
@endpush
