import { CheckCircle2 } from 'lucide-react'
import { QRCodeSVG } from 'qrcode.react'
import RegistrationPageControls from '@/components/registration/RegistrationPageControls'
import AddToWalletButtons from '@/components/wallet/AddToWalletButtons'
import { useLocale } from '@/hooks/useLocale'

export default function Confirmation({
  locale,
  reference,
  eventName,
  attendeeName = 'Participant',
  qrPayload,
  accessToken,
  applePassUrl: applePassUrlProp,
  googleSaveUrl: googleSaveUrlProp,
  credentialStatus = 'active',
}: {
  locale: 'en' | 'ar'
  reference: string
  eventName?: string
  attendeeName?: string
  qrPayload?: string | null
  accessToken?: string
  applePassUrl?: string | null
  googleSaveUrl?: string | null
  credentialStatus?: string
}) {
  const { t, direction } = useLocale()
  const applePassUrl = applePassUrlProp
    ?? (accessToken
      ? `/api/v1/public/orders/${reference}/wallet-passes/apple?access_token=${encodeURIComponent(accessToken)}`
      : null)
  const googleSaveUrl = googleSaveUrlProp
    ?? (accessToken
      ? `/api/v1/public/orders/${reference}/wallet-passes/google?access_token=${encodeURIComponent(accessToken)}`
      : null)
  const showWallet = Boolean(applePassUrl && googleSaveUrl)

  return (
    <>
      <RegistrationPageControls locale={locale} />
      <main className="registration-invite registration-invite-success" lang={locale} dir={direction}>
        <div className="registration-invite-card registration-confirmation-card">
          <div className="registration-confirmation-badge" aria-hidden="true">
            <CheckCircle2 className="h-8 w-8" />
          </div>

          <p className="registration-invite-kicker">{t('publicRegistrationSuccessKicker')}</p>
          <h1>{t('publicRegistrationWelcomeName').replace(':name', attendeeName)}</h1>

          {eventName ? (
            <p className="registration-invite-lead">
              {t('publicRegistrationThankYouRegistering')}{' '}
              <strong>{eventName}</strong>.
            </p>
          ) : (
            <p className="registration-invite-lead">{t('publicRegistrationConfirmationReceived')}</p>
          )}

          {qrPayload ? (
            <div className="registration-qr-panel">
              <p>{t('publicRegistrationScanQr')}</p>
              <div className="registration-qr-code">
                <QRCodeSVG value={qrPayload} size={240} level="M" includeMargin />
              </div>
              <p className="registration-confirmation-qr-hint">{t('publicRegistrationKeepQr')}</p>
            </div>
          ) : null}

          <div className="registration-success-meta">
            <div>
              <span className="registration-success-label">{t('publicRegistrationOrderReference')}</span>
              <strong className="font-mono">{reference}</strong>
            </div>
          </div>

          {showWallet ? (
            <div className="registration-confirmation-wallet">
              <AddToWalletButtons
                locale={locale}
                applePassUrl={applePassUrl!}
                googleSaveUrl={googleSaveUrl!}
                credentialStatus={credentialStatus}
              />
            </div>
          ) : null}

          <p className="registration-invite-footnote">
            {t('publicRegistrationEmailFootnote')}
          </p>
        </div>
      </main>
    </>
  )
}
