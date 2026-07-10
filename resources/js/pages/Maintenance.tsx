import { Head } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { Construction, LogIn } from 'lucide-react'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'

type Props = {
  messageEn: string | null
  messageAr: string | null
  appNameEn: string
  appNameAr: string
}

export default function Maintenance({ messageEn, messageAr, appNameEn, appNameAr }: Props) {
  const { locale, direction, localizedPath } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const appName = locale === 'ar' ? appNameAr : appNameEn
  const message = locale === 'ar'
    ? (messageAr || messages.maintenanceDefault)
    : (messageEn || messages.maintenanceDefault)

  return (
    <main dir={direction} lang={locale} className="grid min-h-screen place-items-center bg-[var(--surface)] p-6">
      <Head title={messages.maintenanceTitle} />
      <section className="ta-card w-full max-w-xl space-y-6 p-10 text-center landing-fade-in">
        <div className="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-[var(--warning-soft)] text-[var(--warning)]">
          <Construction className="h-8 w-8" />
        </div>
        <div>
          <p className="text-sm font-semibold uppercase tracking-wider text-[var(--brand)]">{appName}</p>
          <h1 className="mt-2 text-3xl font-bold">{messages.maintenanceTitle}</h1>
        </div>
        <p className="whitespace-pre-wrap text-[var(--muted)]">{message}</p>
        <LocalizedLink href={localizedPath('/login')} className="button-primary inline-flex items-center gap-2">
          <LogIn className="h-4 w-4" />
          {messages.loginTitle}
        </LocalizedLink>
      </section>
    </main>
  )
}
