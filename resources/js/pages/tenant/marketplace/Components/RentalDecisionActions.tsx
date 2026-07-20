import PermissionGate from '@/components/layout/PermissionGate'
import { useLocale } from '@/hooks/useLocale'
import type { RentalStatus } from '@/types/phase6'

type Props = {
  status: RentalStatus
  viewerRole: 'owner' | 'organizer'
  onApprove?: () => void
  onReject?: () => void
  onRevoke?: () => void
  onCancel?: () => void
  busy?: boolean
}

export default function RentalDecisionActions({
  status,
  viewerRole,
  onApprove,
  onReject,
  onRevoke,
  onCancel,
  busy = false,
}: Props) {
  const { t } = useLocale()

  const ownerCanDecide = viewerRole === 'owner' && status === 'requested'
  const ownerCanRevoke = viewerRole === 'owner' && (status === 'approved' || status === 'active')
  const organizerCanCancel = viewerRole === 'organizer' && (status === 'requested' || status === 'approved')

  if (!ownerCanDecide && !ownerCanRevoke && !organizerCanCancel) {
    return null
  }

  return (
    <div className="flex flex-wrap gap-2" aria-label={t('actions')}>
      {ownerCanDecide ? (
        <PermissionGate permission="rentals.approve">
          <button type="button" className="button-primary" disabled={busy} onClick={onApprove}>
            {t('approveRental')}
          </button>
          <button type="button" className="button-secondary" disabled={busy} onClick={onReject}>
            {t('rejectRental')}
          </button>
        </PermissionGate>
      ) : null}
      {ownerCanRevoke ? (
        <PermissionGate permission="rentals.approve">
          <button type="button" className="button-secondary" disabled={busy} onClick={onRevoke}>
            {t('revokeRental')}
          </button>
        </PermissionGate>
      ) : null}
      {organizerCanCancel ? (
        <PermissionGate permission="marketplace.manage">
          <button type="button" className="button-secondary" disabled={busy} onClick={onCancel}>
            {t('cancelRental')}
          </button>
        </PermissionGate>
      ) : null}
    </div>
  )
}
