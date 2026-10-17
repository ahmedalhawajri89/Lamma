@extends('site.layouts.legacy')

@section('title')الأسئلة الشائعة | كفالة@endsection
@section('description')إجابات مباشرة عن الكفالة: كيف نتحقق، ما نسبة المصاريف الإدارية، هل تُحتسب من الزكاة، وكيف توقفها.@endsection
@section('canonical', url('/faq'))

@section('content')
  <main id="main">

    <section class="page-hero">
      <div class="container">
        <div class="page-hero__inner">
          <nav aria-label="مسار التنقل">
            <ol class="breadcrumb">
              <li><a href="/">الرئيسية</a></li>
              <li><span aria-current="page">الأسئلة الشائعة</span></li>
            </ol>
          </nav>
          <h1 class="page-hero__title">أسئلة يطرحها الناس قبل أن يبدأوا</h1>
          <p class="page-hero__lede">
            أجبنا عنها بصراحة، بما فيها الأسئلة المحرجة عن المال والمصاريف الإدارية.
          </p>
        </div>
      </div>
    </section>

    <section class="section">
      <div class="container container--narrow">
        <h2 class="sr-only">قائمة الأسئلة والأجوبة</h2>
        <div class="accordion" data-accordion="single" id="faqList"></div>
      </div>
    </section>

    <!-- مسارات المساعدة -->
    <section class="section section--surface">
      <div class="container">
        <div class="block-head">
          <div class="block-head__text">
            <p class="meta-label">لم تجد جوابك؟</p>
            <h2>ثلاث طرق للوصول إلينا</h2>
          </div>
        </div>

        <div class="columns" style="--cols:3">
          <div>
            <i class="fa-brands fa-whatsapp columns__icon" aria-hidden="true"></i>
            <h3>واتساب</h3>
            <p>أسرع وسيلة. نرد عادةً خلال ساعتين في أيام العمل.</p>
            <a class="link-arrow" href="https://wa.me/970599000000" target="_blank" rel="noopener noreferrer">راسلنا على واتساب<span class="sr-only"> (يفتح في نافذة جديدة)</span> <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
          </div>

          <div>
            <i class="fa-regular fa-envelope columns__icon" aria-hidden="true"></i>
            <h3>البريد الإلكتروني</h3>
            <p>للأسئلة التفصيلية عن التقارير أو الميزانية. نرد خلال ٧٢ ساعة.</p>
            <a class="link-arrow" href="mailto:info@kafala.org">أرسل رسالة <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
          </div>

          <div>
            <i class="fa-solid fa-file-shield columns__icon" aria-hidden="true"></i>
            <h3>صفحة الشفافية</h3>
            <p>الميزانية المدقّقة والتقارير السنوية وسياسة حماية الطفل.</p>
            <a class="link-arrow" href="/transparency">اطّلع عليها <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
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
