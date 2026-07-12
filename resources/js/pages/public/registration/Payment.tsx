import { PaymentState, type PaymentUiState } from '@/components/orders/PaymentState'
import { useLocale } from '@/hooks/useLocale'
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
  const { t, direction } = useLocale()

  return (
    <main lang={locale} dir={direction}>
      <h1>{t('publicRegistrationPaymentTitle')}</h1>
      <p>{t('publicRegistrationTotal')}: {formatMoney(totalMinor, currency, locale)}</p>
      <PaymentState locale={locale} state={state} />
      {state === 'action_required' && actionUrl && (
        <a href={actionUrl}>{t('publicRegistrationPaymentContinue')}</a>
      )}
    </main>
  )
}
