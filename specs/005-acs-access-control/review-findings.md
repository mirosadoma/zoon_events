# مراجعة تنفيذ 005-acs-access-control — نقاط المراجعة المطلوب تعديلها

> **بالعربى (ملخص):** ده ملف بكل النقاط اللى ظهرت من المراجعة العميقة لموديول
> `AccessControl` (اللى نفّذه موديل LLM أرخص). كل نقطة فيها: مكانها فى الكود،
> المشكلة، التعديل المطلوب، الخطورة، والحالة (اتعدلت / مؤجلة).
> النقاط **F1 لحد F7** اتعدلت فى الكود. تم إضافة تغطية اختبارية لـ F6/F7،
> ولسه مطلوب تشغيل gate التحقق النهائى بعد التعديل.

**Feature**: `specs/005-acs-access-control` · **Module**: `app/Modules/AccessControl`
**Reviewed**: 2026-07-07 · **Method**: whole-file read of the decision path, rule
evaluator, anti-passback, event ingestion, emergency handling, M2M middleware,
routes, adapter interface, and the deployment-parity test, cross-checked against
`contracts/authorization-contract.md`, `data-model.md`, and `plan.md`.

الخطة الأصلية للمراجعة والقرارات موجودة فى:
`C:\Users\AfafHussein\.claude\plans\llm-model-cheaper-did-stateless-pine.md`

---

## جدول النقاط (Summary)

| # | الخطورة / Severity | العنوان | الملف الأساسى | الحالة / Status |
|---|---|---|---|---|
| F1 | 🔴 Critical | قرار عدم توفّر الـ ACS مربوط بالـ test double (`instanceof FakeAcsAdapter`) — مايشتغلش مع أى adapter حقيقى | `Application/Actions/AuthorizeGateAction.php`, `Contracts/AcsAdapter.php`, `Providers/AccessControlServiceProvider.php` | ✅ اتعدلت (Fixed) |
| F2 | 🟠 High | `attendee_type` مابيتحلّش أبداً (بيتبعت `null`) → أى قاعدة على staff/vip/vendor مابتشتغلش | `Application/Actions/AuthorizeGateAction.php` | ✅ اتعدلت (Fixed) |
| F3 | 🟡 Medium | idempotency لاستقبال entry/exit فيه سباق (check-then-insert برّه الـ transaction) | `Application/Actions/IngestAccessEventAction.php` | ✅ اتعدلت (Fixed) |
| F4 | 🟡 Medium | تحديث حالة anti-passback مش atomic → سباق ممكن يسمح بـ passback | `Application/Support/AntiPassbackService.php` | ✅ اتعدلت (Fixed) |
| F5 | 🟡 Medium | الطوارئ (fail-open) بتفتح الدخول بس — الخروج ممكن يترفض أثناء الإخلاء | `Application/Actions/AuthorizeGateAction.php` (+ contracts/data-model) | ✅ اتعدلت (Fixed — دخول وخروج) |
| F6 | ⚪ Low | `reason_code` لأحداث entry/exit/emergency بيتحط قيم مش من قائمة reason codes المعرّفة | `Application/Actions/IngestAccessEventAction.php`, `Application/Actions/RaiseEmergencyAction.php`, `Application/Actions/ClearEmergencyAction.php` | ✅ اتعدلت (Fixed) |
| F7 | ⚪ Low | طوارئ على مستوى الحدث بتسجّل `behavior_applied = fail_open` بغض النظر عن وضع كل zone | `Application/Actions/RaiseEmergencyAction.php` | ✅ اتعدلت (Fixed) |
| — | ℹ️ Cleanup | تكرار `use DatabaseTransactions;` مرتين فى ملف اختبار | `tests/Unit/AccessControl/AntiPassbackServiceTest.php` | ✅ اتعدلت (Fixed) |

**النطاق المنفذ:** تعديل F1–F7. F6/F7 اتعملوا كتحسينات منخفضة الخطورة بدل التأجيل.
**قرار F5:** الطوارئ تفتح **الاتجاهين** (دخول وخروج).

---

## تفاصيل النقاط

### F1 — 🔴 Critical: قرار عدم توفّر الـ ACS مربوط بالـ fake adapter

- **المكان:** `AuthorizeGateAction::execute()` كان بيعمل
  `if ($this->adapter instanceof FakeAcsAdapter && $this->adapter->isUnavailable())`.
  الـ interface `Contracts/AcsAdapter.php` كان بيعرّف `health()` بس، و`isUnavailable()`
  موجودة **بس** فى `Testing/FakeAcsAdapter.php`، والـ binding فى
  `AccessControlServiceProvider.php` كان دايماً بيرجّع `FakeAcsAdapter`.
- **المشكلة:** خطوة القرار رقم 6 (fail-open/fail-closed حسب `unavailability_mode`
  فى `authorization-contract.md`) مكانتش بتشتغل غير لما الـ adapter المربوط هو الـ
  test double. أول ما ييجى adapter حقيقى (بينفّذ `health()` بس)، الخطوة دى بتختفى
  بصمت وأى طلب بيعدّى لـ `allowed` — يعنى zone معمولها `fail_closed` هتسمح بالدخول
  وقت انقطاع الـ ACS. ده كمان بيكسر ادّعاء "Deployment parity" فى `plan.md`.
- **التعديل المطلوب/اللى اتعمل:**
  1. أضفت `isAvailable(): bool` للـ `AcsAdapter` interface.
  2. `AuthorizeGateAction` بقى بيفرّع على `if (! $this->adapter->isAvailable())`
     (اتشال `instanceof` نهائى + اتشال import الـ FakeAcsAdapter).
  3. `FakeAcsAdapter::isAvailable()` بترجّع `! $this->unavailable`.
  4. adapter إنتاجى جديد `Infrastructure/Adapters/MockAcsAdapter.php`
     (`isAvailable() => true`, `health() => online`) وبقى هو الـ binding الافتراضى
     بدل الـ Testing double.
- **الاختبارات:** اختبار جديد
  `AuthorizeGateActionTest::test_unavailability_mode_applies_for_any_adapter_not_only_the_fake`
  بيربط adapter **مش fake** بيرجّع `isAvailable() = false` ويتأكد من fail-open/fail-closed.
  الاختبارات القديمة (`AcsUnavailableDeploymentParityTest`, `AcsHealthApiTest`,
  `AuthorizeGateActionTest`) اتعدلت تربط الـ fake على الـ interface صراحةً.

### F2 — 🟠 High: `attendee_type` مابيتحلّش

- **المكان:** `AuthorizeGateAction` كان بيبعت `null` كـ `attendeeType` للـ
  `AcsRuleEvaluator::evaluate()` و`isAntiPassbackExempt()`. المقيّم بيرفض أى قاعدة
  عندها `attendee_type` مش null.
- **المشكلة:** أى قاعدة موجّهة لـ `staff`/`vip`/`vendor` مكانتش بتطابق أبداً →
  ممرات staff/VIP بترفض الكل (`zone_not_permitted`). فقط قواعد "أى نوع"
  (`attendee_type = null`) كانت بتشتغل.
- **التعديل المطلوب/اللى اتعمل:** بقى بيتحلّ `attendee_type` من الـ `TicketType`
  بتاعة الـ credential (`TicketType.attendee_type`، القيم:
  `attendee|staff|vip|vendor`) ويتبعت للمقيّم وللـ anti-passback-exempt.
- **الاختبار:** `AuthorizeGateActionTest::test_resolves_attendee_type_from_ticket_type_for_rule_matching`
  (قاعدة staff بتسمح لـ credential نوعه staff، وقاعدة vip بترفضه).

### F3 — 🟡 Medium: سباق فى idempotency استقبال الأحداث

- **المكان:** `IngestAccessEventAction::execute()` كان بيقرأ `$existing` بـ
  `(tenant_id, external_event_id)` **قبل** الـ `audited->run()`.
- **المشكلة:** استقبالين متزامنين لنفس `external_event_id` ممكن الاتنين يعدّوا من
  الفحص ويعملوا insert → الـ unique index يرمى `QueryException` (خطأ 500) بدل
  no-op، واحتمال تطبيق anti-passback مرتين. المطلوب no-op idempotent
  (`data-model.md` invariant 4).
- **التعديل المطلوب/اللى اتعمل:** فضل الفحص السريع كـ fast-path، واتغلّف الإنشاء
  بـ `try/catch (UniqueConstraintViolationException)` بيرجّع الصف الموجود كـ no-op.
  الـ transaction بيعمل rollback فمفيش كتابة جزئية.
- **الاختبار:** الموجود `IngestAccessEventActionTest::test_ingest_creates_one_row_and_is_idempotent`
  بيغطى السلوك (نداءين → صف واحد). السباق الحقيقى صعب يتعمل deterministic فى الـ
  harness؛ الأمان متحقّق بالـ unique index + الـ catch.

### F4 — 🟡 Medium: تحديث anti-passback مش atomic

- **المكان:** `AntiPassbackService::applyEvent()` كان بيعمل read ثم `updateOrCreate`.
- **المشكلة:** دخول/خروج متزامن ممكن يتداخل ويسيب الحالة `outside` بدل `inside` →
  يسمح بـ passback. الـ unique index بيمنع تكرار الصفوف بس مش الـ lost updates.
- **التعديل المطلوب/اللى اتعمل:** الانتقال بقى atomic عبر
  `UPDATE ... WHERE last_transition_at < :occurred_at OR IS NULL` (دالة `advance()`)،
  ولو مفيش صف اتعمل insert محمى بـ `try/catch` على الـ unique violation ثم إعادة
  المحاولة الشرطية.
- **الاختبار:** الموجود `AntiPassbackServiceTest::test_apply_event_sets_state_and_ignores_out_of_order_events`
  بيغطى إعادة الترتيب (حدث أقدم مايرجّعش حالة أحدث). كمان اتشال تكرار
  `use DatabaseTransactions;` عشان الملف يشتغل صح.

### F5 — 🟡 Medium: طوارئ الإخلاء بتفتح الدخول بس

- **المكان:** `AuthorizeGateAction::execute()` كان الـ short-circuit مشروط بـ
  `$direction === 'entry'`.
- **المشكلة:** أثناء طوارئ fail-open، طلب **الخروج** كان بيعدّى على تقييم القواعد
  العادى وممكن يترفض — عكس المطلوب من "emergency egress". فيه كمان تعارض فى
  المواصفات: `data-model.md` invariant 7 مكتوب "entry decisions" بينما
  `authorization-contract.md` مش مقيّد بالدخول.
- **القرار:** الطوارئ تفتح **الاتجاهين** (دخول وخروج).
- **التعديل المطلوب/اللى اتعمل:** اتشال قيد `$direction === 'entry'` من الـ
  short-circuit؛ واتحدّثت `data-model.md` (invariant 7 + قسم EmergencyEvent) و
  `authorization-contract.md` (خطوة 2) لتقول "entry and exit".
- **الاختبار:** `AuthorizeGateActionTest::test_emergency_fail_open_allows_exit_not_only_entry`.

### F6 — ⚪ Low: reason_code لأحداث غير القرار

- **المكان:** `IngestAccessEventAction` (`reason_code = $eventType` = `"entry"`/`"exit"`)
  و`RaiseEmergencyAction` / `ClearEmergencyAction` (`"emergency_raised"` / `"emergency_cleared"`).
- **المشكلة:** القيم دى مش ضمن قائمة reason codes المعرّفة فى `data-model.md`
  (كلها قرارات). تأثير تجميلى/تحليلى بس (الصفوف دى `decision = n/a`).
- **التعديل المطلوب/اللى اتعمل:** `reason_code` بقى nullable لأحداث غير القرار
  (`entry`/`exit`/`emergency`) مع migration لتحديث قواعد البيانات القائمة، وتحديث
  `data-model.md`.
- **الاختبار:** `IngestAccessEventActionTest` بيتأكد إن `reason_code` = null بعد
  الاستقبال؛ `OperatorEmergencyApiTest` بيتأكد إن emergency evidence بترجع null.

### F7 — ⚪ Low: دقة `behavior_applied` للطوارئ على مستوى الحدث

- **المكان:** `RaiseEmergencyAction` بيثبّت `fail_open` لما `zoneId` تكون null،
  بينما البوابة بتعيد فحص وضع كل zone. القيمة المسجّلة مضلّلة لـ zones الـ fail_closed.
- **التعديل المطلوب/اللى اتعمل:** الطوارئ على مستوى الحدث بتسجّل `mixed` لو فيه
  zones فعّالة بمزيج `fail_open` و`fail_closed`، وبتسجّل القيمة الوحيدة لو كل الـ
  zones متفقة. اتضاف constraint وmigration لاستخدام `mixed`.
- **الاختبار:** `OperatorEmergencyApiTest::test_event_wide_emergency_records_mixed_behavior_when_zones_disagree`.

---

## اللى شغّال صح (مش محتاج تعديل)

- عزل الـ tenant/event ثابت على كل استعلام؛ lane عبر الحدود بترجّع
  `acs_lane_unmapped` / `acs_event_out_of_scope` (unknown-target parity, CR-001).
- مصادقة M2M (`ResolveAcsIntegration`) + التحقق من الـ capability
  (`RequireAcsCapability`) مطابقين للعقد؛ الـ ACS مش هوية RBAC بشرية.
- مسار ثقة واحد للـ credential: البوابة والاستقبال بيستخدموا
  `CredentialValidator::validate()` (مفيش مسار توقيع/صلاحية تانى) — CR-004.
- كل تغييرات الحالة داخل `AuditedTransaction` + listeners للـ audit؛ رفض الحقول
  المجهولة؛ الـ integration routes عليها `idempotency`.
- كشف الطوارئ على مستوى الحدث صحيح
  (`EmergencyStateService::isActiveForZone` بيطابق `zone_id IS NULL OR = :zone`).

---

## الملفات اللى اتعدّلت (Changed files)

**Code (F1–F7):**
- `app/Modules/AccessControl/Contracts/AcsAdapter.php` — أضيف `isAvailable()`.
- `app/Modules/AccessControl/Testing/FakeAcsAdapter.php` — نفّذ `isAvailable()`.
- `app/Modules/AccessControl/Infrastructure/Adapters/MockAcsAdapter.php` — **جديد** (adapter إنتاجى).
- `app/Modules/AccessControl/Providers/AccessControlServiceProvider.php` — binding افتراضى → `MockAcsAdapter`.
- `app/Modules/AccessControl/Application/Actions/AuthorizeGateAction.php` — F1 + F2 + F5.
- `app/Modules/AccessControl/Application/Actions/IngestAccessEventAction.php` — F3 + F6.
- `app/Modules/AccessControl/Application/Actions/RaiseEmergencyAction.php` — F6 + F7.
- `app/Modules/AccessControl/Application/Actions/ClearEmergencyAction.php` — F6.
- `app/Modules/AccessControl/Application/Support/AntiPassbackService.php` — F4.
- `database/migrations/2026_07_07_000010_fix_acs_review_reason_and_emergency_behavior.php` — **جديد** (F6/F7 schema).

**Docs (F5–F7):**
- `specs/005-acs-access-control/data-model.md` — invariant 7 + nullable reason codes + `mixed` behavior.
- `specs/005-acs-access-control/contracts/authorization-contract.md` — خطوة 2 (دخول وخروج).
- `docs/operations/acs-emergency-egress-runbook.md` — evidence wording.

**Tests:**
- `tests/Unit/AccessControl/AuthorizeGateActionTest.php` — rebind الـ fake + 3 اختبارات جديدة (F1/F2/F5).
- `tests/Integration/AcsUnavailableDeploymentParityTest.php` — rebind الـ fake على الـ interface.
- `tests/Feature/AccessControl/AcsHealthApiTest.php` — rebind الـ fake على الـ interface.
- `tests/Unit/AccessControl/AntiPassbackServiceTest.php` — شيل تكرار `use DatabaseTransactions;`.
- `tests/Unit/AccessControl/IngestAccessEventActionTest.php` — nullable reason assertion (F6).
- `tests/Feature/AccessControl/OperatorEmergencyApiTest.php` — mixed behavior + null reason (F6/F7).
- `tests/Integration/MySql/EmergencyEventSchemaTest.php` — `mixed` constraint assertion (F7).

---

## التحقّق المطلوب

```bash
php artisan migrate --env=testing --force
php artisan test --group=phase-4
composer quality
```

**الاختبارات المتوقّع تنجح:** fail-open/fail-closed لأى adapter (F1)، قاعدة staff
تسمح/ترفض (F2)، idempotency الاستقبال (F3)، إعادة ترتيب anti-passback (F4)،
السماح بالخروج أثناء الطوارئ (F5)، nullable reason codes (F6)، mixed event-wide
emergency behavior (F7).
