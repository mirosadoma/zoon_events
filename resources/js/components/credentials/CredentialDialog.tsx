import { useState } from 'react'
import ReasonModal from '@/components/modals/ReasonModal'
import PermissionGate from '@/components/layout/PermissionGate'
import { useLocale } from '@/hooks/useLocale'

type CredentialDialogProps = {
  status: string
  loading?: boolean
  onRevoked?: (reason: string) => Promise<boolean> | boolean
  onReissued?: (reason: string) => Promise<boolean> | boolean
}

export function CredentialDialog({ status, loading = false, onRevoked, onReissued }: CredentialDialogProps) {
  const { locale, t } = useLocale()
  const [revokeOpen, setRevokeOpen] = useState(false)
  const [reissueOpen, setReissueOpen] = useState(false)

  const canAct = status === 'active' || status === 'pending'

  return (
    <section className="state-panel mt-6" aria-labelledby="credential-actions">
      <h2 id="credential-actions" className="text-lg font-semibold">
        {t('credentialDialogTitle')}
      </h2>
      <div className="mt-4 flex flex-wrap gap-2">
        <PermissionGate permission="credential.revoke">
          {canAct && (
            <button type="button" className="button-secondary" onClick={() => setRevokeOpen(true)} disabled={loading}>
              {t('credentialDialogRevoke')}
            </button>
          )}
        </PermissionGate>
        <PermissionGate permission="credential.reissue">
          {(status === 'revoked' || status === 'expired') && (
            <button type="button" className="button-primary" onClick={() => setReissueOpen(true)} disabled={loading}>
              {t('credentialDialogReissue')}
            </button>
          )}
        </PermissionGate>
      </div>

      <ReasonModal
        open={revokeOpen}
        title={t('credentialDialogRevokeTitle')}
        message={t('credentialDialogRevokeMessage')}
        reasonLabel={t('credentialDialogReason')}
        confirmLabel={t('credentialDialogRevokeConfirm')}
        cancelLabel={t('cancel')}
        loading={loading}
        onConfirm={async (reason) => {
          const success = await onRevoked?.(reason)
          if (success !== false) {
            setRevokeOpen(false)
          }
        }}
        onCancel={() => setRevokeOpen(false)}
      />

      <ReasonModal
        open={reissueOpen}
        title={t('credentialDialogReissueTitle')}
        message={t('credentialDialogReissueMessage')}
        reasonLabel={t('credentialDialogReason')}
        confirmLabel={t('credentialDialogReissueButton')}
        cancelLabel={t('cancel')}
        loading={loading}
        onConfirm={async (reason) => {
          const success = await onReissued?.(reason)
          if (success !== false) {
            setReissueOpen(false)
          }
        }}
        onCancel={() => setReissueOpen(false)}
      />
    </section>
  )
}
