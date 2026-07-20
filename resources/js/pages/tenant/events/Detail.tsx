import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { useMemo, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import EventSectionGrid from '@/components/events/EventSectionGrid'
import PublishReadinessList from '@/components/events/PublishReadinessList'
import EventNextSteps from '@/components/events/EventNextSteps'
import CopyRegistrationLinkButton from '@/components/events/CopyRegistrationLinkButton'
import SendPrivateInviteModal from '@/components/events/SendPrivateInviteModal'
import type { EventCapabilities } from '@/lib/eventOptions'
import type { EventSectionTab, EventSetupProgress } from '@/lib/eventSetupProgress'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import ConfirmModal from '@/components/modals/ConfirmModal'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { splitPublishReadiness, type PublishReadinessContext } from '@/lib/publishReadinessCatalog'
import { Mail } from 'lucide-react'

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
  can_unpublish?: boolean
}

type Props = {
  event: EventRow
  setupTabs: EventSectionTab[]
  operationsTabs: EventSectionTab[]
  tenantId: string
  eventCapabilities?: EventCapabilities
}

export default function EventDetail({ event, setupTabs, operationsTabs, tenantId, eventCapabilities }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [publishOpen, setPublishOpen] = useState(false)
  const [unpublishOpen, setUnpublishOpen] = useState(false)
  const [cancelOpen, setCancelOpen] = useState(false)
  const [inviteOpen, setInviteOpen] = useState(false)
  const [submitting, setSubmitting] = useState<'publish' | 'unpublish' | 'cancel' | null>(null)
  const [apiPublishMissing, setApiPublishMissing] = useState<string[] | null>(null)
  const canSendPrivateInvites = event.tier === 'private' || event.tier === 'both'

  async function copyRegistrationLink(url: string) {
    try {
      await navigator.clipboard.writeText(url)
      toast(t('copied'), 'success')
    } catch {
      toast(t('eventDetailCouldNotCopyLink'), 'error')
    }
  }

  const capabilities = eventCapabilities ?? event.capabilities
  const readinessContext = useMemo<PublishReadinessContext>(() => ({
    status: event.status,
    requiresTicketing: capabilities?.requires_ticketing,
  }), [event.status, capabilities?.requires_ticketing])
  const canPublish = event.status === 'draft' || event.status === 'configured'
  const canUnpublish = Boolean(event.can_unpublish)
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
    agenda: !effectiveReadiness.includes('published_agenda'),
    categories: !effectiveReadiness.includes('event_categories'),
    badge_templates: !effectiveReadiness.includes('active_badge_template'),
    kiosks: false,
    identity: false,
    published: !canPublish,
  }

  async function runStatusAction(action: 'publish' | 'unpublish' | 'cancel') {
    setSubmitting(action)

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/${action}`, {
        method: 'POST',
        tenantId,
        idempotency: true,
      })
      toast(
        action === 'publish'
          ? t('eventDetailPublished')
          : action === 'unpublish'
            ? t('eventDetailUnpublished')
            : t('eventDetailCancelled'),
        'success',
      )
      setPublishOpen(false)
      setUnpublishOpen(false)
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
        ? t('eventDetailCannotPublish').replace(':count', String(error.missing.length))
        : error instanceof ApiFetchError
          ? error.message
          : action === 'publish'
            ? t('eventDetailFailedToPublish')
            : action === 'unpublish'
              ? t('eventDetailFailedToUnpublish')
              : t('eventDetailFailedToCancel')

      toast(publishErrorMessage, 'error')
    } finally {
      setSubmitting(null)
    }
  }

  function openPublishModal() {
    setApiPublishMissing(null)
    setPublishOpen(true)
  }

  const publishButtonLabel = t('publish')

  return (
    <DashboardLayout title={event.name[locale]}>
      <PageHeader
        title={event.name[locale]}
        description={t('eventDetailDescription')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
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
            {canSendPrivateInvites ? (
              <PermissionGate permission="event.invite.manage">
                <button
                  type="button"
                  className="button-secondary inline-flex items-center gap-2"
                  onClick={() => setInviteOpen(true)}
                >
                  <Mail className="h-4 w-4" aria-hidden="true" />
                  {t('sendPrivateLink')}
                </button>
              </PermissionGate>
            ) : null}
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/agenda-preview`} target="_blank" rel="noreferrer">
              {t('preview')}
            </LocalizedLink>
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/edit`}>{t('edit')}</LocalizedLink>
            <PermissionGate permission="event.publish">
              {canPublishNow ? (
                <button type="button" className="button-primary" onClick={openPublishModal} disabled={submitting === 'publish'}>
                  {publishButtonLabel}
                </button>
              ) : null}
              {canUnpublish ? (
                <button
                  type="button"
                  className="button-secondary"
                  onClick={() => setUnpublishOpen(true)}
                  disabled={submitting === 'unpublish'}
                >
                  {t('unpublish')}
                </button>
              ) : null}
            </PermissionGate>
            <PermissionGate permission="event.cancel">
              <button type="button" className="button-secondary" onClick={() => setCancelOpen(true)}>{t('cancel')}</button>
            </PermissionGate>
          </div>
        )}
      />
      <PageContent>
        {/* <EventDetailHero
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
        /> */}

        {statusBlockers.length > 0 ? (
          <PublishReadinessList
            className="mt-6"
            items={statusBlockers}
            eventId={event.id}
            title={t('eventDetailEventStatus')}
            variant="info"
            context={readinessContext}
          />
        ) : null}

        {isPrePublishStatus && requirements.length > 0 ? (
          <PublishReadinessList
            className="mt-6"
            items={requirements}
            eventId={event.id}
            title={t('eventDetailRequirementsBeforePublishing')}
            variant="alert"
            context={readinessContext}
          />
        ) : null}

        <EventNextSteps
          eventId={event.id}
          status={event.status}
          tier={event.tier}
          eventType={event.event_type}
          registrationMode={event.registration_mode}
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
                {t('eventDetailQuickNavigation')}
              </p>
              <h2 className="text-lg font-semibold">{t('eventDetailEventSections')}</h2>
            </div>
          </div>

          <div className="event-sections-group">
            <h3 className="event-sections-group-title">
              {t('eventDetailEventSetup')}
            </h3>
            <EventSectionGrid tabs={setupTabs} />
          </div>

          <div className="event-sections-group">
            <h3 className="event-sections-group-title">
              {t('eventDetailEventOperations')}
            </h3>
            <EventSectionGrid tabs={operationsTabs} />
          </div>
        </section>
      </PageContent>

      <ConfirmModal
        open={publishOpen}
        title={t('eventDetailPublishEvent')}
        message={canPublishNow ? t('eventDetailPublishMessage') : t('eventDetailCannotPublishMessage')}
        confirmLabel={t('publish')}
        cancelLabel={t('close')}
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
        open={unpublishOpen}
        title={t('eventDetailUnpublishEvent')}
        message={t('eventDetailUnpublishMessage')}
        confirmLabel={t('unpublish')}
        cancelLabel={t('close')}
        loading={submitting !== null}
        onConfirm={() => void runStatusAction('unpublish')}
        onCancel={() => setUnpublishOpen(false)}
      />
      <ConfirmModal
        open={cancelOpen}
        title={t('eventDetailCancelEvent')}
        message={t('eventDetailCancelMessage')}
        confirmLabel={t('eventDetailConfirmCancel')}
        loading={submitting !== null}
        onConfirm={() => void runStatusAction('cancel')}
        onCancel={() => setCancelOpen(false)}
      />
      <SendPrivateInviteModal
        open={inviteOpen}
        eventId={event.id}
        tenantId={tenantId}
        onClose={() => setInviteOpen(false)}
      />
    </DashboardLayout>
  )
}
