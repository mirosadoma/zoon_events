# خطة تفعيل Inertia SSR (قابل للتشغيل/الإيقاف)

Owner: Frontend / Admin Console  
Last reviewed: 2026-07-26  
Status: planned (not implemented)

## الهدف

إضافة Server-Side Rendering لتطبيق Laravel + Inertia + React + Vite، مع إمكانية تشغيله أو إيقافه بعلم إعداد واضح بدون حذف الكود.

## الوضع الحالي

- التطبيق CSR فقط (`createRoot` في `resources/js/app.tsx`)
- مفيش `resources/js/ssr.tsx` ولا `vite build --ssr`
- مفيش `config/inertia.php` منشور؛ إعدادات الحزمة الافتراضية موجودة في `vendor/inertiajs/inertia-laravel/config/inertia.php`
- مكتبات تعتمد على المتصفح (Leaflet / maps / QR / editors / `localStorage`) هتكسر SSR لو اتنفذت على السيرفر بدون حراسة

مرجع القرار الحالي: [ADR 007 — React and Inertia](./decisions/007-react-inertia.md).

---

## المرحلة 0 — قرار النطاق

اختر واحد قبل التنفيذ:

1. **SSR كامل**: كل صفحات Inertia
2. **SSR للصفحات العامة فقط** (مُفضّل للبداية): Landing / public registration / marketing  
   الصفحات الثقيلة (kiosk, maps, badge designer, admin) تفضل CSR أو تتعمل lazy + guards

**توصية:** ابدأ بالعام، وبعدين وسّع.

---

## المرحلة 1 — البنية الأساسية

### 1.1 ملفات جديدة

- [ ] `resources/js/ssr.tsx`
  - `createInertiaApp` مع `renderToString` من `react-dom/server`
  - نفس `resolve` للصفحات زي client
  - بدون `window` / `document` / `localStorage` في مسار السيرفر
- [ ] (اختياري) `resources/js/app-shared.ts` لمشاركة `resolve` بين client و SSR

### 1.2 تعديل client entry

- [ ] في `resources/js/app.tsx`:
  - استبدال `createRoot(...).render` بـ `hydrateRoot` عند تفعيل SSR
  - نقل theme init (`localStorage` / `matchMedia`) إلى مكان آمن (مثلاً inline script في Blade أو `useEffect`)
  - أي كود top-level يعتمد على DOM يبقى داخل `setup` / effects فقط

### 1.3 Vite

- [ ] في `vite.config.ts` داخل `laravel()`:

  ```ts
  laravel({
    input: ['resources/css/app.css', 'resources/js/app.tsx'],
    ssr: 'resources/js/ssr.tsx',
    refresh: true,
  })
  ```

- [ ] مراجعة `manualChunks`: دالة الـ chunks للـ client فقط؛ تأكد إن SSR build مش بيتأثر بشكل خاطئ
- [ ] scripts في `package.json`:

  ```json
  "build": "vite build && vite build --ssr",
  "build:client": "vite build",
  "build:ssr": "vite build --ssr"
  ```

### 1.4 Laravel / Inertia

- [ ] نشر إعدادات Inertia:

  ```bash
  php artisan vendor:publish --provider="Inertia\ServiceProvider"
  ```

  أو نسخ `config/inertia.php` يدوياً من الحزمة.

- [ ] التأكد من وجود مفتاح SSR في config (من الحزمة):

  ```php
  'ssr' => [
      'enabled' => (bool) env('INERTIA_SSR_ENABLED', false),
      'url' => env('INERTIA_SSR_URL', 'http://127.0.0.1:13714'),
      'ensure_bundle_exists' => (bool) env('INERTIA_SSR_ENSURE_BUNDLE_EXISTS', true),
      // 'bundle' => base_path('bootstrap/ssr/ssr.mjs'),
  ],
  ```

  ملاحظة: الافتراضي في الحزمة حالياً `INERTIA_SSR_ENABLED=true`. في مشروعنا نخليه **`false`** لحد ما نجهز الـ bundle والـ Node process.

- [ ] تشغيل Node SSR: `php artisan inertia:start-ssr`
- [ ] أوامر مساعدة موجودة في الحزمة: `inertia:stop-ssr`, `inertia:check-ssr`
- [ ] في production: Supervisor / PM2 / systemd يحافظ على العملية

---

## المرحلة 2 — جعل الكود SSR-safe

### قواعد

- ممنوع استخدام `window` / `document` / `localStorage` / `navigator` خارج:
  - `useEffect`
  - أو `typeof window !== 'undefined'`
- المكوّنات الثقيلة (maps, QR, editors) → `dynamic import` من client فقط أو fallback فارغ على السيرفر

### قائمة مراجعة أولية في المشروع

- [ ] `resources/js/app.tsx` — theme + DOM mount
- [ ] `MapPicker` / leaflet / google maps
- [ ] kiosk camera / `html5-qrcode`
- [ ] email/badge editors اللي بتستخدم `document.execCommand` / selection APIs
- [ ] `apiFetch` / redirects المعتمدة على `window.location`
- [ ] Toast timers المعتمدة على `window.setTimeout` (غالباً OK داخل effects)

### اختبارات

- [ ] `npm run build` (client + ssr) ينجح بدون أخطاء
- [ ] طلب صفحة عامة يرجع HTML فيه محتوى الصفحة (مش shell فاضي)
- [ ] hydration بدون mismatch warnings كبيرة في الكونسول
- [ ] التنقل داخل Inertia بعد أول تحميل يفضل شغال

---

## المرحلة 3 — مفتاح تشغيل/إيقاف SSR

الفكرة: **الكود يفضل موجود**، والتفعيل يبقى من env + config.

### 3.1 Environment

أضف في `.env` ووثّق في `.env.example`:

```env
INERTIA_SSR_ENABLED=false
INERTIA_SSR_URL=http://127.0.0.1:13714
INERTIA_SSR_ENSURE_BUNDLE_EXISTS=true
```

### 3.2 سلوك التشغيل

| القيمة | المعنى |
|--------|--------|
| `INERTIA_SSR_ENABLED=false` | Laravel يرجّع الصفحة كـ CSR عادي (الوضع الحالي) |
| `INERTIA_SSR_ENABLED=true` + Node SSR شغال | أول response يتعمله render على السيرفر ثم hydrate |
| `true` لكن Node واقف | Inertia يعمل fallback لـ CSR (تحقّق عملياً بـ `inertia:check-ssr` والـ logs) |

### 3.3 أوامر عملية

**تشغيل SSR محلياً:**

```bash
npm run build
# terminal 1
php artisan inertia:start-ssr
# terminal 2 — بعد تعديل .env
php artisan config:clear
```

ثم:

```env
INERTIA_SSR_ENABLED=true
```

**إيقاف سريع:**

```env
INERTIA_SSR_ENABLED=false
```

ثم:

```bash
php artisan config:clear
php artisan inertia:stop-ssr
```

### 3.4 (اختياري) تبديل بدون إعادة deploy كامل

- Feature flag من DB/admin لو محتاج تبديل runtime
- للبداية: **env كافٍ وأوضح**

مرجع أنماط الـ flags في المشروع: [ADR 008 — Feature flags](./decisions/008-feature-flags.md).

### 3.5 Build بدون SSR في بيئات مش محتاجاه

لو عايز تبني client فقط أحياناً:

```bash
npm run build:client
```

وخلّي CI/production تستخدم `npm run build` الكامل لما SSR مفعّل.

---

## المرحلة 4 — Production checklist

- [ ] `INERTIA_SSR_ENABLED=true` على السيرفر فقط بعد ما SSR process مستقر
- [ ] process manager لـ `inertia:start-ssr` مع restart on failure
- [ ] health check: `php artisan inertia:check-ssr` + مراقبة fallback/logs
- [ ] قيس TTFB وحجم HTML للصفحات العامة قبل/بعد
- [ ] راقب hydration mismatches بعد كل release

---

## ترتيب التنفيذ المقترح

1. نشر `config/inertia.php` مع `INERTIA_SSR_ENABLED=false` افتراضياً
2. إعداد `ssr.tsx` + Vite + scripts (SSR لسه مطفّي)
3. إصلاح browser-only APIs على مسار الصفحات المستهدفة
4. تفعيل محلي بـ env والتحقق من صفحة عامة واحدة
5. توسيع النطاق تدريجياً
6. تفعيل production + supervisor

---

## تعريف الإنجاز (Done)

- ممكن تبني client و SSR
- ممكن تشغّل/توقف SSR من `INERTIA_SSR_ENABLED` فقط
- صفحة عامة واحدة على الأقل بتترندر من السيرفر وتتعمل لها hydrate بدون كسر واضح
- باقي النظام يفضل شغال لما SSR واقف

---

## ملخص سريع للتشغيل/الإيقاف

| إجراء | خطوات |
|-------|--------|
| إعداد أولي (مرة واحدة) | نفّذ المراحل 1–2 وابنِ بـ `npm run build` |
| تشغيل | `INERTIA_SSR_ENABLED=true` + `php artisan config:clear` + `php artisan inertia:start-ssr` |
| إيقاف | `INERTIA_SSR_ENABLED=false` + `php artisan config:clear` + `php artisan inertia:stop-ssr` |
| تحقق | `php artisan inertia:check-ssr` |
