# أنواع الأحداث والتذاكر الشرطية

> مستند مرجعي بناءً على ملاحظات الإدارة وملف PRD (Zonetec v0.1)

## الخلاصة

النظام يفصل بين مفهومين:

| المفهوم | الحقل | المعنى |
|---|---|---|
| **مستوى الحدث** | `tier` | corporate / public / VIP / VVIP — مستوى الأمان والتسجيل والدخول |
| **نوع الحدث** | `event_type` | seminar / conference / workshop / corporate_gathering — شكل الفعالية |
| **وضع التسجيل** | `registration_mode` | `free_registration` أو `paid_ticketing` |

## قاعدة التذاكر (قرار الإدارة)

> **نظام التذاكر ومستويات الأسعار يُفعَّل فقط للأحداث العامة المدفوعة (`tier = public` + `registration_mode = paid_ticketing`).**

| الحالة | Ticket Types | Price Tiers | الدفع |
|---|---|---|---|
| Corporate (مجاني) | ❌ | ❌ | ❌ |
| Public (مجاني) | ❌ | ❌ | ❌ |
| Public (مدفوع) | ✅ مطلوب | ✅ | ✅ |
| VIP / VVIP | ❌ | ❌ | ❌ (بدعوة) |

للأحداث بدون تذاكر: النظام ينشئ تلقائياً **فتحة تسجيل نظامية** (`__registration__`) مخفية عن واجهة التذاكر، لإدارة السعة داخلياً.

## أنواع الأحداث — إضافة تدريجية

| النوع | الحالة | ملاحظات |
|---|---|---|
| `seminar` | ✅ v1 | أول نوع مدعوم |
| `conference` | ✅ v1 | |
| `workshop` | ✅ v1 | |
| `corporate_gathering` | ✅ v1 | |
| `exhibition` | ❌ مؤجل | معقد — يتطلب exhibitor portal ومواقع العارضين |

## خارطة التنفيذ

### المرحلة 1 — التذاكر الشرطية (هذه الدفعة)
- [x] إضافة `event_type` و `registration_mode` لجدول `events`
- [x] `EventRegistrationProfile` — منطق متى تُطلب التذاكر
- [x] تعديل `PublicationReadiness` — اشتراط التذاكر فقط عند الحاجة
- [x] `EnsureDefaultRegistrationSlot` — فتحة تسجيل تلقائية للأحداث المجانية
- [x] تحديث واجهة إعداد الحدث والتنقل الجانبي
- [x] تحديث seeder بأمثلة لكل سيناريو

### المرحلة 2 — تحسين الواجهة
- [x] Wizard لإنشاء الحدث
- [x] إخفاء أقسام غير مطلوبة ديناميكياً في كل الصفحات

### المرحلة 3 — أنواع إضافية
- [ ] إضافة أنواع جديدة واحداً تلو الآخر حسب الحاجة

### مؤجل — Exhibitions
- مواقع العارضين (exhibitor locations)
- بيانات العارضين
- بوابة العارضين (exhibitor portal)

## مصفوفة قرارات

| السؤال | القرار |
|---|---|
| هل كل حدث يحتاج tickets؟ | لا — فقط public + paid |
| هل price tiers لكل الأحداث؟ | لا — فقط public + paid |
| هل ندعم exhibitions؟ | لا في المرحلة الحالية |
| إيه أول event type؟ | seminar |
| إيه الفرق بين tier و type؟ | tier = أمان/دخول، type = شكل الحدث |

## مرجع PRD

- القسم 4: مستويات الأحداث (Corporate / Public / VIP / VVIP)
- القسم 6.1: التذاكر ومستويات الأسعار — أساس v1 للأحداث العامة المدفوعة
- Non-Goals: لا marketplace عام، لا exhibitions في v1
