import type { CSSProperties } from 'react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import CopyRegistrationLinkButton from '@/components/events/CopyRegistrationLinkButton'
import SetupCompleteMark from '@/components/events/SetupCompleteMark'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import type { EventCapabilities } from '@/lib/eventOptions'
import { EVENT_TIERS, EVENT_TYPES, REGISTRATION_MODES } from '@/lib/eventOptions'
import {
  isNextStepComplete,
  setupCompletionPercent,
  type EventSetupProgress,
} from '@/lib/eventSetupProgress'
import { publishBlockedMessage, type PublishReadinessContext } from '@/lib/publishReadinessCatalog'
import {
  CalendarDays,
  ClipboardList,
  Rocket,
  Tags,
  Ticket,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

type Props = {
  eventId: string
  status: string
  tier: string
  eventType?: string
  registrationMode?: string
  readiness: string[]
  capabilities?: EventCapabilities
  context: PublishReadinessContext
  setupProgress: EventSetupProgress
  canPublishNow: boolean
  onPublish: () => void
  publishing?: boolean
  registrationUrl?: string | null
  onCopyRegistrationLink?: () => void
}

type NextStep = {
  key: string
  title: string
  description: string
  href: string
  icon: LucideIcon
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

export default function EventNextSteps({
  readiness,
  capabilities,
  context,
  setupProgress,
  canPublishNow,
  onPublish,
  publishing = false,
  registrationUrl,
  onCopyRegistrationLink,
  eventId,
  status,
  tier,
  eventType,
  registrationMode,
}: Props) {
  const { locale, t } = useLocale()
  const base = `/tenant/events/${eventId}`
  const needsTicketing = capabilities?.requires_ticketing ?? false
  const needsPriceTiers = capabilities?.requires_price_tiers ?? false
  const publishButtonLabel = t('eventNextPublishButton')
  const blockedMessage = publishBlockedMessage(readiness, locale, context)
  const setupPercent = setupCompletionPercent(setupProgress, capabilities)
  const published = setupProgress.published
  const tierLabel = labelFor(EVENT_TIERS, tier, locale)
  const typeLabel = labelFor(EVENT_TYPES, eventType, locale)
  const modeLabel = labelFor(REGISTRATION_MODES, registrationMode, locale)

  const steps: NextStep[] = [
    {
      key: 'agenda',
      title: t('eventNextAgenda'),
      description: t('eventNextAgendaDescription'),
      href: `${base}/agenda`,
      icon: CalendarDays,
    },
    {
      key: 'registration-form',
      title: t('eventNextRegistrationForm'),
      description: t('eventNextRegistrationFormDescription'),
      href: `${base}/registration-form`,
      icon: ClipboardList,
    },
  ]

  if (needsTicketing) {
    steps.push({
      key: 'ticket-types',
      title: t('eventNextTicketTypes'),
      description: t('eventNextTicketTypesDescription'),
      href: `${base}/ticket-types`,
      icon: Ticket,
    })
  }

  if (needsPriceTiers) {
    steps.push({
      key: 'price-tiers',
      title: t('eventNextPriceTiers'),
      description: t('eventNextPriceTiersDescription'),
      href: `${base}/price-tiers`,
      icon: Tags,
    })
  }

  return (
    <section className="event-next-steps state-panel mt-6">
      <div className="event-next-steps-header event-detail-hero">
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

      <ol className="event-next-steps-list">
        {steps.map((step, index) => {
          const completed = isNextStepComplete(step.key, setupProgress)
          const StepIcon = step.icon

          return (
            <li
              key={step.key}
              className={`event-next-step-card${completed ? ' event-next-step-card-complete' : ''}`}
            >
              <div className="event-next-step-marker" aria-hidden="true">
                <span className="event-next-step-index">{index + 1}</span>
              </div>
              <div className="event-next-step-body">
                <div className="event-next-step-top">
                  <div className="flex min-w-0 flex-1 items-start gap-3">
                    <span className="event-next-step-icon" aria-hidden="true">
                      <StepIcon className="h-4 w-4" />
                    </span>
                    <div className="min-w-0 flex-1">
                      <div className="flex flex-wrap items-center gap-2">
                        <h3 className="font-medium">{step.title}</h3>
                        <SetupCompleteMark completed={completed} />
                      </div>
                      <p className="event-next-step-description">{step.description}</p>
                    </div>
                  </div>
                  <LocalizedLink href={step.href} className="button-secondary event-next-step-action shrink-0">
                    {t('eventNextOpen')}
                  </LocalizedLink>
                </div>
              </div>
            </li>
          )
        })}

        <li className={`event-next-step-card event-next-step-card-publish${canPublishNow ? ' event-next-step-card-ready' : ''}`}>
          <div className="event-next-step-marker event-next-step-marker-publish" aria-hidden="true">
            <Rocket className="h-4 w-4" />
          </div>
          <div className="event-next-step-body">
            <div className="event-next-step-top">
              <div className="min-w-0 flex-1">
                <div className="flex flex-wrap items-center gap-2">
                  <h3 className="font-medium">{t('eventNextPublishTitle')}</h3>
                  <SetupCompleteMark completed={setupProgress.published} />
                </div>
                <p className="event-next-step-description">
                  {canPublishNow
                    ? t('eventNextPublishReady')
                    : blockedMessage}
                </p>
              </div>
              <div className="flex flex-wrap gap-2">
                {registrationUrl && onCopyRegistrationLink ? (
                  <CopyRegistrationLinkButton onClick={onCopyRegistrationLink} className="button-secondary shrink-0" />
                ) : null}
                {canPublishNow ? (
                  <PermissionGate permission="event.publish">
                    <button
                      type="button"
                      className="button-primary shrink-0"
                      onClick={onPublish}
                      disabled={publishing}
                    >
                      {publishButtonLabel}
                    </button>
                  </PermissionGate>
                ) : null}
              </div>
            </div>
          </div>
        </li>
      </ol>
    </section>
  )
}
