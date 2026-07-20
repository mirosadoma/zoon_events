import type { AppLocale } from '@/lib/localePath'

const SCAN_REASON_LABELS: Record<string, Record<AppLocale, string>> = {
  allowed: { en: 'Entry allowed', ar: 'تم السماح بالدخول' },
  entry_granted: { en: 'Entry granted', ar: 'تم منح الدخول' },
  credential_expired: { en: 'Credential has expired', ar: 'انتهت صلاحية بيانات الدخول' },
  credential_revoked: { en: 'Credential has been revoked', ar: 'تم إلغاء بيانات الدخول' },
  credential_unknown: { en: 'Credential is unknown or invalid', ar: 'بيانات الدخول غير معروفة أو غير صالحة' },
  zone_not_permitted: { en: 'Credential is not permitted in this zone', ar: 'بيانات الدخول غير مسموح بها في هذه المنطقة' },
  lane_not_permitted: { en: 'Credential is not permitted at this lane', ar: 'بيانات الدخول غير مسموح بها في هذا المسار' },
  outside_time_window: { en: 'Access is outside the permitted time window', ar: 'الوصول خارج نافذة الوقت المسموح بها' },
  anti_passback_violation: { en: 'Anti-passback violation: re-entry without exit', ar: 'انتهاك منع العودة: إعادة دخول دون خروج' },
  acs_unavailable_fail_open: { en: 'ACS unavailable; zone configured to fail open', ar: 'ACS غير متاح؛ المنطقة مضبوطة على السماح عند التعطل' },
  acs_unavailable_fail_closed: { en: 'ACS unavailable; zone configured to fail closed', ar: 'ACS غير متاح؛ المنطقة مضبوطة على الرفض عند التعطل' },
  emergency_fail_open: { en: 'Emergency egress active; entry allowed', ar: 'خروج الطوارئ نشط؛ تم السماح بالدخول' },
  offline_conflict_resolution: { en: 'Offline scan conflict resolved', ar: 'تم حل تعارض المسح غير المتصل' },
  identity_pending: { en: 'Identity verification pending', ar: 'التحقق من الهوية قيد الانتظار' },
  duplicate_entry: { en: 'Duplicate entry attempt', ar: 'محاولة دخول مكررة' },
  already_checked_in: { en: 'Already checked in', ar: 'تم تسجيل الحضور مسبقاً' },
  credential_invalid: { en: 'Credential is invalid', ar: 'بيانات الدخول غير صالحة' },
  order_reference_not_found: {
    en: 'Order reference not found for this event',
    ar: 'مرجع الطلب غير موجود لهذه الفعالية',
  },
  order_reference_wrong_event: {
    en: 'This order belongs to a different event',
    ar: 'هذا الطلب يخص فعالية أخرى',
  },
}

export function scanReasonLabel(reason: string | null | undefined, locale: AppLocale): string {
  if (!reason) return '—'

  const normalized = reason.toLowerCase().replace(/\s+/g, '_')
  const match = SCAN_REASON_LABELS[normalized]

  if (match) {
    return match[locale]
  }

  return reason.replace(/_/g, ' ')
}

export function checkinStatusLabel(status: string | null | undefined, locale: AppLocale): string {
  if (!status) return '—'

  const labels: Record<string, Record<AppLocale, string>> = {
    not_checked_in: { en: 'Not checked in', ar: 'لم يُسجَّل الحضور' },
    checked_in: { en: 'Checked in', ar: 'تم تسجيل الحضور' },
    rejected: { en: 'Rejected', ar: 'مرفوض' },
  }

  const normalized = status.toLowerCase().replace(/\s+/g, '_')

  return labels[normalized]?.[locale] ?? status.replace(/_/g, ' ')
}
