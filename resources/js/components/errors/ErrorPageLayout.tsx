import { Head, usePage } from '@inertiajs/react'
import { ArrowLeft, Home, LogIn, RefreshCw } from 'lucide-react'
import type { ReactNode } from 'react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { useLocale } from '@/hooks/useLocale'
import type { ErrorPageTone } from '@/lib/errorPageCatalog'

type SiteSettings = {
  app_name_en?: string
  app_name_ar?: string
}

type Action = {
  label: string
  href?: string
  onClick?: () => void
  icon?: 'home' | 'refresh' | 'back' | 'login'
  variant?: 'primary' | 'secondary'
}

type Props = {
  statusCode: number
  title: string
  description: string
  illustration: ReactNode
  tone?: ErrorPageTone
  primaryAction: Action
  secondaryAction?: Action
}

function ActionButton({ action }: { action: Action }) {
  const Icon = action.icon === 'refresh'
    ? RefreshCw
    : action.icon === 'back'
      ? ArrowLeft
      : action.icon === 'login'
        ? LogIn
        : Home
  const className = action.variant === 'secondary'
    ? 'button-secondary inline-flex items-center gap-2'
    : 'button-primary inline-flex items-center gap-2'

  if (action.href) {
    return (
      <LocalizedLink href={action.href} className={className}>
        <Icon className="h-4 w-4" />
        {action.label}
      </LocalizedLink>
    )
  }

  return (
    <button type="button" onClick={action.onClick} className={className}>
      <Icon className="h-4 w-4" />
      {action.label}
    </button>
  )
}

export default function ErrorPageLayout({
  statusCode,
  title,
  description,
  illustration,
  tone = 'server',
  primaryAction,
  secondaryAction,
}: Props) {
  const { locale, direction, t } = useLocale()
  const { siteSettings } = usePage().props as { siteSettings?: SiteSettings }
  const appName = locale === 'ar'
    ? (siteSettings?.app_name_ar ?? 'Zoon')
    : (siteSettings?.app_name_en ?? 'Zoon')

  return (
    <main dir={direction} lang={locale} className={`error-page-shell error-page-shell--${tone}`}>
      <Head title={`${statusCode} · ${title}`} />
      <section className={`error-page-card error-page-card--${tone} landing-fade-in`}>
        <div className="error-page-illustration" aria-hidden="true">
          {illustration}
        </div>

        <div className="space-y-2">
          <p className="error-page-brand">{appName}</p>
          <span className="error-page-code">{statusCode}</span>
          <h1 className="error-page-title">{title}</h1>
        </div>

        <p className="error-page-description">{description}</p>

        <div className="flex flex-wrap items-center justify-center gap-3">
          <ActionButton action={primaryAction} />
          {secondaryAction ? <ActionButton action={{ ...secondaryAction, variant: 'secondary' }} /> : null}
        </div>

        <p className="text-xs text-[var(--muted)]">{t('errorPageSupportHint')}</p>
      </section>
    </main>
  )
}
