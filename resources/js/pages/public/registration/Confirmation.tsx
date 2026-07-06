import AddToWalletButtons from '@/components/wallet/AddToWalletButtons'

export default function Confirmation({
  locale,
  reference,
  accessToken,
  credentialStatus = 'active',
}: {
  locale: 'en' | 'ar'
  reference: string
  accessToken?: string
  credentialStatus?: string
}) {
  const applePassUrl = accessToken
    ? `/api/v1/public/orders/${reference}/wallet-passes/apple`
    : '#'
  const googleSaveUrl = accessToken
    ? `/api/v1/public/orders/${reference}/wallet-passes/google`
    : '#'

  return (
    <main lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <h1>{locale === 'ar' ? 'اكتمل التسجيل' : 'Registration complete'}</h1>
      <p>{locale === 'ar' ? 'مرجع الطلب' : 'Order reference'}: {reference}</p>
      {accessToken ? (
        <AddToWalletButtons
          locale={locale}
          applePassUrl={applePassUrl}
          googleSaveUrl={googleSaveUrl}
          credentialStatus={credentialStatus}
        />
      ) : null}
    </main>
  )
}
