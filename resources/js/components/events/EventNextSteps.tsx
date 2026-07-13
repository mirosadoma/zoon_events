import LocalizedLink from '@/components/routing/LocalizedLink'
import CopyRegistrationLinkButton from '@/components/events/CopyRegistrationLinkButton'
import SetupCompleteMark from '@/components/events/SetupCompleteMark'
import PermissionGate from '@/components/layout/PermissionGate'
import { useLocale } from '@/hooks/useLocale'
import type { EventCapabilities } from '@/lib/eventOptions'
import {
  isNextStepComplete,
  setupCompletionPercent,
  type EventSetupProgress,
} from '@/lib/eventSetupProgress'
import { publishBlockedMessage, type PublishReadinessContext } from '@/lib/publishReadinessCatalog'
import {
  CalendarDays,
  ClipboardList,
  ListChecks,
  Rocket,
  Tags,
  Ticket,
} from 'lucide-react'
import type { LucideIcon } from 'lucide-react'

type Props = {
  eventId: string
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
}: Props) {
  const { locale } = useLocale()
  const base = `/tenant/events/${eventId}`
  const needsTicketing = capabilities?.requires_ticketing ?? false
  const needsPriceTiers = capabilities?.requires_price_tiers ?? false
  const publishButtonLabel = locale === 'ar' ? 'نشر الفعالية' : 'Publish event'
  const blockedMessage = publishBlockedMessage(readiness, locale, context)
  const setupPercent = setupCompletionPercent(setupProgress, capabilities)

  const steps: NextStep[] = [
    {
      key: 'agenda',
      title: locale === 'ar' ? 'جدول الأعمال' : 'Agenda',
      description: locale === 'ar' ? 'أضف الجلسات والعناصر الزمنية للفعالية.' : 'Add sessions and schedule items for the event.',
      href: `${base}/agenda`,
      icon: CalendarDays,
    },
    {
      key: 'registration-form',
      title: locale === 'ar' ? 'نموذج التسجيل' : 'Registration form',
      description: locale === 'ar' ? 'حدد الحقول التي يملأها الحضور.' : 'Configure the fields attendees complete.',
      href: `${base}/registration-form`,
      icon: ClipboardList,
    },
  ]

  if (needsTicketing) {
    steps.push({
      key: 'ticket-types',
      title: locale === 'ar' ? 'أنواع التذاكر' : 'Ticket types',
      description: locale === 'ar' ? 'أنشئ التذاكر والأسعار.' : 'Create tickets and pricing.',
      href: `${base}/ticket-types`,
      icon: Ticket,
    })
  }

  if (needsPriceTiers) {
    steps.push({
      key: 'price-tiers',
      title: locale === 'ar' ? 'مستويات الأسعار' : 'Price tiers',
      description: locale === 'ar' ? 'أضف early bird أو شرائح سعرية.' : 'Add early-bird or scheduled tiers.',
      href: `${base}/price-tiers`,
      icon: Tags,
    })
  }

  return (
    <section className="event-next-steps state-panel mt-6">
      <div className="event-next-steps-header">
        <div>
          <p className="event-next-steps-kicker">
            <ListChecks className="h-4 w-4" aria-hidden="true" />
            {locale === 'ar' ? 'مسار الإعداد' : 'Setup journey'}
          </p>
          <h2 className="text-lg font-semibold">
            {locale === 'ar' ? 'الخطوات التالية' : 'Next steps'}
          </h2>
          <p className="event-next-steps-subtitle">
            {locale === 'ar'
              ? 'اتبع الخطوات بالترتيب لإطلاق فعالية احترافية.'
              : 'Follow these steps in order to launch a polished event experience.'}
          </p>
        </div>
        <div className="event-next-steps-summary">
          <strong>{setupPercent}%</strong>
          <span>{locale === 'ar' ? 'مكتمل' : 'complete'}</span>
        </div>
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
                    {locale === 'ar' ? 'فتح' : 'Open'}
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
                  <h3 className="font-medium">{locale === 'ar' ? 'النشر' : 'Publish'}</h3>
                  <SetupCompleteMark completed={setupProgress.published} />
                </div>
                <p className="event-next-step-description">
                  {canPublishNow
                    ? (locale === 'ar' ? 'الفعالية جاهزة للنشر للحضور.' : 'The event is ready to publish for attendees.')
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
