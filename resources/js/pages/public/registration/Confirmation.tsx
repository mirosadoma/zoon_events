import { QRCodeSVG } from 'qrcode.react'
import AddToWalletButtons from '@/components/wallet/AddToWalletButtons'

export default function Confirmation({
  locale,
  reference,
  eventName,
  attendeeName = 'Participant',
  qrPayload,
  accessToken,
  credentialStatus = 'active',
}: {
  locale: 'en' | 'ar'
  reference: string
  eventName?: string
  attendeeName?: string
  qrPayload?: string | null
  accessToken?: string
  credentialStatus?: string
}) {
  const rtl = locale === 'ar'
  const applePassUrl = accessToken ? `/api/v1/public/orders/${reference}/wallet-passes/apple` : '#'
  const googleSaveUrl = accessToken ? `/api/v1/public/orders/${reference}/wallet-passes/google` : '#'

  return (
    <main className="registration-confirmation-page min-h-screen bg-[var(--surface)] px-4 py-10" lang={locale} dir={rtl ? 'rtl' : 'ltr'}>
      <div className="registration-invite-card mx-auto max-w-2xl">
        <h1 className="text-3xl font-semibold text-[var(--ink)]">
          {rtl ? `مرحباً ${attendeeName}،` : `Welcome ${attendeeName},`}
        </h1>
        <p className="mt-4 text-[var(--muted)]">{rtl ? 'عزيزي المشارك،' : 'Dear Participant,'}</p>
        <p className="mt-4 leading-7 text-[var(--ink)]">
          {rtl ? 'شكراً لتسجيلك في ' : 'Thank you for registering for '}
          <strong>{eventName ?? (rtl ? 'الفعالية' : 'the event')}</strong>.
        </p>
        <p className="mt-4 leading-7 text-[var(--ink)]">
          {rtl
            ? 'يسعدنا تأكيد استلام تسجيلك بنجاح.'
            : 'We are pleased to confirm that your registration has been successfully received.'}
        </p>
        <p className="mt-4 leading-7 text-[var(--ink)]">
          {rtl
            ? 'يرجى الاحتفاظ برمز QR أدناه وإبرازه عند مكتب التسجيل عند الوصول.'
            : 'Please keep the QR code below accessible and present it at the registration desk upon arrival.'}
        </p>

        {qrPayload ? (
          <div className="mt-8 flex justify-center">
            <div className="registration-qr-code rounded-xl border border-[var(--border)] bg-white p-4 shadow-sm">
              <QRCodeSVG value={qrPayload} size={280} level="M" includeMargin />
            </div>
          </div>
        ) : null}

        <p className="mt-8 text-sm text-[var(--muted)]">
          {rtl ? 'مرجع الطلب' : 'Order reference'}: <span className="font-mono text-[var(--ink)]">{reference}</span>
        </p>

        {accessToken ? (
          <div className="mt-6">
            <AddToWalletButtons
              locale={locale}
              applePassUrl={applePassUrl}
              googleSaveUrl={googleSaveUrl}
              credentialStatus={credentialStatus}
            />
          </div>
        ) : null}
      </div>
    </main>
  )
}
