import { useState } from 'react'

export function CredentialDialog({
  locale,
  canRevoke,
  canReissue,
}: {
  locale: 'en' | 'ar'
  canRevoke: boolean
  canReissue: boolean
}) {
  const [reason, setReason] = useState('')

  return (
    <section lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'} aria-labelledby="credential-actions">
      <h2 id="credential-actions">{locale === 'ar' ? 'إجراءات الاعتماد' : 'Credential actions'}</h2>
      <label>
        {locale === 'ar' ? 'السبب' : 'Reason'}
        <textarea value={reason} onChange={(event) => setReason(event.target.value)} required />
      </label>
      {canRevoke && <button disabled={!reason}>{locale === 'ar' ? 'إلغاء' : 'Revoke'}</button>}
      {canReissue && <button disabled={!reason}>{locale === 'ar' ? 'إعادة إصدار' : 'Reissue'}</button>}
    </section>
  )
}
