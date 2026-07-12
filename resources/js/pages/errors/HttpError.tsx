import ErrorPageLayout from '@/components/errors/ErrorPageLayout'
import HttpErrorIllustration from '@/components/errors/HttpErrorIllustration'
import { useLocale } from '@/hooks/useLocale'
import { resolveErrorPageConfig, type ErrorPageAction } from '@/lib/errorPageCatalog'

type Props = {
  statusCode?: number
}

function resolveAction(action: ErrorPageAction, t: (key: string) => string) {
  return {
    label: t(action.labelKey),
    href: action.href,
    onClick: action.onClick === 'reload'
      ? () => window.location.reload()
      : action.onClick === 'back'
        ? () => window.history.back()
        : undefined,
    icon: action.icon,
  }
}

export default function HttpError({ statusCode = 500 }: Props) {
  const { t } = useLocale()
  const config = resolveErrorPageConfig(statusCode)

  return (
    <ErrorPageLayout
      statusCode={statusCode}
      tone={config.tone}
      title={t(config.titleKey)}
      description={t(config.descriptionKey)}
      illustration={(
        <HttpErrorIllustration
          statusCode={statusCode}
          tone={config.tone}
          className="h-full w-full"
        />
      )}
      primaryAction={resolveAction(config.primaryAction, t)}
      secondaryAction={config.secondaryAction ? resolveAction(config.secondaryAction, t) : undefined}
    />
  )
}
