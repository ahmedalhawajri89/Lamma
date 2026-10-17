@extends('site.layouts.legacy')

@section('title')إتمام الكفالة | كفالة@endsection
@section('description')خطوة أخيرة لتفعيل كفالتك. دفع مشفّر، ولا نحتفظ ببيانات بطاقتك.@endsection
@section('robots', 'noindex, nofollow')

@section('content')
  <main id="main">
    <section class="section">
      <div class="container">

        <!-- الخطوات -->
        <ol class="steps" aria-label="مراحل إتمام الكفالة">
          <li class="is-done">
            <span class="steps__dot" aria-hidden="true"><i class="fa-solid fa-check"></i></span>
            اختيار الطفل
          </li>
          <li aria-current="step">
            <span class="steps__dot num" aria-hidden="true">٢</span>
            الدفع
          </li>
          <li>
            <span class="steps__dot num" aria-hidden="true">٣</span>
            التأكيد
          </li>
        </ol>

        <div class="checkout" id="checkoutSummary">

          <!-- النموذج -->
          <div class="stack stack--lg">

            <div class="card" style="padding:var(--space-8)">
              <h1 style="font-size:var(--text-xl)">بيانات الكفالة</h1>

              <!-- البوابة تجريبية: لا يظن أحد أنه دفع مالاً حقيقياً -->
              <div class="secure-note secure-note--demo" data-checkout="demo" hidden>
                <i class="fa-solid fa-flask" aria-hidden="true"></i>
                <span>
                  <strong>وضع تجريبي:</strong> لا يُخصم أي مال. للقبول استعمل البطاقة
                  <span class="num" dir="ltr">4242 4242 4242 4242</span>، وللرفض
                  <span class="num" dir="ltr">4000 0000 0000 0002</span>، بأي تاريخ قادم وأي رمز.
                </span>
              </div>

              <!-- طفلٌ اكتمل تمويله أو رابطٌ لا يوجد: لا نموذج دفع -->
              <div data-checkout="blocked" hidden role="status" style="margin-block:var(--space-6)">
                <p data-checkout="blocked-text" class="section-lede"></p>
                <a class="btn btn--primary" href="/orphans">اختر طفلاً آخر ينتظر كافلاً</a>
              </div>

              <!-- method=post: إن تعطّل JS لا تذهب الحقول في الرابط -->
              <form id="checkoutForm" data-validate-form novalidate method="post" action="#">

                <!-- نوع الكفالة -->
                <fieldset style="border:0;padding:0;margin:0 0 var(--space-6)">
                  <legend class="field__label" style="margin-block-end:var(--space-3)">تكرار الكفالة</legend>
                  <div class="chip-group" data-chips>
                    <button class="chip is-active" type="button" data-value="monthly">شهرية</button>
                    <button class="chip" type="button" data-value="quarterly">كل ٣ أشهر</button>
                    <button class="chip" type="button" data-value="yearly">سنوية</button>
                  </div>
                </fieldset>

                <!-- المبلغ: لا يتجاوز المتبقي من حاجة الطفل -->
                <div class="field" style="margin-block-end:var(--space-6)">
                  <label class="field__label" for="monthlyAmount">مبلغ الكفالة الشهري <span class="req" aria-hidden="true">*</span></label>
                  <div class="field__control">
                    <i class="fa-solid fa-hand-holding-heart field__icon" aria-hidden="true"></i>
                    <input class="input num" type="text" id="monthlyAmount" inputmode="decimal" dir="ltr" lang="en"
                           data-validate="required" aria-describedby="hint-amount err-monthlyAmount">
                  </div>
                  <p class="field__hint" id="hint-amount"></p>
                  <p class="field__error" id="err-monthlyAmount" aria-live="polite"></p>
                </div>

                <!-- الزكاة -->
                <div class="field" style="margin-block-end:var(--space-6)">
                  <label class="checkbox">
                    <input type="checkbox" name="is_zakat" value="1">
                    <span>
                      احتسب هذا المبلغ من الزكاة
                      <span class="field__hint" style="display:block">
                        يُصرف مبلغك على الحالات المستحقة للزكاة وحدها، ويظهر ذلك في إيصالك.
                      </span>
                    </span>
                  </label>
                </div>

                <!-- بيانات الكافل: للزائر وحده -->
                <p class="secure-note" data-checkout="signed" hidden style="margin-block-end:var(--space-6)">
                  <i class="fa-regular fa-circle-user" aria-hidden="true"></i>
                  <span>تكفل باسم <strong data-checkout="signed-name"></strong>، وتجد الكفالة في لوحتك.</span>
                </p>

                <div data-checkout="guest">
                <h2 style="font-size:var(--text-md);margin-block-end:var(--space-4)">بياناتك</h2>
                <p class="field__hint" style="margin-block-end:var(--space-4)">
                  ننشئ لك حساباً بهذا البريد تتابع منه الكفالة، ونرسل إليه رابط تعيين كلمة المرور.
                  لديك حساب؟ <a href="/login" data-checkout="login-link">سجّل الدخول</a>.
                </p>

                <div class="grid grid--2" style="gap:var(--space-4);margin-block-end:var(--space-4)">
                  <div class="field">
                    <label class="field__label" for="donorName">الاسم الكامل <span class="req" aria-hidden="true">*</span></label>
                    <div class="field__control">
                      <i class="fa-regular fa-user field__icon" aria-hidden="true"></i>
                      <input class="input" type="text" id="donorName" name="name"
                             autocomplete="name" data-validate="required|minlength:3"
                             aria-describedby="err-donorName">
                    </div>
                    <p class="field__error" id="err-donorName" aria-live="polite"></p>
                  </div>

                  <div class="field">
                    <label class="field__label" for="donorEmail">البريد الإلكتروني <span class="req" aria-hidden="true">*</span></label>
                    <div class="field__control">
                      <i class="fa-regular fa-envelope field__icon" aria-hidden="true"></i>
                      <input class="input" type="email" id="donorEmail" name="email"
                             autocomplete="email" inputmode="email" data-validate="required|email"
                             aria-describedby="err-donorEmail">
                    </div>
                    <p class="field__error" id="err-donorEmail" aria-live="polite"></p>
                  </div>
                </div>

                <div class="field" style="margin-block-end:var(--space-8)">
                  <label class="field__label" for="donorPhone">رقم الجوال</label>
                  <div class="field__control">
                    <i class="fa-solid fa-mobile-screen field__icon" aria-hidden="true"></i>
                    <input class="input" type="tel" id="donorPhone" name="phone"
                           autocomplete="tel" inputmode="tel" dir="ltr"
                           data-validate="phone" aria-describedby="err-donorPhone hint-phone">
                  </div>
                  <p class="field__hint" id="hint-phone">اختياري. نستخدمه فقط لإشعارك بوصول التقارير.</p>
                  <p class="field__error" id="err-donorPhone" aria-live="polite"></p>
                </div>
                </div>

                <!-- وسيلة الدفع -->
                <h2 style="font-size:var(--text-md);margin-block-end:var(--space-4)">وسيلة الدفع</h2>

                <fieldset class="pay-methods" style="border:0;padding:0;margin:0 0 var(--space-8)">
                  <legend class="sr-only">اختر وسيلة الدفع</legend>

                  <label class="pay-method">
                    <input type="radio" name="method" value="card" checked>
                    <i class="fa-regular fa-credit-card" aria-hidden="true"></i>
                    <span>بطاقة ائتمان</span>
                  </label>


                  <label class="pay-method">
                    <input type="radio" name="method" value="bank_transfer">
                    <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                    <span>تحويل بنكي</span>
                  </label>
                </fieldset>

                <!-- التحويل البنكي: ماذا يحدث بعده -->
                <div class="secure-note" data-checkout="bank-note" hidden style="margin-block-end:var(--space-6)">
                  <i class="fa-solid fa-building-columns" aria-hidden="true"></i>
                  <span>
                    نحجز الكفالة باسمك، ونعرض لك بيانات الحساب ومرجع كفالتك لتكتبه في التحويل.
                    تتفعّل حين يصل التحويل، ويبقى الحجز <span data-checkout="grace">5 أيام</span>.
                  </span>
                </div>

                <div data-checkout="card-fields">
                <!-- معاينة البطاقة -->
                <div class="card-preview" aria-hidden="true">
                  <div class="card-preview__chip"></div>
                  <div class="card-preview__number" id="previewNumber">•••• •••• •••• ••••</div>
                  <div class="card-preview__row">
                    <div>
                      <span>حامل البطاقة</span>
                      <div id="previewHolder">الاسم على البطاقة</div>
                    </div>
                    <div>
                      <span>ينتهي في</span>
                      <div id="previewExpiry">MM/YY</div>
                    </div>
                  </div>
                </div>

                <!-- بيانات البطاقة -->
                <div class="field" style="margin-block-end:var(--space-4)">
                  <label class="field__label" for="cardHolder">الاسم على البطاقة <span class="req" aria-hidden="true">*</span></label>
                  <div class="field__control">
                    <input class="input" type="text" id="cardHolder"
                           autocomplete="cc-name" data-validate="required|minlength:3"
                           aria-describedby="err-cardHolder">
                  </div>
                  <p class="field__error" id="err-cardHolder" aria-live="polite"></p>
                </div>

                <div class="field" style="margin-block-end:var(--space-4)">
                  <label class="field__label" for="cardNumber">رقم البطاقة <span class="req" aria-hidden="true">*</span></label>
                  <div class="field__control">
                    <i class="fa-regular fa-credit-card field__icon" aria-hidden="true"></i>
                    <input class="input" type="text" id="cardNumber"
                           autocomplete="cc-number" inputmode="numeric" dir="ltr"
                           placeholder="4242 4242 4242 4242"
                           data-validate="required|card" aria-describedby="err-cardNumber">
                  </div>
                  <p class="field__error" id="err-cardNumber" aria-live="polite"></p>
                </div>

                <div class="grid grid--2" style="gap:var(--space-4);margin-block-end:var(--space-6)">
                  <div class="field">
                    <label class="field__label" for="cardExpiry">تاريخ الانتهاء <span class="req" aria-hidden="true">*</span></label>
                    <div class="field__control">
                      <input class="input" type="text" id="cardExpiry"
                             autocomplete="cc-exp" inputmode="numeric" dir="ltr" placeholder="MM/YY"
                             data-validate="required|expiry" aria-describedby="err-cardExpiry">
                    </div>
                    <p class="field__error" id="err-cardExpiry" aria-live="polite"></p>
                  </div>

                  <div class="field">
                    <label class="field__label" for="cardCvc">رمز الأمان <span class="req" aria-hidden="true">*</span></label>
                    <div class="field__control">
                      <i class="fa-solid fa-lock field__icon" aria-hidden="true"></i>
                      <input class="input" type="password" id="cardCvc"
                             autocomplete="cc-csc" inputmode="numeric" dir="ltr" placeholder="123"
                             data-validate="required|cvc" aria-describedby="err-cardCvc">
                    </div>
                    <p class="field__error" id="err-cardCvc" aria-live="polite"></p>
                  </div>
                </div>

                </div>

                <div class="secure-note" style="margin-block-end:var(--space-6)">
                  <i class="fa-solid fa-shield-halved" aria-hidden="true"></i>
                  <span>
                    الاتصال مشفّر، وبيانات بطاقتك تُعالَج لدى بوابة الدفع مباشرة.
                    لا تمرّ بخوادمنا ولا نحتفظ بها.
                  </span>
                </div>

                <p class="checkout__plan" data-checkout="plan" aria-live="polite"
                   style="margin-block-end:var(--space-4);font-weight:600"></p>

                <p class="field__error checkout-error" data-checkout="error" role="alert" hidden></p>

                <button class="btn btn--cta btn--lg btn--block" type="submit" id="checkoutSubmit">
                  <i class="fa-solid fa-lock" aria-hidden="true"></i> تفعيل الكفالة</button>

                <p class="field__hint text-center" style="margin-block-start:var(--space-4)">
                  بالمتابعة أنت توافق على
                  <a href="/transparency#child-policy">شروط الكفالة وسياسة الخصوصية</a>.
                  يمكنك الإيقاف في أي وقت بلا رسوم.
                </p>
              </form>
            </div>

          </div>

          <!-- الملخص -->
          <aside class="summary" aria-label="ملخص الكفالة">
            <h2 style="font-size:var(--text-md)">ملخص الكفالة</h2>

            <div class="summary__child">
              <img data-checkout="photo" alt="" width="56" height="56" hidden>
              <span data-checkout="initial" class="summary__initial" aria-hidden="true"></span>
              <div>
                <strong data-checkout="name">…</strong>
                <span class="badge badge--brand" data-checkout="program">…</span>
              </div>
            </div>

            <div class="summary__lines">
              <div class="summary__line">
                <span>رقم الملف</span>
                <strong class="num" data-checkout="id">…</strong>
              </div>
              <div class="summary__line">
                <span>المتبقي من حاجته</span>
                <strong class="num" data-checkout="remaining">…</strong>
              </div>
              <div class="summary__line">
                <span>كفالتك الشهرية</span>
                <strong class="num" data-checkout="price">…</strong>
              </div>
              <div class="summary__line">
                <span>رسوم المنصة</span>
                <strong style="color:var(--color-success)">لا يوجد</strong>
              </div>
            </div>

            <!--
              للمجموع خطّافه الخاص: كان يشترك مع سطر «الكفالة الشهرية» في
              `data-checkout="price"`، فيعرض المبلغ الشهري مهما اختار الزائر
              من دورية — يختار «سنوية» ويقرأ سعر شهر.
            -->
            <div class="summary__total">
              <span>الإجمالي الآن <span class="muted" data-checkout="cycle"></span></span>
              <strong class="num" data-checkout="total">…</strong>
            </div>

            <div class="secure-note">
              <i class="fa-solid fa-circle-check" aria-hidden="true"></i>
              <span>ما يصل للطفل وما يُصرف على التشغيل منشورٌ كل شهر في <a href="/transparency">تقارير الشفافية</a>.</span>
            </div>

            <a class="link-arrow" href="/orphans">
              <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
              تغيير الطفل المختار
            </a>
          </aside>

        </div>
      </div>
    </section>
  </main>
@endsection
