const copy = {
  en: {
    action_required: 'Continue to secure payment',
    pending: 'Payment is pending',
    failed: 'Payment failed',
    unknown: 'Payment status is being verified',
    captured: 'Payment completed',
  },
  ar: {
    action_required: 'المتابعة إلى الدفع الآمن',
    pending: 'الدفع قيد الانتظار',
    failed: 'تعذر الدفع',
    unknown: 'يجري التحقق من حالة الدفع',
    captured: 'اكتمل الدفع',
  },
} as const

export type PaymentUiState = keyof typeof copy.en

export function PaymentState({ locale, state }: { locale: 'en' | 'ar'; state: PaymentUiState }) {
  return <p role="status" aria-live="polite">{copy[locale][state]}</p>
}
