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
  'venue-marketplace': { en: 'Venue marketplace', ar: 'سوق الأماكن' },
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
  'venue.manage': { en: 'Manage venues & assets', ar: 'إدارة الأماكن والأصول' },
  'marketplace.manage': { en: 'Browse catalog & request rentals', ar: 'تصفح دليل السوق وطلب الإيجارات' },
  'rentals.approve': { en: 'Approve & reject rentals', ar: 'الموافقة على الإيجارات ورفضها' },
  'reports.view': { en: 'View marketplace statements', ar: 'عرض كشوف السوق' },
  'platform.user.view': { en: 'View platform admins', ar: 'عرض مشرفي المنصة' },
  'platform.user.manage': { en: 'Manage platform admins', ar: 'إدارة مشرفي المنصة' },
  'platform.role.view': { en: 'View platform roles', ar: 'عرض أدوار المنصة' },
  'platform.role.manage': { en: 'Manage platform roles', ar: 'إدارة أدوار المنصة' },
  'platform.event.view': { en: 'View all events', ar: 'عرض كل الفعاليات' },
  'platform.subscription.view': { en: 'View subscriptions', ar: 'عرض الاشتراكات' },
  'platform.subscription.manage': { en: 'Manage subscriptions', ar: 'إدارة الاشتراكات' },
  'platform.tenant.view': { en: 'View platform tenants', ar: 'عرض مستأجري المنصة' },
  'platform.tenant.manage': { en: 'Manage platform tenants', ar: 'إدارة مستأجري المنصة' },
  'platform.access.recover': { en: 'Platform access recovery', ar: 'استرداد وصول المنصة' },
  'platform.audit.view': { en: 'View platform audit logs', ar: 'عرض سجلات تدقيق المنصة' },
  'platform.audit.export': { en: 'Export platform audit logs', ar: 'تصدير سجلات تدقيق المنصة' },
  'platform.audit.verify': { en: 'Verify platform audit integrity', ar: 'التحقق من سلامة تدقيق المنصة' },
  'operations.health.view': { en: 'View platform health', ar: 'عرض صحة المنصة' },
  'platform.feature_flag.view': { en: 'View platform feature flags', ar: 'عرض أعلام ميزات المنصة' },
  'platform.feature_flag.manage': { en: 'Manage platform feature flags', ar: 'إدارة أعلام ميزات المنصة' },
  'platform.configuration.view': { en: 'View platform configuration', ar: 'عرض إعدادات المنصة' },
  'platform.marketplace.view': { en: 'View marketplace oversight', ar: 'عرض إشراف السوق' },
  'platform.marketplace.disputes.manage': { en: 'Manage marketplace disputes', ar: 'إدارة نزاعات السوق' },
}

export const auditActionLabels: Record<string, LocalizedLabel> = {
  // Roles & authorization
  'role.created': { en: 'Role created', ar: 'تم إنشاء دور' },
  'role.updated': { en: 'Role updated', ar: 'تم تحديث دور' },
  'role.deleted': { en: 'Role deleted', ar: 'تم حذف دور' },
  'role.assigned': { en: 'Role assigned', ar: 'تم تعيين دور' },
  'role.revoked': { en: 'Role revoked', ar: 'تم سحب دور' },
  'role.permission_changed': { en: 'Role permissions changed', ar: 'تم تغيير صلاحيات الدور' },
  // Events
  'event.created': { en: 'Event created', ar: 'تم إنشاء فعالية' },
  'event.updated': { en: 'Event updated', ar: 'تم تحديث فعالية' },
  'event.published': { en: 'Event published', ar: 'تم نشر فعالية' },
  'event.cancelled': { en: 'Event cancelled', ar: 'تم إلغاء فعالية' },
  'event.reopened': { en: 'Event reopened', ar: 'تم إعادة فتح فعالية' },
  'event.archived': { en: 'Event archived', ar: 'تم أرشفة فعالية' },
  // Membership
  'membership.created': { en: 'User added', ar: 'تمت إضافة مستخدم' },
  'membership.updated': { en: 'User updated', ar: 'تم تحديث مستخدم' },
  // Registration & ticketing
  'registration_form.published': { en: 'Registration form published', ar: 'تم نشر نموذج التسجيل' },
  'ticket_type.created': { en: 'Ticket type created', ar: 'تم إنشاء نوع تذكرة' },
  'ticket_type.updated': { en: 'Ticket type updated', ar: 'تم تحديث نوع تذكرة' },
  'registration.free_completed': { en: 'Free registration completed', ar: 'تم إكمال تسجيل مجاني' },
  // Inventory
  'inventory.held': { en: 'Inventory held', ar: 'تم حجز المخزون' },
  'inventory.converted': { en: 'Inventory converted', ar: 'تم تحويل المخزون' },
  'inventory.released': { en: 'Inventory released', ar: 'تم تحرير المخزون' },
  'inventory.expired': { en: 'Inventory expired', ar: 'انتهت صلاحية المخزون' },
  // Payments
  'payment.pending': { en: 'Payment pending', ar: 'دفع معلق' },
  'payment.authorized': { en: 'Payment authorized', ar: 'تم تفويض الدفع' },
  'payment.captured': { en: 'Payment captured', ar: 'تم تحصيل الدفع' },
  'payment.failed': { en: 'Payment failed', ar: 'فشل الدفع' },
  'payment.cancelled': { en: 'Payment cancelled', ar: 'تم إلغاء الدفع' },
  'payment.refunded': { en: 'Payment refunded', ar: 'تم استرداد الدفع' },
  'payment.partially_refunded': { en: 'Payment partially refunded', ar: 'تم استرداد جزئي' },
  'payment.unknown': { en: 'Payment status unknown', ar: 'حالة الدفع غير معروفة' },
  // Refunds
  'refund.succeeded': { en: 'Refund succeeded', ar: 'نجح الاسترداد' },
  'refund.failed': { en: 'Refund failed', ar: 'فشل الاسترداد' },
  'refund.unknown': { en: 'Refund status unknown', ar: 'حالة الاسترداد غير معروفة' },
  // Orders
  'order.created': { en: 'Order created', ar: 'تم إنشاء طلب' },
  'order.paid': { en: 'Order paid', ar: 'تم دفع الطلب' },
  // Attendees
  'attendee.corrected': { en: 'Attendee corrected', ar: 'تم تصحيح بيانات حاضر' },
  'attendee.walk_up_registered': { en: 'Walk-up attendee registered', ar: 'تم تسجيل حاضر مباشر' },
  // Credentials
  'credential.revoked': { en: 'Credential revoked', ar: 'تم إلغاء بيانات الدخول' },
  'credential.reissued': { en: 'Credential reissued', ar: 'تم إعادة إصدار بيانات الدخول' },
  // Notifications
  'notification.delivered': { en: 'Notification delivered', ar: 'تم تسليم الإشعار' },
  'notification.permanent_failure': { en: 'Notification delivery failed', ar: 'فشل تسليم الإشعار' },
  // Scanning & check-in
  'scan.accepted': { en: 'Scan accepted', ar: 'تم قبول المسح' },
  'scan.rejected': { en: 'Scan rejected', ar: 'تم رفض المسح' },
  'scan.duplicate': { en: 'Duplicate scan detected', ar: 'تم اكتشاف مسح مكرر' },
  'scan.revoked': { en: 'Revoked credential scanned', ar: 'تم مسح اعتماد ملغى' },
  'scan.expired': { en: 'Expired credential scanned', ar: 'تم مسح اعتماد منتهي' },
  'scan.manual_override': { en: 'Scan manually overridden', ar: 'تم تجاوز المسح يدوياً' },
  'offline_scan_batch.received': { en: 'Offline scan batch received', ar: 'تم استلام دفعة مسح بدون اتصال' },
  'offline_scan_batch.processed': { en: 'Offline scan batch processed', ar: 'تمت معالجة دفعة المسح' },
  'offline_scan_batch.conflict_flagged': { en: 'Offline scan batch conflict flagged', ar: 'تم الإبلاغ عن تعارض في دفعة المسح' },
  // Wallet passes
  'wallet_pass.updated': { en: 'Wallet pass updated', ar: 'تم تحديث بطاقة المحفظة' },
  'wallet_pass.update_failed': { en: 'Wallet pass update failed', ar: 'فشل تحديث بطاقة المحفظة' },
  'wallet_pass.revoked': { en: 'Wallet pass revoked', ar: 'تم إلغاء بطاقة المحفظة' },
  'wallet_pass.revocation_failed': { en: 'Wallet pass revocation failed', ar: 'فشل إلغاء بطاقة المحفظة' },
  // Badge printing
  'badge_print.created': { en: 'Badge print created', ar: 'تم إنشاء طباعة بطاقة' },
  'badge_print.printed': { en: 'Badge printed', ar: 'تمت طباعة البطاقة' },
  'badge_print.failed': { en: 'Badge print failed', ar: 'فشلت طباعة البطاقة' },
  'badge_print.reprinted': { en: 'Badge reprinted', ar: 'تمت إعادة طباعة البطاقة' },
  // Badge templates
  'badge_template.created': { en: 'Badge template created', ar: 'تم إنشاء قالب بطاقة' },
  'badge_template.updated': { en: 'Badge template updated', ar: 'تم تحديث قالب بطاقة' },
  'badge_template.activated': { en: 'Badge template activated', ar: 'تم تفعيل قالب بطاقة' },
  'badge_template.deactivated': { en: 'Badge template deactivated', ar: 'تم تعطيل قالب بطاقة' },
  'badge_template.deleted': { en: 'Badge template deleted', ar: 'تم حذف قالب بطاقة' },
  // Kiosks
  'kiosk.paired': { en: 'Kiosk paired', ar: 'تم ربط الكشك' },
  'kiosk.retired': { en: 'Kiosk retired', ar: 'تم إيقاف الكشك' },
  'kiosk.status_changed': { en: 'Kiosk status changed', ar: 'تم تغيير حالة الكشك' },
  // Access control
  'access.authorized': { en: 'Access authorized', ar: 'تم السماح بالدخول' },
  'access.denied': { en: 'Access denied', ar: 'تم رفض الدخول' },
  'access.entry': { en: 'Entry recorded', ar: 'تم تسجيل دخول' },
  'access.exit': { en: 'Exit recorded', ar: 'تم تسجيل خروج' },
  'acs_zone.created': { en: 'ACS zone created', ar: 'تم إنشاء منطقة تحكم' },
  'acs_zone.updated': { en: 'ACS zone updated', ar: 'تم تحديث منطقة تحكم' },
  'acs_lane.created': { en: 'ACS lane created', ar: 'تم إنشاء ممر تحكم' },
  'acs_rule.created': { en: 'ACS rule created', ar: 'تم إنشاء قاعدة تحكم' },
  'acs_integration.credential_registered': { en: 'ACS integration credential registered', ar: 'تم تسجيل اعتماد التكامل' },
  'acs_emergency.raised': { en: 'Emergency raised', ar: 'تم رفع حالة طوارئ' },
  'acs_emergency.cleared': { en: 'Emergency cleared', ar: 'تم رفع حالة الطوارئ' },
  // Identity verification
  'identity_requirement.configured': { en: 'Identity requirement configured', ar: 'تم إعداد متطلبات التحقق' },
  'identity_consent.captured': { en: 'Identity consent captured', ar: 'تم تسجيل موافقة التحقق' },
  'identity_consent.withdrawn': { en: 'Identity consent withdrawn', ar: 'تم سحب موافقة التحقق' },
  'identity_verification.started': { en: 'Identity verification started', ar: 'بدأ التحقق من الهوية' },
  'identity_verification.result_recorded': { en: 'Identity verification result recorded', ar: 'تم تسجيل نتيجة التحقق' },
  'identity_face_capture.submitted': { en: 'Face capture submitted', ar: 'تم إرسال صورة الوجه' },
  'identity_review.approved': { en: 'Identity review approved', ar: 'تمت الموافقة على مراجعة الهوية' },
  'identity_review.rejected': { en: 'Identity review rejected', ar: 'تم رفض مراجعة الهوية' },
  'identity_data.viewed': { en: 'Identity data viewed', ar: 'تم عرض بيانات الهوية' },
  'identity_data.deleted': { en: 'Identity data deleted', ar: 'تم حذف بيانات الهوية' },
  'identity_data.purged': { en: 'Identity data purged', ar: 'تم تطهير بيانات الهوية' },
  // Venue marketplace
  'venue.created': { en: 'Venue created', ar: 'تم إنشاء مكان' },
  'venue.updated': { en: 'Venue updated', ar: 'تم تحديث مكان' },
  'venue.status_changed': { en: 'Venue status changed', ar: 'تم تغيير حالة المكان' },
  'venue.archived': { en: 'Venue archived', ar: 'تم أرشفة المكان' },
  'venue_asset.created': { en: 'Venue asset created', ar: 'تم إنشاء أصل مكان' },
  'venue_asset.updated': { en: 'Venue asset updated', ar: 'تم تحديث أصل مكان' },
  'venue_asset.published': { en: 'Venue asset published', ar: 'تم نشر أصل مكان' },
  'venue_asset.publication_withdrawn': { en: 'Venue asset publication withdrawn', ar: 'تم سحب نشر أصل مكان' },
  'venue_asset.retired': { en: 'Venue asset retired', ar: 'تم إيقاف أصل مكان' },
  'rental.requested': { en: 'Rental requested', ar: 'تم طلب إيجار' },
  'rental.approved': { en: 'Rental approved', ar: 'تمت الموافقة على الإيجار' },
  'rental.rejected': { en: 'Rental rejected', ar: 'تم رفض الإيجار' },
  'rental.cancelled': { en: 'Rental cancelled', ar: 'تم إلغاء الإيجار' },
  'rental.revoked': { en: 'Rental revoked', ar: 'تم سحب الإيجار' },
  'delegation.provisioned': { en: 'Delegation provisioned', ar: 'تم توفير التفويض' },
  'delegation.degraded': { en: 'Delegation degraded', ar: 'تدهور التفويض' },
  'delegation.released': { en: 'Delegation released', ar: 'تم تحرير التفويض' },
  'statement.generated': { en: 'Statement generated', ar: 'تم إنشاء كشف حساب' },
  'statement.revised': { en: 'Statement revised', ar: 'تم تعديل كشف حساب' },
  'statement.exported': { en: 'Statement exported', ar: 'تم تصدير كشف حساب' },
  'dispute.opened': { en: 'Dispute opened', ar: 'تم فتح نزاع' },
  'dispute.resolved': { en: 'Dispute resolved', ar: 'تم حل النزاع' },
  'dispute.rejected': { en: 'Dispute rejected', ar: 'تم رفض النزاع' },
  'dispute.review_started': { en: 'Dispute review started', ar: 'بدأت مراجعة النزاع' },
  'dispute.note_added': { en: 'Dispute note added', ar: 'تمت إضافة ملاحظة للنزاع' },
  // Configuration & audit
  'configuration.updated': { en: 'Configuration updated', ar: 'تم تحديث الإعدادات' },
  'audit.viewed': { en: 'Audit viewed', ar: 'تم عرض سجل التدقيق' },
  'audit.searched': { en: 'Audit searched', ar: 'تم البحث في سجل التدقيق' },
}

export const auditTargetTypeLabels: Record<string, LocalizedLabel> = {
  event: { en: 'Event', ar: 'فعالية' },
  registration_form_version: { en: 'Registration form', ar: 'نموذج تسجيل' },
  ticket_type: { en: 'Ticket type', ar: 'نوع تذكرة' },
  inventory_hold: { en: 'Inventory hold', ar: 'حجز مخزون' },
  order: { en: 'Order', ar: 'طلب' },
  payment_attempt: { en: 'Payment', ar: 'دفع' },
  attendee: { en: 'Attendee', ar: 'حاضر' },
  refund: { en: 'Refund', ar: 'استرداد' },
  credential: { en: 'Credential', ar: 'اعتماد' },
  notification: { en: 'Notification', ar: 'إشعار' },
  scan_event: { en: 'Scan event', ar: 'حدث مسح' },
  offline_scan_reconciliation_batch: { en: 'Offline scan batch', ar: 'دفعة مسح بدون اتصال' },
  wallet_pass: { en: 'Wallet pass', ar: 'بطاقة محفظة' },
  badge_print_job: { en: 'Badge print job', ar: 'مهمة طباعة بطاقة' },
  badge_template: { en: 'Badge template', ar: 'قالب بطاقة' },
  kiosk: { en: 'Kiosk', ar: 'كشك' },
  access_event: { en: 'Access event', ar: 'حدث دخول' },
  emergency_event: { en: 'Emergency event', ar: 'حدث طوارئ' },
  acs_zone: { en: 'ACS zone', ar: 'منطقة تحكم' },
  acs_lane: { en: 'ACS lane', ar: 'ممر تحكم' },
  acs_rule: { en: 'ACS rule', ar: 'قاعدة تحكم' },
  acs_integration_credential: { en: 'ACS integration', ar: 'تكامل تحكم' },
  identity_requirement: { en: 'Identity requirement', ar: 'متطلب تحقق' },
  identity_consent: { en: 'Identity consent', ar: 'موافقة تحقق' },
  identity_verification: { en: 'Identity verification', ar: 'تحقق من الهوية' },
  marketplace_resource: { en: 'Marketplace resource', ar: 'مورد سوق' },
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

export function auditTargetTypeLabel(targetType: string, locale: AppLocale): string {
  return auditTargetTypeLabels[targetType]?.[locale] ?? targetType.replaceAll('_', ' ')
}
