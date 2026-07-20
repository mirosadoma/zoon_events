import { clsx } from 'clsx'
import { useLocale } from '@/hooks/useLocale'
import {
  publishReadinessBadgeKind,
  publishReadinessTooltip,
  type PublishReadinessContext,
} from '@/lib/publishReadinessCatalog'

type Props = {
  readiness: string[]
  context: PublishReadinessContext
  className?: string
}

export default function PublishReadinessBadge({ readiness, context, className = '' }: Props) {
  const { locale, t } = useLocale()
  const kind = publishReadinessBadgeKind(readiness, context)
  const tooltip = publishReadinessTooltip(readiness, locale, context)

  const label = (() => {
    switch (kind) {
      case 'ready':
        return t('publishReadinessReady')
      case 'published':
        return t('publishReadinessPublished')
      case 'unavailable':
        return t('publishReadinessUnavailable')
      default:
        return t('publishReadinessCannotPublish')
    }
  })()

  return (
    <span
      className={clsx(
        'ta-badge',
        kind === 'ready' || kind === 'published' ? 'ta-badge-success' : 'ta-badge-warning',
        className,
      )}
      title={tooltip}
    >
      {label}
    </span>
  )
}
