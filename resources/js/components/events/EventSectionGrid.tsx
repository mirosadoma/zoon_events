import LocalizedLink from '@/components/routing/LocalizedLink'
import SetupCompleteMark from '@/components/events/SetupCompleteMark'
import type { EventSectionTab } from '@/lib/eventSetupProgress'
import { sectionMetaFor } from '@/lib/eventSectionMeta'
import { useLocale } from '@/hooks/useLocale'
import { ArrowUpRight } from 'lucide-react'

type Props = {
  tabs: EventSectionTab[]
}

export default function EventSectionGrid({ tabs }: Props) {
  const { t } = useLocale()

  return (
    <div className="event-section-grid">
      {tabs.map((tab) => {
        const { icon: Icon } = sectionMetaFor(tab.key)
        const completed = tab.completed ?? false
        const progress = tab.progress

        return (
          <LocalizedLink
            key={tab.href}
            href={tab.href}
            className={`group event-section-tile${completed ? ' event-section-tile-complete' : ''}`}
          >
            <span className="event-section-tile-icon" aria-hidden="true">
              <Icon className="h-5 w-5" />
            </span>
            <span className="event-section-tile-copy">
              <span className="event-section-tile-label">{tab.label}</span>
              {progress && !completed ? (
                <span className="event-section-tile-progress">
                  {t('eventSectionProgress', { done: progress.done, total: progress.total })}
                </span>
              ) : null}
              <SetupCompleteMark completed={completed} />
            </span>
            <ArrowUpRight className="event-section-tile-arrow h-4 w-4" aria-hidden="true" />
          </LocalizedLink>
        )
      })}
    </div>
  )
}
