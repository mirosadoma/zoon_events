import { Head, Link, router, usePage } from '@inertiajs/react'
import type { PropsWithChildren } from 'react'
import en from '@/locales/en'
import ar from '@/locales/ar'
import { useLocale } from '@/hooks/useLocale'
import { useTheme } from '@/hooks/useTheme'
import { platformNavigation } from '@/lib/navigation'

type SharedProps = {
  auth?: { user?: { name: string; email: string } | null }
  permissions?: string[]
}

export default function FoundationLayout({ children }: PropsWithChildren) {
  const { locale, direction } = useLocale()
  const { theme, setTheme } = useTheme()
  const page = usePage<SharedProps>()
  const messages = locale === 'ar' ? ar : en
  const permissions = page.props.permissions || []

  return (
    <div dir={direction} lang={locale} className="min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50">
      <Head title={messages.appName} />
      <a href="#main-content" className="skip-link">Skip to content</a>
      <div className="mx-auto grid min-h-screen lg:grid-cols-[17rem_1fr]">
        <aside className="border-b border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 lg:border-b-0 lg:border-e">
          <strong className="text-lg">{messages.appName}</strong>
          <nav aria-label="Foundation navigation" className="mt-6 grid grid-cols-2 gap-2 sm:grid-cols-4 lg:grid-cols-1">
            {platformNavigation.filter((item) => item.permission === null || permissions.includes(item.permission)).map((item) => (
              <Link key={item.href} href={item.href} className="rounded-lg px-3 py-2 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 dark:hover:bg-slate-800">
                {messages[item.label]}
              </Link>
            ))}
          </nav>
        </aside>
        <div>
          <header className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-200 bg-white px-6 py-4 dark:border-slate-800 dark:bg-slate-900">
            <span dir="auto" className="font-medium">{page.props.auth?.user?.name}</span>
            <div className="flex gap-2">
              <label><span className="sr-only">Theme</span><select value={theme} onChange={(event) => setTheme(event.target.value as typeof theme)} className="control"><option value="system">System</option><option value="light">Light</option><option value="dark">Dark</option></select></label>
              <button type="button" className="button-secondary" onClick={() => router.post('/logout')}>{messages.logout}</button>
            </div>
          </header>
          <main id="main-content" tabIndex={-1} className="p-4 sm:p-6 lg:p-8">{children}</main>
        </div>
      </div>
    </div>
  )
}
