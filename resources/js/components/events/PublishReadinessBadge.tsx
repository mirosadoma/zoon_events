import { clsx } from 'clsx'
import { useLocale } from '@/hooks/useLocale'
import {
  isReadyToPublish,
  publishReadinessTooltip,
  type PublishReadinessContext,
} from '@/lib/publishReadinessCatalog'

type Props = {
  readiness: string[]
  context: PublishReadinessContext
  className?: string
}

export default function PublishReadinessBadge({ readiness, context, className = '' }: Props) {
  const { locale } = useLocale()
  const ready = isReadyToPublish(readiness, context)
  const tooltip = publishReadinessTooltip(readiness, locale, context)

  return (
    <span
      className={clsx(
        'ta-badge',
        ready ? 'ta-badge-success' : 'ta-badge-warning',
        className,
      )}
      title={tooltip}
    >
      {ready
        ? (locale === 'ar' ? 'جاهزة للنشر' : 'Ready to publish')
        : (locale === 'ar' ? 'لا يمكن نشرها' : 'Cannot publish')}
    </span>
  )
}
