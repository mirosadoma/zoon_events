import { Wallet } from 'lucide-react'
import { useLocale } from '@/hooks/useLocale'

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
  const { t } = useLocale()
  const isActive = credentialStatus === 'active'
  const direction = locale === 'ar' ? 'rtl' : 'ltr'

  if (!isActive) {
    return null
  }

  return (
    <div className="registration-wallet-actions" lang={locale} dir={direction}>
      <p className="registration-wallet-heading">{t('walletAddPassTitle')}</p>
      <div className="registration-wallet-buttons">
        <a
          href={applePassUrl}
          className="registration-wallet-button registration-wallet-button-apple"
        >
          <Wallet className="h-4 w-4" aria-hidden="true" />
          <span>{t('walletAddToApple')}</span>
        </a>
        <a
          href={googleSaveUrl}
          className="registration-wallet-button registration-wallet-button-google"
        >
          <Wallet className="h-4 w-4" aria-hidden="true" />
          <span>{t('walletAddToGoogle')}</span>
        </a>
      </div>
    </div>
  )
}
