import LocalizedLink from '@/components/routing/LocalizedLink'
import SetupCompleteMark from '@/components/events/SetupCompleteMark'
import type { EventSectionTab } from '@/lib/eventSetupProgress'
import { sectionMetaFor } from '@/lib/eventSectionMeta'
import { ArrowUpRight } from 'lucide-react'

type Props = {
  tabs: EventSectionTab[]
}

export default function EventSectionGrid({ tabs }: Props) {
  return (
    <div className="event-section-grid">
      {tabs.map((tab) => {
        const { icon: Icon } = sectionMetaFor(tab.key)
        const completed = tab.completed ?? false

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
              <SetupCompleteMark completed={completed} />
            </span>
            <ArrowUpRight className="event-section-tile-arrow h-4 w-4" aria-hidden="true" />
          </LocalizedLink>
        )
      })}
    </div>
  )
}
