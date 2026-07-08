import { useState } from 'react'
import ConfirmModal from '@/components/modals/ConfirmModal'
import ReasonModal from '@/components/modals/ReasonModal'
import PermissionGate from '@/components/layout/PermissionGate'
import { useLocale } from '@/hooks/useLocale'

type CredentialDialogProps = {
  status: string
  onRevoked?: (reason: string) => void
  onReissued?: () => void
}

export function CredentialDialog({ status, onRevoked, onReissued }: CredentialDialogProps) {
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
            <button type="button" className="button-secondary" onClick={() => setRevokeOpen(true)}>
              {locale === 'ar' ? 'إلغاء بيانات الدخول' : 'Revoke credential'}
            </button>
          )}
        </PermissionGate>
        <PermissionGate permission="credential.reissue">
          {(status === 'revoked' || status === 'expired') && (
            <button type="button" className="button-primary" onClick={() => setReissueOpen(true)}>
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
        onConfirm={(reason) => {
          onRevoked?.(reason)
          setRevokeOpen(false)
        }}
        onCancel={() => setRevokeOpen(false)}
      />

      <ConfirmModal
        open={reissueOpen}
        title={locale === 'ar' ? 'إعادة إصدار بيانات الدخول' : 'Reissue credential'}
        message={locale === 'ar' ? 'سيتم إنشاء بيانات دخول جديدة مرتبطة بالسابقة.' : 'A new credential will be issued and linked to the prior one.'}
        confirmLabel={locale === 'ar' ? 'إعادة الإصدار' : 'Reissue'}
        cancelLabel={locale === 'ar' ? 'إلغاء' : 'Cancel'}
        onConfirm={() => {
          onReissued?.()
          setReissueOpen(false)
        }}
        onCancel={() => setReissueOpen(false)}
      />
    </section>
  )
}
