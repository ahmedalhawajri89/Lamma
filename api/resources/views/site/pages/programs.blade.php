@extends('site.layouts.legacy')

@section('title')برامج الكفالة | كفالة@endsection
@section('description')أربعة برامج كفالة: شاملة، تعليمية، صحية، وكفالة أسرة. تعرّف على ما يغطيه كل برنامج ومبلغه الشهري.@endsection
@section('canonical', url('/programs'))

@section('content')
  <main id="main">

    <section class="page-hero">
      <div class="container">
        <div class="page-hero__inner">
          <nav aria-label="مسار التنقل">
            <ol class="breadcrumb">
              <li><a href="/">الرئيسية</a></li>
              <li><span aria-current="page">برامج الكفالة</span></li>
            </ol>
          </nav>
          <h1 class="page-hero__title">برامج الكفالة وأسعارها</h1>
          <p class="page-hero__lede">
            كل برنامج يغطي احتياجاً مختلفاً، وكلها موثّقة وتصلك تقاريرها.
            المبالغ محسوبة على تكلفة فعلية، لا مقدّرة جزافاً.
          </p>
        </div>
      </div>
    </section>

    <!-- البرامج -->
    <section class="section">
      <div class="container">
        <h2 class="sr-only">برامج الكفالة المتاحة</h2>
        <div class="plans" id="programsGrid"></div>
      </div>
    </section>

    <!-- حاسبة الأثر: مكانها هنا بجانب البرامج، حيث يقرر الزائر المبلغ -->
    <section class="section section--surface" aria-labelledby="calcTitle">
      <div class="container">
        <header class="block-head">
          <div class="block-head__text">
            <h2 id="calcTitle">ما الذي يغطيه مبلغك كل شهر</h2>
            <p class="block-lede">حرّك الشريط لترى ما يغطيه المبلغ في حياة طفل واحد.</p>
          </div>
        </header>
        <div class="calculator">
          <div>
            <label class="field__label" for="impactRange">مبلغ الكفالة الشهرية</label>
            <div class="calculator__amount">
              <span>$</span>
              <output class="num" id="impactAmount" for="impactRange">45</output>
              <span>في الشهر</span>
            </div>
            <input class="range" type="range" id="impactRange"
                   min="15" max="150" step="5" value="45"
                   aria-describedby="impactSuggestion">
            <div class="range__scale">
              <span class="num">$15</span>
              <span class="num">$150</span>
            </div>
            <p class="field__hint" id="impactSuggestion" role="status" aria-live="polite"></p>
            <a class="btn btn--cta btn--lg calculator__cta" href="/orphans">
              اكفل يتيماً
              <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
            </a>
          </div>
          <div>
            <p class="field__label calculator__list-label">هذا المبلغ يغطي:</p>
            <ul class="impact-list" role="list" id="impactList"></ul>
          </div>
        </div>
      </div>
    </section>

    <!-- مقارنة سريعة -->
    <section class="section">
      <div class="container">
        <div class="block-head">
          <div class="block-head__text">
            <h2>ما الذي يشمله كل برنامج؟</h2>
          </div>
        </div>

        <div class="table-wrap">
          <table class="table">
            <caption class="sr-only">مقارنة بين برامج الكفالة الأربعة وما يشمله كل منها</caption>
            <thead>
              <tr>
                <th scope="col">البند</th>
                <th scope="col">تعليمية</th>
                <th scope="col">صحية</th>
                <th scope="col">شاملة</th>
                <th scope="col">أسرة</th>
              </tr>
            </thead>
            <tbody>
              <tr>
                <th scope="row">غذاء وكسوة</th>
                <td><span class="muted">غير مشمول</span></td><td><span class="muted">غير مشمول</span></td>
                <td><i class="fa-solid fa-check is-yes" aria-hidden="true"></i><span class="sr-only">مشمول</span></td>
                <td><i class="fa-solid fa-check is-yes" aria-hidden="true"></i><span class="sr-only">مشمول</span></td>
              </tr>
              <tr>
                <th scope="row">رسوم ومستلزمات دراسية</th>
                <td><i class="fa-solid fa-check is-yes" aria-hidden="true"></i><span class="sr-only">مشمول</span></td>
                <td><span class="muted">غير مشمول</span></td>
                <td><i class="fa-solid fa-check is-yes" aria-hidden="true"></i><span class="sr-only">مشمول</span></td>
                <td><i class="fa-solid fa-check is-yes" aria-hidden="true"></i><span class="sr-only">مشمول</span></td>
              </tr>
              <tr>
                <th scope="row">رعاية طبية ومتابعة</th>
                <td><span class="muted">غير مشمول</span></td>
                <td><i class="fa-solid fa-check is-yes" aria-hidden="true"></i><span class="sr-only">مشمول</span></td>
                <td><i class="fa-solid fa-check is-yes" aria-hidden="true"></i><span class="sr-only">مشمول</span></td>
                <td>جزئياً</td>
              </tr>
              <tr>
                <th scope="row">دعم نفسي واجتماعي</th>
                <td><span class="muted">غير مشمول</span></td>
                <td><i class="fa-solid fa-check is-yes" aria-hidden="true"></i><span class="sr-only">مشمول</span></td>
                <td><i class="fa-solid fa-check is-yes" aria-hidden="true"></i><span class="sr-only">مشمول</span></td>
                <td><i class="fa-solid fa-check is-yes" aria-hidden="true"></i><span class="sr-only">مشمول</span></td>
              </tr>
              <tr>
                <th scope="row">دعم الإخوة والأسرة</th>
                <td><span class="muted">غير مشمول</span></td><td><span class="muted">غير مشمول</span></td><td><span class="muted">غير مشمول</span></td>
                <td><i class="fa-solid fa-check is-yes" aria-hidden="true"></i><span class="sr-only">مشمول</span></td>
              </tr>
              <tr>
                <th scope="row">تقرير دوري مصوّر</th>
                <td>فصلي</td><td>كل ٣ أشهر</td><td>كل ٣ أشهر</td><td>شهري</td>
              </tr>
              <tr>
                <th scope="row">المبلغ الشهري</th>
                <td class="num">$35</td><td class="num">$45</td><td class="num">$65</td><td class="num">$90</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </section>

    <!-- الزكاة -->
    <section class="section section--surface">
      <div class="container">
        <div class="split">
          <div class="prose-block">
            <p class="meta-label">مسألة فقهية</p>
            <h2>هل تُحتسب الكفالة من الزكاة؟</h2>
            <p>
              كفالة اليتيم في أصلها صدقة عامة. لكن إن كان الطفل وأسرته من الفقراء أو المساكين،
              وهذا حال معظم الحالات عندنا، جاز صرف الزكاة إليهم.
            </p>
            <p>
              لذلك نفصل الحسابين تماماً: أموال الزكاة تُصرف حصراً على الحالات المستحقة زكوياً،
              ولا يُخصم منها أي مصروف إداري إطلاقاً.
            </p>
            <ul class="check-list" role="list">
              <li>خيار «هذا المبلغ من الزكاة» متاح عند الدفع</li>
              <li>حساب بنكي منفصل لأموال الزكاة</li>
              <li>صفر مصاريف إدارية على الزكاة</li>
              <li>تقرير زكاة سنوي مستقل ومدقّق</li>
            </ul>
            <a class="link-arrow" href="/faq">
              اقرأ التفاصيل الفقهية
              <i class="fa-solid fa-arrow-left" aria-hidden="true"></i>
            </a>
          </div>

          <div class="zakat-flow">
            <h3>آلية توزيع الزكاة لدينا</h3>
            <ol class="columns" style="--cols:1">
              <li>
                <span class="columns__num num">01</span>
                <h4>الحالات المستحقة زكوياً أولاً</h4>
                <p>الأشد فقراً وفق تقييم المشرف الميداني.</p>
              </li>
              <li>
                <span class="columns__num num">02</span>
                <h4>صرف كامل بلا خصم</h4>
                <p>١٠٠٪ من مبلغ الزكاة يصل للمستحق.</p>
              </li>
              <li>
                <span class="columns__num num">03</span>
                <h4>توثيق منفصل</h4>
                <p>سجل مستقل يُراجعه المدقق الخارجي سنوياً.</p>
              </li>
            </ol>
          </div>
        </div>
      </div>
    </section>

    <!-- الخاتمة -->
    <section class="closing" aria-labelledby="closingTitle">
      <div class="container">
        <div class="closing__inner closing__inner--solo">
          <div class="closing__pitch">
            <h2 id="closingTitle">حددت البرنامج؟ اختر الطفل الآن</h2>
            <p class="closing__lede">كل الحالات المعروضة موثّقة ومزارة ميدانياً، وتنتظر من يقف معها.</p>
            <div class="closing__actions">
              <a class="btn btn--cta btn--lg" href="/orphans">
                تصفّح كل الأطفال
                <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
              </a>
            </div>
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
