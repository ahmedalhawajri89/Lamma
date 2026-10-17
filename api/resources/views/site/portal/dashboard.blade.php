@extends('site.layouts.legacy', ['chrome' => false])

@section('title')لوحتي | كفالة@endsection
@section('description')تابع كفالاتك وتقاريرها وسجل مدفوعاتك من مكان واحد.@endsection
@section('robots', 'noindex, nofollow')

@section('content')
  <a class="skip-link" href="#main">تخطَّ إلى المحتوى الرئيسي</a>

  <!--
    لوحة الكافل امتداد للموقع لا لوحة إدارة: شريط علوي رفيع بأقسام الصفحة،
    ثم أقسام مفتوحة بخطوط رفيعة. كل رقم يُقرأ من /api/v1/portal/*.
  -->
  <div class="portal" data-portal data-portal-loading>

    <header class="portal-bar">
      <div class="container portal-bar__inner">
        <a class="brand" href="/">
          <img class="brand__mark" src="/assets/img/logo.svg" alt="" width="36" height="36">
          <span class="brand__name">كفالة</span>
        </a>

        <nav class="portal-tabs" aria-label="أقسام لوحتك">
          <a class="portal-tab" href="#overview" aria-current="true">نظرة عامة</a>
          <a class="portal-tab" href="#sponsorships">كفالاتي</a>
          <a class="portal-tab" href="#reports">
            التقارير
            <span class="portal-tab__dot" data-portal-reports-badge hidden></span>
          </a>
          <a class="portal-tab" href="#payments">المدفوعات</a>
          <a class="portal-tab" href="#account">حسابي</a>
        </nav>

        <div class="portal-bar__user" data-theme-slot>
          <span class="avatar avatar--sm avatar--1" aria-hidden="true" data-portal-avatar>·</span>
          <span class="portal-bar__name" data-portal-name>…</span>
          <a class="portal-bar__logout" href="/" data-portal-logout>
            <i class="fa-solid fa-arrow-right-from-bracket" aria-hidden="true"></i>
            <span>تسجيل الخروج</span>
          </a>
        </div>
      </div>
    </header>

    <main id="main">

      <!-- الترحيب والأرقام -->
      <section class="portal-section portal-hello" id="overview" aria-labelledby="helloTitle">
        <div class="container">
          <div class="portal-hello__head">
            <div>
              <p class="meta-label" data-portal-since></p>
              <h1 id="helloTitle" data-portal-greeting>أهلاً بك</h1>
              <p class="portal-hello__status" data-portal-subtitle>جارٍ تحميل لوحتك…</p>
            </div>
            <a class="btn btn--cta" href="/orphans">
              اكفل طفلاً آخر
              <i class="fa-solid fa-arrow-left btn__icon btn__icon--arrow" aria-hidden="true"></i>
            </a>
          </div>

          <dl class="figures portal-figures">
            <div><dt>أيتام تكفلهم</dt><dd class="num" data-stat="orphans">…</dd></div>
            <div><dt>ما قدّمته حتى اليوم</dt><dd class="num" data-stat="given">…</dd></div>
            <div><dt>التزامك الشهري</dt><dd class="num" data-stat="commitment">…</dd></div>
            <div><dt data-stat="next-hint">القسط القادم</dt><dd data-stat="next">…</dd></div>
          </dl>
        </div>
      </section>

      <!-- الكفالات -->
      <section class="portal-section portal-section--surface" id="sponsorships" aria-labelledby="sponsorshipsTitle">
        <div class="container">
          <div class="portal-head">
            <h2 id="sponsorshipsTitle">كفالاتي</h2>
            <a class="link-arrow" href="/orphans">إضافة كفالة <i class="fa-solid fa-arrow-left" aria-hidden="true"></i></a>
          </div>

          <div class="pkids" data-portal-sponsorships>
            <p class="muted">جارٍ التحميل…</p>
          </div>

          <details class="ppast-wrap" data-portal-past hidden>
            <summary>كفالات سابقة (<span class="num" data-portal-past-count>0</span>)</summary>
            <ul class="ppast-list" role="list" data-portal-past-list></ul>
          </details>
        </div>
      </section>

      <!-- التقارير -->
      <section class="portal-section" id="reports" aria-labelledby="reportsTitle">
        <div class="container">
          <div class="portal-head">
            <h2 id="reportsTitle">آخر التقارير</h2>
            <p class="portal-head__note">تقرير مصوّر عن كل طفل كل ثلاثة أشهر.</p>
          </div>

          <ul class="preports" role="list" data-portal-reports>
            <li class="muted">جارٍ التحميل…</li>
          </ul>
        </div>
      </section>

      <!-- المدفوعات -->
      <section class="portal-section portal-section--surface" id="payments" aria-labelledby="paymentsTitle">
        <div class="container">
          <div class="portal-head">
            <h2 id="paymentsTitle">سجل المدفوعات</h2>
            <p class="portal-head__note">الإجمالي <strong class="num" data-stat="payments-total">…</strong></p>
          </div>

          <div class="table-wrap">
            <table class="table ptable">
              <caption class="sr-only">سجل مدفوعاتك</caption>
              <thead>
                <tr>
                  <th scope="col">التاريخ</th>
                  <th scope="col">الطفل</th>
                  <th scope="col">المبلغ</th>
                  <th scope="col">الوسيلة</th>
                  <th scope="col">الحالة</th>
                  <th scope="col">الإيصال</th>
                </tr>
              </thead>
              <tbody data-portal-payments>
                <tr><td colspan="6" class="muted">جارٍ التحميل…</td></tr>
              </tbody>
            </table>
          </div>
          <button class="btn btn--outline btn--sm ptable__more" type="button" data-payments-more hidden>دفعات أقدم</button>
        </div>
      </section>

      <!-- الحساب -->
      <section class="portal-section" id="account" aria-labelledby="accountTitle">
        <div class="container">
          <div class="portal-head">
            <h2 id="accountTitle">حسابي</h2>
          </div>

          <div class="paccount">
            <form class="paccount__form" data-portal-profile novalidate>
              <h3>بياناتك</h3>
              <div class="field">
                <label class="field__label" for="profileName">الاسم</label>
                <input class="input" type="text" id="profileName" name="name" autocomplete="name" required minlength="3">
              </div>
              <div class="field">
                <label class="field__label" for="profileEmail">البريد الإلكتروني</label>
                <input class="input" type="email" id="profileEmail" name="email" autocomplete="email" required>
              </div>
              <div class="field">
                <label class="field__label" for="profilePhone">رقم الجوال</label>
                <input class="input" type="tel" id="profilePhone" name="phone" autocomplete="tel" dir="ltr">
              </div>
              <div class="field" data-profile-confirm hidden>
                <label class="field__label" for="profileCurrent">كلمة المرور الحالية</label>
                <input class="input" type="password" id="profileCurrent" name="current_password" autocomplete="current-password" aria-describedby="hint-profileCurrent">
                <p class="field__hint" id="hint-profileCurrent">لتغيير البريد نتأكد أنك صاحب الحساب.</p>
              </div>
              <button class="btn btn--primary" type="submit">احفظ التعديلات</button>
            </form>

            <form class="paccount__form" data-portal-password novalidate>
              <h3>كلمة المرور</h3>
              <div class="field">
                <label class="field__label" for="currentPassword">كلمة المرور الحالية</label>
                <input class="input" type="password" id="currentPassword" name="current_password" autocomplete="current-password" required aria-describedby="hint-currentPassword">
                <p class="field__hint" id="hint-currentPassword">أنشأنا حسابك عند الكفالة ولم تضع كلمة مرور؟ <button class="link-button" type="button" data-send-reset>أرسل لي رابط تعيينها</button>.</p>
              </div>
              <div class="field">
                <label class="field__label" for="newPassword">كلمة المرور الجديدة</label>
                <input class="input" type="password" id="newPassword" name="password" autocomplete="new-password" required minlength="8" aria-describedby="hint-newPassword">
                <p class="field__hint" id="hint-newPassword">٨ أحرف على الأقل، فيها حرف ورقم.</p>
              </div>
              <div class="field">
                <label class="field__label" for="newPassword2">أعد كتابتها</label>
                <input class="input" type="password" id="newPassword2" name="password_confirmation" autocomplete="new-password" required>
              </div>
              <button class="btn btn--outline" type="submit">غيّر كلمة المرور</button>
            </form>
          </div>
        </div>
      </section>

    </main>
  </div>

  <!-- نافذة التقرير -->
  <div class="modal" id="reportModal" role="dialog" aria-modal="true" aria-labelledby="reportModalTitle">
    <div class="modal__backdrop"></div>
    <div class="modal__panel preport-modal">
      <button class="modal__close" type="button" data-modal-close aria-label="إغلاق">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
      <div data-report-content></div>
    </div>
  </div>

  <!-- نافذة الرسائل -->
  <div class="modal" id="messagesModal" role="dialog" aria-modal="true" aria-labelledby="messagesTitle">
    <div class="modal__backdrop"></div>
    <div class="modal__panel pmessages">
      <button class="modal__close" type="button" data-modal-close aria-label="إغلاق">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
      <div class="modal__head">
        <h2 id="messagesTitle">رسائلك</h2>
        <p>يقرأ المشرف الميداني كل رسالة قبل أن تصل للطفل، حمايةً له. لا تكتب رقم هاتف أو عنواناً.</p>
      </div>
      <ol class="pmessages__list" role="list" data-messages-list></ol>
      <form class="pmessages__form" data-messages-form novalidate>
        <label class="sr-only" for="messageBody">رسالتك</label>
        <textarea class="textarea" id="messageBody" name="body" rows="3" maxlength="2000"
                  placeholder="اكتب كلمة تشجيع، أو سؤالاً عن دراسته…" data-autofocus></textarea>
        <button class="btn btn--primary" type="submit">أرسل</button>
      </form>
    </div>
  </div>

  <!-- نافذة إيقاف الكفالة -->
  <div class="modal" id="cancelModal" role="dialog" aria-modal="true" aria-labelledby="cancelTitle">
    <div class="modal__backdrop"></div>
    <div class="modal__panel">
      <button class="modal__close" type="button" data-modal-close aria-label="إغلاق">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
      <div class="modal__head">
        <h2 id="cancelTitle"><span data-cancel-verb>إنهاء كفالة</span> <span data-cancel-name></span></h2>
        <p data-cancel-lede></p>
      </div>

      <!-- من يحتاج استراحة لا يخسر مكانه عند الطفل -->
      <div class="pcancel__pause" data-cancel-pause-box>
        <p><strong>تحتاج استراحة فقط؟</strong> أوقف الخصم مؤقتاً، ويبقى الطفل محجوزاً لك حتى تستأنف.</p>
        <button class="btn btn--primary btn--sm" type="button" data-cancel-pause>
          <i class="fa-solid fa-pause" aria-hidden="true"></i> أوقفها مؤقتاً
        </button>
      </div>

      <form data-cancel-form novalidate>
        <div class="field" data-cancel-reason>
          <label class="field__label" for="cancelReason">هل تخبرنا بالسبب؟ <span class="muted">(اختياري)</span></label>
          <textarea class="textarea" id="cancelReason" name="reason" rows="3" maxlength="255"></textarea>
        </div>
        <div class="cluster pcancel__actions">
          <button class="btn btn--danger" type="submit" data-cancel-submit>أنهِ الكفالة</button>
          <button class="btn btn--outline" type="button" data-modal-close>تراجع</button>
        </div>
      </form>
    </div>
  </div>

  <!-- نافذة بيانات التحويل للحجز -->
  <div class="modal" id="bankModal" role="dialog" aria-modal="true" aria-labelledby="bankTitle">
    <div class="modal__backdrop"></div>
    <div class="modal__panel">
      <button class="modal__close" type="button" data-modal-close aria-label="إغلاق">
        <i class="fa-solid fa-xmark" aria-hidden="true"></i>
      </button>
      <div class="modal__head">
        <h2 id="bankTitle">بيانات التحويل</h2>
        <p>اكتب مرجع الكفالة في خانة البيان حتى نربط التحويل بك. تبدأ الكفالة حين يصل.</p>
      </div>
      <dl class="thanks__bank">
        <div><dt>المبلغ</dt><dd class="num" data-bank="amount"></dd></div>
        <div><dt>مرجع الكفالة</dt><dd class="num" dir="ltr" data-bank="reference"></dd></div>
        <div><dt>البنك</dt><dd data-bank="name"></dd></div>
        <div><dt>اسم الحساب</dt><dd data-bank="account"></dd></div>
        <div><dt>IBAN</dt><dd class="num" dir="ltr" data-bank="iban"></dd></div>
        <div><dt>SWIFT</dt><dd class="num" dir="ltr" data-bank="swift"></dd></div>
        <div><dt>يبقى الحجز حتى</dt><dd data-bank="until"></dd></div>
      </dl>
    </div>
  </div>
@endsection
