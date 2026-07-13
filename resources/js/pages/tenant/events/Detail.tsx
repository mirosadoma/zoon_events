import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { useMemo, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import EventDetailHero from '@/components/events/EventDetailHero'
import EventSectionGrid from '@/components/events/EventSectionGrid'
import PublishReadinessList from '@/components/events/PublishReadinessList'
import EventNextSteps from '@/components/events/EventNextSteps'
import CopyRegistrationLinkButton from '@/components/events/CopyRegistrationLinkButton'
import type { EventCapabilities } from '@/lib/eventOptions'
import type { EventSectionTab, EventSetupProgress } from '@/lib/eventSetupProgress'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import ConfirmModal from '@/components/modals/ConfirmModal'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { splitPublishReadiness, type PublishReadinessContext } from '@/lib/publishReadinessCatalog'

type EventRow = {
  id: string
  name: { en: string; ar: string }
  status: string
  tier: string
  event_type?: string
  registration_mode?: string
  timezone: string
  start_at?: string | null
  end_at?: string | null
  capacity?: number | null
  readiness?: string[]
  capabilities?: EventCapabilities,
  registration_url?: string | null
  setup_progress?: EventSetupProgress
}

type Props = {
  event: EventRow
  setupTabs: EventSectionTab[]
  operationsTabs: EventSectionTab[]
  tenantId: string
  eventCapabilities?: EventCapabilities
}

export default function EventDetail({ event, setupTabs, operationsTabs, tenantId, eventCapabilities }: Props) {
  const { locale } = useLocale()
  const { toast } = useToast()
  const [publishOpen, setPublishOpen] = useState(false)
  const [cancelOpen, setCancelOpen] = useState(false)
  const [submitting, setSubmitting] = useState<'publish' | 'cancel' | null>(null)
  const [apiPublishMissing, setApiPublishMissing] = useState<string[] | null>(null)

  async function copyRegistrationLink(url: string) {
    try {
      await navigator.clipboard.writeText(url)
      toast(locale === 'ar' ? 'تم النسخ' : 'Copied', 'success')
    } catch {
      toast(locale === 'ar' ? 'تعذر نسخ الرابط.' : 'Could not copy the link.', 'error')
    }
  }

  const capabilities = eventCapabilities ?? event.capabilities
  const readinessContext = useMemo<PublishReadinessContext>(() => ({
    status: event.status,
    requiresTicketing: capabilities?.requires_ticketing,
  }), [event.status, capabilities?.requires_ticketing])
  const canPublish = event.status === 'draft' || event.status === 'configured'
  const isPrePublishStatus = canPublish
  const effectiveReadiness = useMemo(
    () => apiPublishMissing ?? (event.readiness ?? []),
    [apiPublishMissing, event.readiness],
  )
  const { requirements, statusBlockers } = useMemo(
    () => splitPublishReadiness(effectiveReadiness, readinessContext),
    [effectiveReadiness, readinessContext],
  )
  const canPublishNow = canPublish && requirements.length === 0 && statusBlockers.length === 0
  const setupProgress: EventSetupProgress = event.setup_progress ?? {
    registration_form: !effectiveReadiness.includes('active_form_version_id'),
    ticket_types: !effectiveReadiness.includes('active_ticket_type'),
    price_tiers: false,
    agenda: false,
    identity: false,
    published: !canPublish,
  }

  async function runStatusAction(action: 'publish' | 'cancel') {
    setSubmitting(action)

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/${action}`, {
        method: 'POST',
        tenantId,
        idempotency: true,
      })
      toast(
        action === 'publish'
          ? (locale === 'ar' ? 'تم نشر الفعالية.' : 'Event published.')
          : (locale === 'ar' ? 'تم إلغاء الفعالية.' : 'Event cancelled.'),
        'success',
      )
      setPublishOpen(false)
      setCancelOpen(false)
      setApiPublishMissing(null)
      router.reload({ only: ['event'] })
    } catch (error) {
      if (action === 'publish' && error instanceof ApiFetchError) {
        if (error.missing.length > 0) {
          setApiPublishMissing(error.missing)
          setPublishOpen(true)
        }
      }

      const publishErrorMessage = action === 'publish' && error instanceof ApiFetchError && error.missing.length > 0
        ? (locale === 'ar'
          ? `لا يمكن النشر — متبقي ${error.missing.length} متطلبات.`
          : `Cannot publish — ${error.missing.length} requirement(s) remain.`)
        : error instanceof ApiFetchError
          ? error.message
          : action === 'publish'
            ? (locale === 'ar' ? 'تعذر نشر الفعالية.' : 'Failed to publish event.')
            : (locale === 'ar' ? 'تعذر إلغاء الفعالية.' : 'Failed to cancel event.')

      toast(publishErrorMessage, 'error')
    } finally {
      setSubmitting(null)
    }
  }

  function openPublishModal() {
    setApiPublishMissing(null)
    setPublishOpen(true)
  }

  const publishButtonLabel = locale === 'ar' ? 'نشر' : 'Publish'

  return (
    <DashboardLayout title={event.name[locale]}>
      <PageHeader
        title={event.name[locale]}
        description={locale === 'ar' ? 'تفاصيل الفعالية وإعداداتها.' : 'Event details and configuration.'}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale] },
        ]}
        actions={(
          <div className="flex flex-wrap gap-2">
            {event.registration_url ? (
              <CopyRegistrationLinkButton
                className="button-secondary"
                onClick={() => void copyRegistrationLink(event.registration_url!)}
              />
            ) : null}
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/agenda-preview`} target="_blank" rel="noreferrer">
              {locale === 'ar' ? 'معاينة' : 'Preview'}
            </LocalizedLink>
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/edit`}>{locale === 'ar' ? 'تعديل' : 'Edit'}</LocalizedLink>
            <PermissionGate permission="event.publish">
              {canPublishNow ? (
                <button type="button" className="button-primary" onClick={openPublishModal} disabled={submitting === 'publish'}>
                  {publishButtonLabel}
                </button>
              ) : null}
            </PermissionGate>
            <PermissionGate permission="event.cancel">
              <button type="button" className="button-secondary" onClick={() => setCancelOpen(true)}>{locale === 'ar' ? 'إلغاء' : 'Cancel'}</button>
            </PermissionGate>
          </div>
        )}
      />
      <PageContent>
        <EventDetailHero
          status={event.status}
          tier={event.tier}
          eventType={event.event_type}
          registrationMode={event.registration_mode}
          timezone={event.timezone}
          startAt={event.start_at}
          endAt={event.end_at}
          capacity={event.capacity}
          setupProgress={setupProgress}
          capabilities={capabilities}
          published={setupProgress.published}
        />

        {statusBlockers.length > 0 ? (
          <PublishReadinessList
            className="mt-6"
            items={statusBlockers}
            eventId={event.id}
            title={locale === 'ar' ? 'حالة الفعالية' : 'Event status'}
            variant="info"
            context={readinessContext}
          />
        ) : null}

        {isPrePublishStatus && requirements.length > 0 ? (
          <PublishReadinessList
            className="mt-6"
            items={requirements}
            eventId={event.id}
            title={locale === 'ar' ? 'متطلبات النشر قبل الإطلاق' : 'Requirements before publishing'}
            variant="alert"
            context={readinessContext}
          />
        ) : null}

        <EventNextSteps
          eventId={event.id}
          readiness={effectiveReadiness}
          capabilities={capabilities}
          context={readinessContext}
          setupProgress={setupProgress}
          canPublishNow={canPublishNow}
          onPublish={openPublishModal}
          publishing={submitting === 'publish'}
          registrationUrl={event.registration_url}
          onCopyRegistrationLink={event.registration_url
            ? () => void copyRegistrationLink(event.registration_url!)
            : undefined}
        />

        <section className="event-sections-panel state-panel mt-6">
          <div className="event-sections-header">
            <div>
              <p className="event-sections-kicker">
                {locale === 'ar' ? 'التنقل السريع' : 'Quick navigation'}
              </p>
              <h2 className="text-lg font-semibold">{locale === 'ar' ? 'أقسام الفعالية' : 'Event sections'}</h2>
            </div>
          </div>

          <div className="event-sections-group">
            <h3 className="event-sections-group-title">
              {locale === 'ar' ? 'إعداد الفعالية' : 'Event setup'}
            </h3>
            <EventSectionGrid tabs={setupTabs} />
          </div>

          <div className="event-sections-group">
            <h3 className="event-sections-group-title">
              {locale === 'ar' ? 'عمليات الفعالية' : 'Event operations'}
            </h3>
            <EventSectionGrid tabs={operationsTabs} />
          </div>
        </section>
      </PageContent>

      <ConfirmModal
        open={publishOpen}
        title={locale === 'ar' ? 'نشر الفعالية' : 'Publish event'}
        message={
          canPublishNow
            ? (locale === 'ar' ? 'سيتم نشر الفعالية للمشتركين.' : 'This will publish the event for attendees.')
            : (locale === 'ar'
              ? 'لا يمكن نشر الفعالية حتى تكتمل المتطلبات التالية:'
              : 'The event cannot be published until the following requirements are completed:')
        }
        confirmLabel={locale === 'ar' ? 'نشر' : 'Publish'}
        cancelLabel={locale === 'ar' ? 'إغلاق' : 'Close'}
        loading={submitting !== null}
        confirmDisabled={!canPublishNow}
        onConfirm={() => void runStatusAction('publish')}
        onCancel={() => setPublishOpen(false)}
      >
        {!canPublishNow ? (
          <PublishReadinessList
            items={[...statusBlockers, ...requirements]}
            eventId={event.id}
            context={readinessContext}
          />
        ) : null}
      </ConfirmModal>
      <ConfirmModal
        open={cancelOpen}
        title={locale === 'ar' ? 'إلغاء الفعالية' : 'Cancel event'}
        message={locale === 'ar' ? 'سيتم إيقاف التسجيل والعمليات المرتبطة.' : 'This will stop registration and related operations.'}
        confirmLabel={locale === 'ar' ? 'تأكيد الإلغاء' : 'Confirm cancel'}
        loading={submitting !== null}
        onConfirm={() => void runStatusAction('cancel')}
        onCancel={() => setCancelOpen(false)}
      />
    </DashboardLayout>
  )
}
