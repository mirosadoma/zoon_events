import LocalizedLink from '@/components/routing/LocalizedLink'
import { useLocale } from '@/hooks/useLocale'
import { publishReadinessHref, publishReadinessLabel } from '@/lib/publishReadinessCatalog'

type Props = {
  items: string[]
  eventId?: string
  title?: string
  className?: string
}

export default function PublishReadinessList({ items, eventId, title, className = '' }: Props) {
  const { locale } = useLocale()

  if (items.length === 0) {
    return null
  }

  const heading = title ?? (locale === 'ar' ? 'متطلبات النشر الناقصة' : 'Missing publication requirements')

  return (
    <section className={className} aria-labelledby="publish-readiness-heading">
      <h2 id="publish-readiness-heading" className="text-lg font-semibold">{heading}</h2>
      <ul className="mt-2 list-disc space-y-1 ps-5 text-sm text-slate-600 dark:text-slate-300">
        {items.map((item) => {
          const label = publishReadinessLabel(item, locale)
          const href = eventId ? publishReadinessHref(item, eventId) : undefined

          return (
            <li key={item}>
              {href ? (
                <LocalizedLink href={href} className="text-[var(--primary)] underline-offset-2 hover:underline">
                  {label}
                </LocalizedLink>
              ) : (
                label
              )}
            </li>
          )
        })}
      </ul>
    </section>
  )
}
