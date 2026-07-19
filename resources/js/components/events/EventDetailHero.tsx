import type { CSSProperties } from 'react'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import type { EventCapabilities } from '@/lib/eventOptions'
import { EVENT_TIERS, EVENT_TYPES, REGISTRATION_MODES } from '@/lib/eventOptions'
import { setupCompletionPercent, type EventSetupProgress } from '@/lib/eventSetupProgress'
import { Ticket } from 'lucide-react'

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
  setupProgress,
  capabilities,
  published,
}: Props) {
  const { locale, t } = useLocale()
  const setupPercent = setupCompletionPercent(setupProgress, capabilities)
  const tierLabel = labelFor(EVENT_TIERS, tier, locale)
  const typeLabel = labelFor(EVENT_TYPES, eventType, locale)
  const modeLabel = labelFor(REGISTRATION_MODES, registrationMode, locale)

  return (
    <section className="event-detail-hero" aria-label={t('eventHeroOverview')}>
      <div className="event-detail-hero__glow" aria-hidden="true" />

      <div className="event-detail-hero__top">
        <div className="event-detail-hero__copy">
          <div className="event-detail-hero__chips">
            <span className="event-detail-hero__chip">{tierLabel}</span>
            <span className="event-detail-hero__chip event-detail-hero__chip--muted">{typeLabel}</span>
          </div>

          <div className="event-detail-hero__status">
            <StatusBadge status={status} size="md" />
            <span className="event-detail-hero__mode">
              <Ticket className="h-3.5 w-3.5" aria-hidden="true" />
              {modeLabel}
            </span>
          </div>

          <p className="event-detail-hero__subtitle">
            {published ? t('eventHeroPublished') : t('eventHeroDraft')}
          </p>
        </div>

        <aside className="event-detail-hero__progress" aria-label={t('eventHeroSetupProgress')}>
          <div
            className="event-setup-progress-ring"
            style={{ '--setup-progress': `${setupPercent}%` } as CSSProperties}
            role="img"
            aria-label={`${setupPercent}%`}
          >
            <span className="event-setup-progress-value">{setupPercent}%</span>
          </div>
          <div className="event-detail-hero__progress-copy">
            <p className="event-setup-progress-label">
              {t('eventHeroSetupComplete')}
            </p>
            <div
              className="event-setup-progress-track event-setup-progress-track--compact"
              role="progressbar"
              aria-valuenow={setupPercent}
              aria-valuemin={0}
              aria-valuemax={100}
            >
              <span className="event-setup-progress-fill" style={{ width: `${setupPercent}%` }} />
            </div>
            <p className="event-detail-hero__progress-hint">
              {setupPercent >= 100 ? t('eventHeroReadyToPublish') : t('eventHeroStepsRemaining')}
            </p>
          </div>
        </aside>
      </div>
    </section>
  )
}
