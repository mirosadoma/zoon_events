import ReasonModal from '@/components/modals/ReasonModal'
import { useLocale } from '@/hooks/useLocale'

type Props = {
  open: boolean
  kind: 'reject' | 'revoke' | 'dispute'
  onConfirm: (reason: string) => void
  onCancel: () => void
  loading?: boolean
}

export default function DecisionReasonDialog({ open, kind, onConfirm, onCancel, loading = false }: Props) {
  const { t } = useLocale()

  const titleMap = {
    reject: t('rejectRental'),
    revoke: t('revokeRental'),
    dispute: t('openDispute'),
  }

  const messageMap = {
    reject: t('rejectRentalReason'),
    revoke: t('revokeRentalReason'),
    dispute: t('disputeReason'),
  }

  return (
    <ReasonModal
      open={open}
      title={titleMap[kind]}
      message={messageMap[kind]}
      reasonLabel={t('reason')}
      confirmLabel={t('confirm')}
      cancelLabel={t('cancel')}
      onConfirm={onConfirm}
      onCancel={onCancel}
      loading={loading}
    />
  )
}
