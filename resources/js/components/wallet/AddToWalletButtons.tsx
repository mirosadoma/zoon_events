import { useTranslation } from 'react-i18next'

export default function AddToWalletButtons({
  locale,
  applePassUrl,
  googleSaveUrl,
  credentialStatus,
}: {
  locale: 'en' | 'ar'
  applePassUrl: string
  googleSaveUrl: string
  credentialStatus: string
}) {
  const { t } = useTranslation()
  const isActive = credentialStatus === 'active'

  if (!isActive) {
    return null
  }

  return (
    <div lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <a href={applePassUrl}>{t('phase2.wallet_pass.added')}</a>
      <a href={googleSaveUrl}>{t('phase2.wallet_pass.added')}</a>
    </div>
  )
}
