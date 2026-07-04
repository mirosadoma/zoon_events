import { CredentialDialog } from '@/components/credentials/CredentialDialog'

export default function Credentials({ locale = 'en', canRevoke, canReissue }: { locale?: 'en' | 'ar'; canRevoke: boolean; canReissue: boolean }) {
  return (
    <main lang={locale} dir={locale === 'ar' ? 'rtl' : 'ltr'}>
      <h1>{locale === 'ar' ? 'الاعتمادات' : 'Credentials'}</h1>
      <CredentialDialog locale={locale} canRevoke={canRevoke} canReissue={canReissue} />
    </main>
  )
}
