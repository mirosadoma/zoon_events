# دليل المستخدمين التجريبيين (Demo)

ملخص سريع لعدد المستخدمين، دور كل واحد، ودورة حياته، وماذا يشاهد ويضيف ويعدّل في النظام.

> **بيئة التطوير فقط** — هذه الحسابات تُزرع عبر `FoundationSeeder` و`DemoContentSeeder` ولا تُستخدم في الإنتاج.

---

## كم مستخدم عندي؟

| النوع | العدد | ملاحظة |
|-------|-------|--------|
| مستخدمون أساسيون (تسجيل دخول) | **7** | حسابات جاهزة بكلمة مرور |
| طلبات منظمين (بدون دخول) | **2** | pending / rejected — لاختبار الموافقة |
| **المجموع للاختبار** | **9** | |

---

## الحسابات الأساسية

| البريد | كلمة المرور | الدور | المستأجر (Tenant) |
|--------|-------------|-------|-------------------|
| `super.admin@admin.com` | `admin1234` | Super Administrator (منصة) + Tenant Administrator | fixture-alpha |
| `demo@zonetec.test` | `DemoMeet2026!` | Tenant Administrator | fixture-alpha |
| `fixture.creator@example.test` | (سينثتيك) | Super Administrator + Tenant Administrator | fixture-alpha |
| `fixture.alpha@example.test` | (سينثتيك) | Tenant Administrator | fixture-alpha |
| `fixture.bravo@example.test` | (سينثتيك) | Event Manager | fixture-bravo |
| `onsite@zonetec.test` | `OnsiteDemo2026!` | On-Site Staff | fixture-alpha |
| `acs@zonetec.test` | `AcsDemo2026!` | ACS Operator | fixture-alpha |
| `ticketing@zonetec.test` | `TicketDemo2026!` | Ticketing Manager | fixture-alpha |

---

## ماذا يفعل كل مستخدم؟ (باختصار)

### 1) `super.admin@admin.com` — مدير المنصة الأعلى
- **الدورة:** يسجّل دخول → يرى لوحة المنصة (إعدادات الموقع، الجغرافيا، طلبات المنظمين) + يمكنه الدخول كمستأجر.
- **يشاهد:** كل أقسام المنصة + كل أقسام المستأجر (لأن لديه `*` على المنصة ومدير مستأجر).
- **يضيف/يعدّل:** موافقة/رفض منظمين، إعدادات الموقع، الدول والمدن، المستخدمين والأدوار على مستوى المنصة، وكل عمليات الفعاليات داخل المستأجر.
- **لا يشاهد:** لا يُخفى عنه شيء ضمن صلاحيات النظام.

### 2) `demo@zonetec.test` — مدير المستأجر (الحساب الرئيسي للتجربة)
- **الدورة:** تسجيل دخول → لوحة المستأجر → إدارة فعاليات كاملة.
- **يشاهد:** Overview، Events، Registration، Ticketing، Attendees، Credentials، Check-in، Kiosk، Badges، ACS، Admin (Users/Roles ضمن ما أنشأه).
- **يضيف/يعدّل:** إنشاء/تعديل/نشر/إلغاء فعاليات، حقول التسجيل، أنواع التذاكر، الحضور، الشارات، إعدادات الهوية، أدوار ومستخدمين **أنشأهم هو فقط** (scoped).
- **مثالي لـ:** تجربة المنتج end-to-end كمنظم فعاليات.

### 3) `fixture.bravo@example.test` — مدير فعاليات (Event Manager)
- **الدورة:** دخول على مستأجر `fixture-bravo` فقط.
- **يشاهد:** الفعاليات والتسجيل والتذاكر والحضور والشارات والتحقق من الهوية والتسجيل الميداني — **بدون** إدارة منصة.
- **يضيف/يعدّل:** إدارة الفعاليات (إنشاء، نشر، إلغاء)، التسجيل، التذاكر، الحضور، الشارات، Check-in.
- **لا يشاهد:** إعدادات المنصة، ولا صلاحيات ACS الطارئة الكاملة.

### 4) `ticketing@zonetec.test` — مدير التذاكر
- **الدورة:** يركز على التذاكر والطلبات والاسترداد.
- **يشاهد:** الفعاليات (قراءة)، Ticketing، Orders، Attendees (قراءة)، Credentials (عرض/إلغاء/إعادة إصدار).
- **يضيف/يعدّل:** أنواع التذاكر، الطلبات، الاستردادات.
- **لا يشاهد:** بناء نموذج التسجيل الكامل، إعداد ACS، إدارة المستخدمين.

### 5) `onsite@zonetec.test` — طاقم ميداني
- **الدورة:** يوم الفعالية — مسح، مكتب يدوي، طباعة شارات.
- **يشاهد:** الفعاليات، الحضور، Credentials، Check-in (Dashboard/Scanner/Desk)، حالة Kiosk، طباعة الشارات.
- **يضيف/يعدّل:** مسح الدخول، تسجيل walk-up، طباعة/إعادة طباعة شارة.
- **لا يشاهد:** إنشاء فعاليات، التسعير، إعدادات المنصة.

### 6) `acs@zonetec.test` — مشغّل التحكم بالدخول (ACS)
- **الدورة:** إعداد ومراقبة البوابات والمناطق.
- **يشاهد:** ACS (Zones, Lanes, Gate Health, Access Logs)، الفعاليات (قراءة).
- **يضيف/يعدّل:** إعدادات ACS، إدارة الطوارئ على البوابات.
- **لا يشاهد:** التذاكر، التسجيل، Check-in العادي (إلا ما يتقاطع مع ACS).

### 7) حسابات الاختبار الآلية (`fixture.creator`, `fixture.alpha`)
- **الغرض:** اختبارات PHPUnit/Vitest — نفس صلاحيات المدير تقريباً.
- **لا تُستخدم يدوياً** في العرض التجريبي إلا للتصحيح.

---

## طلبات المنظمين (بدون حساب فعّال)

| البريد | الحالة | الغرض |
|--------|--------|-------|
| `pending.organizer@demo.zonetec.test` | pending | اختبار الموافقة من شاشة Platform → Organizer Requests |
| `rejected.organizer@demo.zonetec.test` | rejected | اختبار الرفض ورسالة البريد |

**دورة الحياة:**
1. زائر يملأ نموذج **Register Organizer** من الصفحة العامة.
2. الطلب يظهر لمدير المنصة كـ **pending**.
3. **موافقة** → إنشاء مستخدم + Tenant Administrator + بريد ترحيب.
4. **رفض** → إغلاق الطلب + بريد بالسبب.

---

## ماذا يظهر في الواجهة حسب الصلاحية؟

القائمة الجانبية تُفلتر تلقائياً حسب خريطة `can` من الخادم (`rbac-ui-map.md`):

| القدرة | مثال صلاحية | من يراها |
|--------|-------------|----------|
| Overview | `tenant.view` | كل أعضاء المستأجر النشطين |
| Events | `event.view` / `event.manage` | مدير المستأجر، مدير الفعاليات |
| Users/Roles | `membership.view` / `role.manage` | مدير المستأجر (محدود بمن أنشأهم) |
| Platform | `platform.*` | Super Administrator فقط |
| Check-in | `checkin.*` | ميداني + مدير |
| ACS | `acs.*` | مشغّل ACS + مدير |

**قاعدة مهمة:** المستخدم **لا يرى نفسه** في قائمة Users، ولا يرى أدوار/مستخدمين أنشأهم مستخدم آخر (scoped visibility).

---

## دورة حياة المستخدم داخل النظام

```
تسجيل / دعوة / موافقة منظم
        ↓
   حساب User (نشط)
        ↓
   TenantMembership (Active)
        ↓
   TenantRole (صلاحيات)
        ↓
   جلسة دخول → can map → Sidebar + أزرار Actions
        ↓
   عمليات (فعاليات، تذاكر، مسح، …) → Audit Log
```

---

## للتجربة السريعة

1. سجّل دخول بـ `demo@zonetec.test` / `DemoMeet2026!`
2. افتح **Overview** — إحصائيات حية + سجل تدقيق.
3. أنشئ فعالية من **Events → Create**.
4. للمنصة: `super.admin@admin.com` / `admin1234` → **Organizer Requests** و **Site Settings**.

---

*آخر تحديث: يوليو 2026 — مرتبط بـ `database/seeders/FoundationSeeder.php` و `DemoAccounts.php`*
