import { router, usePage } from '@inertiajs/react'
import { useLocale } from '@/hooks/useLocale'
import { useTheme } from '@/hooks/useTheme'
import en from '@/locales/en'
import ar from '@/locales/ar'
import type { SessionContext } from '@/types/shell'

type PageProps = {
  session?: SessionContext | null
}

export default function Topbar() {
  const { locale } = useLocale()
  const { theme, setTheme } = useTheme()
  const page = usePage<PageProps>()
  const messages = locale === 'ar' ? ar : en
  const session = page.props.session

  const toggleLocale = () => {
    const next = locale === 'ar' ? 'en' : 'ar'
    document.cookie = `locale=${next};path=/;max-age=31536000`
    router.reload({ only: ['locale', 'direction', 'session'] })
  }

  return (
    <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
      <div className="flex flex-wrap items-center gap-3">
        <span dir="auto" className="font-medium">{session?.user.name}</span>
        {session?.tenant && (
          <span className="rounded-full bg-teal-100 px-2.5 py-0.5 text-xs font-medium text-teal-900 dark:bg-teal-900/40 dark:text-teal-100">
            {session.tenant.name}
          </span>
        )}
        {session?.role_label && (
          <span className="rounded-full bg-slate-100 px-2.5 py-0.5 text-xs text-slate-700 dark:bg-slate-800 dark:text-slate-200">
            {session.role_label}
          </span>
        )}
      </div>
      <div className="flex flex-wrap items-center gap-2">
        <button type="button" className="button-secondary" onClick={toggleLocale} aria-label={messages.toggleLocale}>
          {locale === 'ar' ? 'EN' : 'ع'}
        </button>
        <label>
          <span className="sr-only">{messages.theme}</span>
          <select
            value={theme}
            onChange={(event) => setTheme(event.target.value as typeof theme)}
            className="control"
          >
            <option value="system">{messages.themeSystem}</option>
            <option value="light">{messages.themeLight}</option>
            <option value="dark">{messages.themeDark}</option>
          </select>
        </label>
        <button type="button" className="button-secondary" onClick={() => router.post('/logout')}>
          {messages.logout}
        </button>
      </div>
    </header>
  )
}
