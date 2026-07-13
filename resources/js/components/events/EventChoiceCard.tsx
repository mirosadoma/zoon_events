import { clsx } from 'clsx'
import type { LucideIcon } from 'lucide-react'
import { Check } from 'lucide-react'

type Props = {
  title: string
  description?: string
  icon?: LucideIcon
  selected?: boolean
  onClick?: () => void
  disabled?: boolean
  badge?: string
}

export default function EventChoiceCard({
  title,
  description,
  icon: Icon,
  selected = false,
  onClick,
  disabled = false,
  badge,
}: Props) {
  return (
    <button
      type="button"
      className={clsx(
        'event-choice-card',
        selected && 'event-choice-card-selected',
        disabled && 'event-choice-card-disabled',
      )}
      onClick={onClick}
      disabled={disabled}
      aria-pressed={selected}
    >
      <div className="event-choice-card-top">
        {Icon ? (
          <span className="event-choice-card-icon" aria-hidden="true">
            <Icon className="h-5 w-5" />
          </span>
        ) : null}
        <div className="event-choice-card-copy">
          <span className="event-choice-card-title">{title}</span>
          {description ? <span className="event-choice-card-description">{description}</span> : null}
        </div>
        <span className={clsx('event-choice-card-check', selected && 'event-choice-card-check-visible')}>
          <Check className="h-4 w-4" />
        </span>
      </div>
      {badge ? <span className="event-choice-card-badge">{badge}</span> : null}
    </button>
  )
}
