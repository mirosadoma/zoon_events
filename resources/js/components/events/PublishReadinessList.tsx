import LocalizedLink from '@/components/routing/LocalizedLink'
import { clsx } from 'clsx'
import { useLocale } from '@/hooks/useLocale'
import {
  publishReadinessHref,
  publishReadinessLabel,
  type PublishReadinessContext,
} from '@/lib/publishReadinessCatalog'

type Props = {
  items: string[]
  eventId?: string
  title?: string
  className?: string
  variant?: 'default' | 'alert' | 'info'
  context?: PublishReadinessContext
}

export default function PublishReadinessList({
  items,
  eventId,
  title,
  className = '',
  variant = 'default',
  context,
}: Props) {
  const { locale, t } = useLocale()

  if (items.length === 0) {
    return null
  }

  const heading = title ?? t('publishReadinessListTitle')
  const linkClassName = clsx(
    'underline-offset-2 hover:underline',
    variant === 'alert' && 'text-[var(--warning)]',
    variant === 'info' && 'text-[var(--info)]',
    variant === 'default' && 'text-[var(--brand)]',
  )
  const markerLabel = variant === 'info' ? 'i' : '!'

  return (
    <section
      className={clsx(
        'publish-readiness-panel',
        `publish-readiness-panel--${variant}`,
        variant === 'alert' && 'ta-alert-warning',
        variant === 'info' && 'ta-alert-info',
        variant === 'default' && 'state-panel',
        className,
      )}
      aria-labelledby="publish-readiness-heading"
    >
      <h2 id="publish-readiness-heading" className="text-lg font-semibold">{heading}</h2>
      <p className="publish-readiness-copy">
        {variant === 'info'
          ? t('publishReadinessInfoCopy')
          : t('publishReadinessDefaultCopy')}
      </p>
      <ul className="publish-readiness-list">
        {items.map((item) => {
          const label = publishReadinessLabel(item, locale, context)
          const href = eventId ? publishReadinessHref(item, eventId, context) : undefined

          return (
            <li key={item} className="publish-readiness-item">
              <span className="publish-readiness-marker" aria-hidden="true">
                {markerLabel}
              </span>
              {href ? (
                <LocalizedLink href={href} className={linkClassName}>
                  {label}
                </LocalizedLink>
              ) : (
                <span>{label}</span>
              )}
            </li>
          )
        })}
      </ul>
    </section>
  )
}
