import { useState } from 'react'
import ConfirmModal from '@/components/modals/ConfirmModal'
import StatusBadge from '@/components/status/StatusBadge'
import PermissionGate from '@/components/layout/PermissionGate'
import { useLocale } from '@/hooks/useLocale'
import type { PublicationStatus, VenueAssetRow } from '@/types/phase6'

type Props = {
  asset: VenueAssetRow
  readiness?: string[]
  onPublish?: () => void
  onWithdraw?: () => void
  conflictRentalHref?: string | null
  readOnly?: boolean
}

export default function PublicationPanel({
  asset,
  readiness = [],
  onPublish,
  onWithdraw,
  conflictRentalHref = null,
  readOnly = false,
}: Props) {
  const { t } = useLocale()
  const [confirmAction, setConfirmAction] = useState<'publish' | 'withdraw' | null>(null)
  const publicationStatus = (asset.publication_status ?? 'private') as PublicationStatus
  const hasConflict = Boolean(conflictRentalHref)

  return (
    <section className="ta-card space-y-4" aria-label={t('publication')}>
      <div className="flex flex-wrap items-center gap-2">
        <h3 className="text-lg font-semibold text-[var(--ink)]">{t('publication')}</h3>
        <StatusBadge status={publicationStatus} />
      </div>

      <div>
        <h4 className="text-sm font-medium text-[var(--ink)]">{t('publicationReadiness')}</h4>
        {readiness.length === 0 ? (
          <p className="text-sm text-[var(--muted)]">{t('overviewHealthy')}</p>
        ) : (
          <ul className="mt-2 list-disc ps-5 text-sm text-[var(--muted)]">
            {readiness.map((item) => (
              <li key={item}>{item}</li>
            ))}
          </ul>
        )}
      </div>

      {hasConflict && conflictRentalHref ? (
        <p className="text-sm text-amber-700" role="alert">
          Future approved rental conflict —{' '}
          <a href={conflictRentalHref} className="font-medium underline">
            {t('rentalDetails')}
          </a>
        </p>
      ) : null}

      {!readOnly ? (
        <PermissionGate permission="venue.manage">
          <div className="flex flex-wrap gap-2">
            <button
              type="button"
              className="button-primary"
              disabled={hasConflict || publicationStatus === 'published'}
              onClick={() => setConfirmAction('publish')}
            >
              {t('publishAsset')}
            </button>
            <button
              type="button"
              className="button-secondary"
              disabled={publicationStatus !== 'published'}
              onClick={() => setConfirmAction('withdraw')}
            >
              {t('withdrawAsset')}
            </button>
          </div>
        </PermissionGate>
      ) : null}

      <ConfirmModal
        open={confirmAction === 'publish'}
        title={t('publishAsset')}
        message={t('confirm')}
        confirmLabel={t('publishAsset')}
        cancelLabel={t('cancel')}
        onConfirm={() => {
          onPublish?.()
          setConfirmAction(null)
        }}
        onCancel={() => setConfirmAction(null)}
      />

      <ConfirmModal
        open={confirmAction === 'withdraw'}
        title={t('withdrawAsset')}
        message={t('confirm')}
        confirmLabel={t('withdrawAsset')}
        cancelLabel={t('cancel')}
        onConfirm={() => {
          onWithdraw?.()
          setConfirmAction(null)
        }}
        onCancel={() => setConfirmAction(null)}
      />
    </section>
  )
}
