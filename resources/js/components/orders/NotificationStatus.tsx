import en from '@/locales/en'
import ar from '@/locales/ar'

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
  const messages = locale === 'ar' ? ar : en
  const unavailable = messages.notificationUnavailable
  const label = messages.notificationDeliveryStatus
  return <span role="status" aria-label={label}>{safeLabels[locale][status] ?? unavailable}</span>
}
