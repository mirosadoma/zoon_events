import type { CSSProperties } from 'react'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import type { EventCapabilities } from '@/lib/eventOptions'
import { EVENT_TIERS, EVENT_TYPES, REGISTRATION_MODES } from '@/lib/eventOptions'
import { setupCompletionPercent, type EventSetupProgress } from '@/lib/eventSetupProgress'
import { formatDate, formatNumber } from '@/lib/formatters'
import { CalendarRange, Layers3, Sparkles, Users } from 'lucide-react'

type Props = {
  status: string
  tier: string
  eventType?: string
  registrationMode?: string
  timezone: string
  startAt?: string | null
  endAt?: string | null
  capacity?: number | null
  setupProgress: EventSetupProgress
  capabilities?: EventCapabilities
  published: boolean
}

function labelFor(
  options: ReadonlyArray<{ value: string; label_en: string; label_ar: string }>,
  value: string | undefined,
  locale: 'en' | 'ar',
): string {
  if (!value) {
    return '—'
  }

  const match = options.find((option) => option.value === value)

  return match ? (locale === 'ar' ? match.label_ar : match.label_en) : value
}

export default function EventDetailHero({
  status,
  tier,
  eventType,
  registrationMode,
  timezone,
  startAt,
  endAt,
  capacity,
  setupProgress,
  capabilities,
  published,
}: Props) {
  const { locale } = useLocale()
  const setupPercent = setupCompletionPercent(setupProgress, capabilities)
  const tierLabel = labelFor(EVENT_TIERS, tier, locale)
  const typeLabel = labelFor(EVENT_TYPES, eventType, locale)
  const modeLabel = labelFor(REGISTRATION_MODES, registrationMode, locale)
  const startsLabel = startAt ? formatDate(startAt, locale, timezone) : '—'
  const endsLabel = endAt ? formatDate(endAt, locale, timezone) : '—'
  const capacityLabel = capacity != null ? formatNumber(capacity, locale) : '—'

  return (
    <section className="event-detail-hero" aria-label={locale === 'ar' ? 'نظرة عامة على الفعالية' : 'Event overview'}>
      <div className="event-detail-hero-glow" aria-hidden="true" />
      <div className="event-detail-hero-layout">
        <div className="event-detail-hero-copy">
          <p className="event-detail-hero-kicker">
            <Sparkles className="h-4 w-4" aria-hidden="true" />
            {tierLabel}
            <span className="event-detail-hero-dot" aria-hidden="true" />
            {typeLabel}
          </p>
          <div className="event-detail-hero-status">
            <StatusBadge status={status} size="md" />
            <span className="event-detail-hero-mode">{modeLabel}</span>
          </div>
          <p className="event-detail-hero-subtitle">
            {published
              ? (locale === 'ar' ? 'الفعالية منشورة وجاهزة لاستقبال الحضور.' : 'This event is live and ready for attendees.')
              : (locale === 'ar'
                ? 'أكمل الإعداد أدناه ثم انشر الفعالية عندما تكون جاهزة.'
                : 'Complete setup below, then publish when you are ready to go live.')}
          </p>
        </div>

        <div className="event-detail-hero-progress" aria-label={locale === 'ar' ? 'تقدم الإعداد' : 'Setup progress'}>
          <div
            className="event-setup-progress-ring"
            style={{ '--setup-progress': `${setupPercent}%` } as CSSProperties}
            role="img"
            aria-label={`${setupPercent}%`}
          >
            <span className="event-setup-progress-value">{setupPercent}%</span>
          </div>
          <p className="event-setup-progress-label">
            {locale === 'ar' ? 'اكتمال الإعداد' : 'Setup complete'}
          </p>
        </div>
      </div>

      <div className="event-detail-hero-metrics">
        <div className="event-detail-metric">
          <span className="event-detail-metric-icon" aria-hidden="true">
            <CalendarRange className="h-4 w-4" />
          </span>
          <div>
            <span className="event-detail-metric-label">{locale === 'ar' ? 'البداية' : 'Starts'}</span>
            <strong>{startsLabel}</strong>
          </div>
        </div>
        <div className="event-detail-metric">
          <span className="event-detail-metric-icon" aria-hidden="true">
            <CalendarRange className="h-4 w-4" />
          </span>
          <div>
            <span className="event-detail-metric-label">{locale === 'ar' ? 'النهاية' : 'Ends'}</span>
            <strong>{endsLabel}</strong>
          </div>
        </div>
        <div className="event-detail-metric">
          <span className="event-detail-metric-icon" aria-hidden="true">
            <Users className="h-4 w-4" />
          </span>
          <div>
            <span className="event-detail-metric-label">{locale === 'ar' ? 'السعة' : 'Capacity'}</span>
            <strong>{capacityLabel}</strong>
          </div>
        </div>
        <div className="event-detail-metric">
          <span className="event-detail-metric-icon" aria-hidden="true">
            <Layers3 className="h-4 w-4" />
          </span>
          <div>
            <span className="event-detail-metric-label">{locale === 'ar' ? 'المنطقة الزمنية' : 'Timezone'}</span>
            <strong>{timezone}</strong>
          </div>
        </div>
      </div>

      <div className="event-setup-progress-track" aria-hidden="true">
        <span className="event-setup-progress-fill" style={{ width: `${setupPercent}%` }} />
      </div>
    </section>
  )
}
