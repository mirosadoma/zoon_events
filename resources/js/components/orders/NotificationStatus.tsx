const safeLabels: Record<'en' | 'ar', Record<string, string>> = {
  en: {
    pending: 'Queued',
    processing: 'Sending',
    sent: 'Sent',
    delivered: 'Delivered',
    temporary_failure: 'Retrying',
    permanent_failure: 'Delivery failed',
  },
  ar: {
    pending: 'في قائمة الانتظار',
    processing: 'جارٍ الإرسال',
    sent: 'تم الإرسال',
    delivered: 'تم التسليم',
    temporary_failure: 'ستتم إعادة المحاولة',
    permanent_failure: 'فشل التسليم',
  },
}

export function NotificationStatus({ status, locale = 'en' }: { status: string; locale?: 'en' | 'ar' }) {
  const unavailable = locale === 'ar' ? 'غير متاح' : 'Unavailable'
  const label = locale === 'ar' ? 'حالة إرسال التأكيد' : 'Confirmation delivery status'
  return <span role="status" aria-label={label}>{safeLabels[locale][status] ?? unavailable}</span>
}
