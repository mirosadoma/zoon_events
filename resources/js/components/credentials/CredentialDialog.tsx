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
  const { locale } = useLocale()
  const [revokeOpen, setRevokeOpen] = useState(false)
  const [reissueOpen, setReissueOpen] = useState(false)

  const canAct = status === 'active' || status === 'pending'

  return (
    <section className="state-panel mt-6" aria-labelledby="credential-actions">
      <h2 id="credential-actions" className="text-lg font-semibold">
        {locale === 'ar' ? 'إجراءات بيانات الدخول' : 'Credential actions'}
      </h2>
      <div className="mt-4 flex flex-wrap gap-2">
        <PermissionGate permission="credential.revoke">
          {canAct && (
            <button type="button" className="button-secondary" onClick={() => setRevokeOpen(true)} disabled={loading}>
              {locale === 'ar' ? 'إلغاء بيانات الدخول' : 'Revoke credential'}
            </button>
          )}
        </PermissionGate>
        <PermissionGate permission="credential.reissue">
          {(status === 'revoked' || status === 'expired') && (
            <button type="button" className="button-primary" onClick={() => setReissueOpen(true)} disabled={loading}>
              {locale === 'ar' ? 'إعادة الإصدار' : 'Reissue credential'}
            </button>
          )}
        </PermissionGate>
      </div>

      <ReasonModal
        open={revokeOpen}
        title={locale === 'ar' ? 'إلغاء بيانات الدخول' : 'Revoke credential'}
        message={locale === 'ar' ? 'سيتم إيقاف بيانات الدخول فورًا.' : 'This will immediately invalidate the credential.'}
        reasonLabel={locale === 'ar' ? 'السبب' : 'Reason'}
        confirmLabel={locale === 'ar' ? 'تأكيد الإلغاء' : 'Confirm revoke'}
        cancelLabel={locale === 'ar' ? 'إلغاء' : 'Cancel'}
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
        title={locale === 'ar' ? 'إعادة إصدار بيانات الدخول' : 'Reissue credential'}
        message={locale === 'ar' ? 'سيتم إنشاء بيانات دخول جديدة مرتبطة بالسابقة.' : 'A new credential will be issued and linked to the prior one.'}
        reasonLabel={locale === 'ar' ? 'السبب' : 'Reason'}
        confirmLabel={locale === 'ar' ? 'إعادة الإصدار' : 'Reissue'}
        cancelLabel={locale === 'ar' ? 'إلغاء' : 'Cancel'}
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
