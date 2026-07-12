import type { AppLocale } from '@/lib/localePath'

export type PermissionMeta = {
  key: string
  module: string
}

type LocalizedLabel = { en: string; ar: string }

export const permissionModuleLabels: Record<string, LocalizedLabel> = {
  tenancy: { en: 'Tenancy & users', ar: 'المستأجر والمستخدمون' },
  authorization: { en: 'Roles & permissions', ar: 'الأدوار والصلاحيات' },
  audit: { en: 'Audit', ar: 'التدقيق' },
  'feature-flags': { en: 'Feature flags', ar: 'أعلام الميزات' },
  events: { en: 'Events', ar: 'الفعاليات' },
  registration: { en: 'Registration', ar: 'التسجيل' },
  ticketing: { en: 'Ticketing', ar: 'التذاكر' },
  orders: { en: 'Orders', ar: 'الطلبات' },
  payments: { en: 'Payments', ar: 'المدفوعات' },
  attendees: { en: 'Attendees', ar: 'الحضور' },
  credentials: { en: 'Credentials', ar: 'الاعتمادات' },
  'identity-verification': { en: 'Identity verification', ar: 'التحقق من الهوية' },
  'wallet-passes': { en: 'Wallet passes', ar: 'محافظ Apple/Google' },
  scanning: { en: 'Check-in & scanning', ar: 'تسجيل الحضور والمسح' },
  kiosk: { en: 'Kiosks', ar: 'أكشاك الخدمة' },
  'badge-printing': { en: 'Badge printing', ar: 'طباعة البطاقات' },
  'access-control': { en: 'Access control', ar: 'التحكم في الدخول' },
  identity: { en: 'Platform users', ar: 'مستخدمو المنصة' },
  operations: { en: 'Platform operations', ar: 'عمليات المنصة' },
}

export const permissionLabels: Record<string, LocalizedLabel> = {
  'tenant.view': { en: 'View tenant profile', ar: 'عرض بيانات المستأجر' },
  'membership.view': { en: 'View users', ar: 'عرض المستخدمين' },
  'membership.manage': { en: 'Add & manage users', ar: 'إضافة وإدارة المستخدمين' },
  'role.view': { en: 'View roles', ar: 'عرض الأدوار' },
  'role.manage': { en: 'Create & edit roles', ar: 'إنشاء وتعديل الأدوار' },
  'role.assign': { en: 'Assign roles', ar: 'تعيين الأدوار' },
  'audit.view': { en: 'View audit logs', ar: 'عرض سجلات التدقيق' },
  'audit.export': { en: 'Export audit logs', ar: 'تصدير سجلات التدقيق' },
  'audit.verify': { en: 'Verify audit integrity', ar: 'التحقق من سلامة التدقيق' },
  'configuration.view': { en: 'View configuration', ar: 'عرض الإعدادات' },
  'feature_flag.view': { en: 'View feature flags', ar: 'عرض أعلام الميزات' },
  'feature_flag.manage': { en: 'Manage feature flags', ar: 'إدارة أعلام الميزات' },
  'event.view': { en: 'View events', ar: 'عرض الفعاليات' },
  'event.manage': { en: 'Create & edit events', ar: 'إنشاء وتعديل الفعاليات' },
  'event.publish': { en: 'Publish events', ar: 'نشر الفعاليات' },
  'event.cancel': { en: 'Cancel events', ar: 'إلغاء الفعاليات' },
  'event.reopen': { en: 'Reopen registration', ar: 'إعادة فتح التسجيل' },
  'event.archive': { en: 'Archive events', ar: 'أرشفة الفعاليات' },
  'registration.manage': { en: 'Manage registration forms', ar: 'إدارة نماذج التسجيل' },
  'ticketing.manage': { en: 'Manage ticket types & pricing', ar: 'إدارة أنواع التذاكر والأسعار' },
  'order.view': { en: 'View orders', ar: 'عرض الطلبات' },
  'order.manage': { en: 'Manage orders', ar: 'إدارة الطلبات' },
  'payment.refund': { en: 'Request refunds', ar: 'طلب استرداد المدفوعات' },
  'attendee.view': { en: 'View attendees', ar: 'عرض الحضور' },
  'attendee.manage': { en: 'Manage attendees', ar: 'إدارة الحضور' },
  'attendee.walkup.register': { en: 'Register walk-up attendees', ar: 'تسجيل حضور مباشر' },
  'credential.view': { en: 'View credentials', ar: 'عرض الاعتمادات' },
  'credential.validate': { en: 'Validate credentials', ar: 'التحقق من الاعتمادات' },
  'credential.revoke': { en: 'Revoke credentials', ar: 'إلغاء الاعتمادات' },
  'credential.reissue': { en: 'Reissue credentials', ar: 'إعادة إصدار الاعتمادات' },
  'identity.configure': { en: 'Configure identity requirements', ar: 'إعداد متطلبات التحقق' },
  'identity.review': { en: 'Review identity submissions', ar: 'مراجعة طلبات التحقق' },
  'identity.data.view': { en: 'View identity metadata', ar: 'عرض بيانات التحقق' },
  'identity.data.manage': { en: 'Manage sensitive identity data', ar: 'إدارة بيانات التحقق الحساسة' },
  'wallet.pass.view': { en: 'View wallet passes', ar: 'عرض محافظ التذاكر' },
  'wallet.pass.generate': { en: 'Generate wallet passes', ar: 'إنشاء محافظ التذاكر' },
  'wallet.pass.manage': { en: 'Manage wallet passes', ar: 'إدارة محافظ التذاكر' },
  'checkin.scan.submit': { en: 'Submit QR scans', ar: 'إرسال مسح QR' },
  'checkin.scan.override': { en: 'Override scan rejections', ar: 'تجاوز رفض المسح' },
  'checkin.dashboard.view': { en: 'View check-in dashboard', ar: 'عرض لوحة تسجيل الحضور' },
  'checkin.desk.perform': { en: 'Manual desk check-in', ar: 'تسجيل حضور يدوي' },
  'kiosk.manage': { en: 'Manage kiosks', ar: 'إدارة الأكشاك' },
  'kiosk.health.view': { en: 'View kiosk health', ar: 'عرض حالة الأكشاك' },
  'badge.print': { en: 'Print badges', ar: 'طباعة البطاقات' },
  'badge.reprint': { en: 'Reprint badges', ar: 'إعادة طباعة البطاقات' },
  'badge.template.manage': { en: 'Manage badge templates', ar: 'إدارة قوالب البطاقات' },
  'acs.configure': { en: 'Configure access control', ar: 'إعداد التحكم في الدخول' },
  'acs.events.view': { en: 'View gate access logs', ar: 'عرض سجلات البوابات' },
  'acs.health.view': { en: 'View gate health', ar: 'عرض حالة البوابات' },
  'acs.emergency.manage': { en: 'Manage emergency egress', ar: 'إدارة الإخلاء الطارئ' },
  'platform.tenant.view': { en: 'View platform tenants', ar: 'عرض مستأجري المنصة' },
  'platform.tenant.manage': { en: 'Manage platform tenants', ar: 'إدارة مستأجري المنصة' },
  'platform.user.view': { en: 'View platform users', ar: 'عرض مستخدمي المنصة' },
  'platform.user.manage': { en: 'Manage platform users', ar: 'إدارة مستخدمي المنصة' },
  'platform.role.view': { en: 'View platform roles', ar: 'عرض أدوار المنصة' },
  'platform.role.manage': { en: 'Manage platform roles', ar: 'إدارة أدوار المنصة' },
  'platform.role.assign': { en: 'Assign platform roles', ar: 'تعيين أدوار المنصة' },
  'platform.access.recover': { en: 'Platform access recovery', ar: 'استرداد وصول المنصة' },
  'platform.audit.view': { en: 'View platform audit logs', ar: 'عرض سجلات تدقيق المنصة' },
  'platform.audit.export': { en: 'Export platform audit logs', ar: 'تصدير سجلات تدقيق المنصة' },
  'platform.audit.verify': { en: 'Verify platform audit integrity', ar: 'التحقق من سلامة تدقيق المنصة' },
  'operations.health.view': { en: 'View platform health', ar: 'عرض صحة المنصة' },
  'platform.feature_flag.view': { en: 'View platform feature flags', ar: 'عرض أعلام ميزات المنصة' },
  'platform.feature_flag.manage': { en: 'Manage platform feature flags', ar: 'إدارة أعلام ميزات المنصة' },
  'platform.configuration.view': { en: 'View platform configuration', ar: 'عرض إعدادات المنصة' },
}

export const auditActionLabels: Record<string, LocalizedLabel> = {
  'role.created': { en: 'Role created', ar: 'تم إنشاء دور' },
  'role.updated': { en: 'Role updated', ar: 'تم تحديث دور' },
  'role.deleted': { en: 'Role deleted', ar: 'تم حذف دور' },
  'role.assigned': { en: 'Role assigned', ar: 'تم تعيين دور' },
  'role.revoked': { en: 'Role revoked', ar: 'تم سحب دور' },
  'role.permission_changed': { en: 'Role permissions changed', ar: 'تم تغيير صلاحيات الدور' },
  'event.created': { en: 'Event created', ar: 'تم إنشاء فعالية' },
  'event.updated': { en: 'Event updated', ar: 'تم تحديث فعالية' },
  'event.published': { en: 'Event published', ar: 'تم نشر فعالية' },
  'event.cancelled': { en: 'Event cancelled', ar: 'تم إلغاء فعالية' },
  'event.reopened': { en: 'Event reopened', ar: 'تم إعادة فتح فعالية' },
  'event.archived': { en: 'Event archived', ar: 'تم أرشفة فعالية' },
  'membership.created': { en: 'User added', ar: 'تمت إضافة مستخدم' },
  'membership.updated': { en: 'User updated', ar: 'تم تحديث مستخدم' },
  'credential.revoked': { en: 'Credential revoked', ar: 'تم إلغاء بيانات الدخول' },
  'credential.reissued': { en: 'Credential reissued', ar: 'تم إعادة إصدار بيانات الدخول' },
  'scan.accepted': { en: 'Scan accepted', ar: 'تم قبول المسح' },
  'scan.rejected': { en: 'Scan rejected', ar: 'تم رفض المسح' },
  'order.created': { en: 'Order created', ar: 'تم إنشاء طلب' },
  'order.paid': { en: 'Order paid', ar: 'تم دفع الطلب' },
  'configuration.updated': { en: 'Configuration updated', ar: 'تم تحديث الإعدادات' },
  'audit.viewed': { en: 'Audit viewed', ar: 'تم عرض سجل التدقيق' },
}

export const auditOutcomeLabels: Record<string, LocalizedLabel> = {
  succeeded: { en: 'Succeeded', ar: 'نجح' },
  failed: { en: 'Failed', ar: 'فشل' },
  denied: { en: 'Denied', ar: 'مرفوض' },
}

export function permissionLabel(key: string, locale: AppLocale): string {
  const label = permissionLabels[key]
  if (label) return label[locale]

  return key
    .replace(/^platform\./, '')
    .replaceAll('.', ' · ')
    .replaceAll('_', ' ')
}

export function moduleLabel(module: string, locale: AppLocale): string {
  const label = permissionModuleLabels[module]
  if (label) return label[locale]
  return module
}

export function groupPermissions(permissions: PermissionMeta[]): Array<{ module: string; label: string; items: Array<{ key: string; label: string }> }> {
  const grouped = new Map<string, PermissionMeta[]>()

  for (const permission of permissions) {
    const bucket = grouped.get(permission.module) ?? []
    bucket.push(permission)
    grouped.set(permission.module, bucket)
  }

  return Array.from(grouped.entries()).map(([module, items]) => ({
    module,
    label: module,
    items: items.map((item) => ({ key: item.key, label: item.key })),
  }))
}

export function groupPermissionsLocalized(
  permissions: PermissionMeta[],
  locale: AppLocale,
): Array<{ module: string; label: string; items: Array<{ key: string; label: string }> }> {
  const grouped = new Map<string, PermissionMeta[]>()

  for (const permission of permissions) {
    const bucket = grouped.get(permission.module) ?? []
    bucket.push(permission)
    grouped.set(permission.module, bucket)
  }

  return Array.from(grouped.entries())
    .sort(([a], [b]) => moduleLabel(a, locale).localeCompare(moduleLabel(b, locale), locale))
    .map(([module, items]) => ({
      module,
      label: moduleLabel(module, locale),
      items: items
        .sort((a, b) => permissionLabel(a.key, locale).localeCompare(permissionLabel(b.key, locale), locale))
        .map((item) => ({
          key: item.key,
          label: permissionLabel(item.key, locale),
        })),
    }))
}

export function auditActionLabel(action: string, locale: AppLocale): string {
  return auditActionLabels[action]?.[locale] ?? action.replaceAll('.', ' · ')
}

export function auditOutcomeLabel(outcome: string, locale: AppLocale): string {
  return auditOutcomeLabels[outcome]?.[locale] ?? outcome
}
