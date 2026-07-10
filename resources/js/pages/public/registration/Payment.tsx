import { PaymentState, type PaymentUiState } from '@/components/orders/PaymentState'
import { formatMoney } from '@/lib/formatMoney'

export default function Payment({
  locale,
  totalMinor,
  currency,
  state,
  actionUrl,
}: {
  locale: 'en' | 'ar'
  totalMinor: number
  currency: string
  state: PaymentUiState
  actionUrl?: string
}) {
  return (
    <main lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <h1>{locale === 'ar' ? 'الدفع' : 'Payment'}</h1>
      <p>{locale === 'ar' ? 'الإجمالي' : 'Total'}: {formatMoney(totalMinor, currency, locale)}</p>
      <PaymentState locale={locale} state={state} />
      {state === 'action_required' && actionUrl && (
        <a href={actionUrl}>{locale === 'ar' ? 'متابعة الدفع' : 'Continue payment'}</a>
      )}
    </main>
  )
}
