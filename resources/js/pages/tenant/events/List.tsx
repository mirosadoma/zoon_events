import { router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import PublishReadinessBadge from '@/components/events/PublishReadinessBadge'
import { PageContent, PageHeader } from '@/components/layout'
import ConfirmModal from '@/components/modals/ConfirmModal'
import StatusBadge from '@/components/status/StatusBadge'
import { DataTable } from '@/components/tables'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { labelForEventTier, requiresTicketing } from '@/lib/eventOptions'
import type { PublishReadinessContext } from '@/lib/publishReadinessCatalog'

type EventRow = {
  id: string
  name: { en: string; ar: string }
  status: string
  tier: string
  event_type?: string
  registration_mode?: string
  timezone: string
  start_at?: string | null
  registration_url?: string | null
  readiness?: string[]
}

type Props = {
  events: EventRow[]
  tenantId?: string
}

function readinessContextFor(event: EventRow): PublishReadinessContext {
  return {
    status: event.status,
    requiresTicketing: requiresTicketing(event.tier, event.registration_mode ?? 'free_registration'),
  }
}

export default function EventList({ events, tenantId: tenantIdProp }: Props) {
  const { locale, t, localizedPath } = useLocale()
  const { toast } = useToast()
  const page = usePage().props as { session?: { tenant?: { id?: string | number } } }
  const tenantId = String(tenantIdProp ?? page.session?.tenant?.id ?? '')
  const [copying, setCopying] = useState<EventRow | null>(null)
  const [copyNameEn, setCopyNameEn] = useState('')
  const [copyNameAr, setCopyNameAr] = useState('')
  const [submitting, setSubmitting] = useState(false)

  function openCopyModal(event: EventRow) {
    setCopying(event)
    setCopyNameEn(event.name.en || '')
    setCopyNameAr(event.name.ar || '')
  }

  function closeCopyModal() {
    if (submitting) {
      return
    }
    setCopying(null)
    setCopyNameEn('')
    setCopyNameAr('')
  }

  async function confirmCopy() {
    if (!copying || copyNameEn.trim() === '' || copyNameAr.trim() === '') {
      toast(t('eventListCopyNameRequired'), 'error')
      return
    }

    setSubmitting(true)

    try {
      const cloned = await apiFetch<{ id: string }>(`/api/v1/tenant/events/${copying.id}/copy`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: {
          name: {
            en: copyNameEn.trim(),
            ar: copyNameAr.trim(),
          },
        },
      })

      toast(t('eventListCopied'), 'success')
      setCopying(null)
      setCopyNameEn('')
      setCopyNameAr('')
      router.visit(localizedPath(`/tenant/events/${cloned.id}`))
    } catch (error) {
      const message = error instanceof ApiFetchError ? error.message : t('eventListCopyFailed')
      toast(message, 'error')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <DashboardLayout title={t('events')}>
      <PageHeader
        title={t('events')}
        description={t('eventListDescription')}
        breadcrumbs={[{ label: t('overview'), href: '/dashboard' }, { label: t('events') }]}
        actions={
          <LocalizedLink className="button-primary" href="/tenant/events/create">
            {t('eventListNewEvent')}
          </LocalizedLink>
        }
      />
      <PageContent>
        {events.length === 0 ? (
          <EmptyState title={t('eventListNoEvents')} detail={t('eventListNoEventsDetail')} />
        ) : (
          <DataTable
            rows={events as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'name',
                header: t('eventListName'),
                render: (row) => {
                  const event = row as unknown as EventRow

                  return <LocalizedLink href={`/tenant/events/${event.id}`} className="font-medium text-sky-700 hover:underline">{event.name[locale]}</LocalizedLink>
                },
              },
              {
                key: 'tier',
                header: t('eventListTier'),
                render: (row) => labelForEventTier(String((row as unknown as EventRow).tier), locale),
              },
              { key: 'event_type', header: t('eventListType') },
              { key: 'registration_mode', header: t('eventListRegistration') },
              {
                key: 'status',
                header: t('status'),
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              {
                key: 'publish_readiness',
                header: t('eventListPublishReadiness'),
                render: (row) => {
                  const event = row as unknown as EventRow

                  return (
                    <PublishReadinessBadge
                      readiness={event.readiness ?? []}
                      context={readinessContextFor(event)}
                    />
                  )
                },
              },
              { key: 'timezone', header: t('eventListTimezone') },
              {
                key: 'actions',
                header: t('actions'),
                render: (row) => {
                  const event = row as unknown as EventRow

                  return (
                    <div className="ta-table-actions">
                      <LocalizedLink href={`/tenant/events/${event.id}`} className="ta-table-action">
                        {t('view')}
                      </LocalizedLink>
                      <LocalizedLink href={`/tenant/events/${event.id}/edit`} className="ta-table-action">
                        {t('edit')}
                      </LocalizedLink>
                      <button
                        type="button"
                        className="ta-table-action"
                        onClick={() => openCopyModal(event)}
                      >
                        {t('eventListCopy')}
                      </button>
                    </div>
                  )
                },
              },
            ]}
          />
        )}
      </PageContent>

      <ConfirmModal
        open={copying !== null}
        title={t('eventListCopyTitle')}
        message={t('eventListCopyMessage')}
        confirmLabel={t('eventListCopy')}
        cancelLabel={t('cancel')}
        loading={submitting}
        loadingLabel={t('eventListCopying')}
        confirmDisabled={copyNameEn.trim() === '' || copyNameAr.trim() === ''}
        onConfirm={() => void confirmCopy()}
        onCancel={closeCopyModal}
      >
        <div className="space-y-4">
          <label className="block text-sm font-medium text-[var(--ink)]">
            {t('eventListCopyNameEn')}
            <input
              type="text"
              className="mt-2 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm"
              value={copyNameEn}
              onChange={(event) => setCopyNameEn(event.target.value)}
              maxLength={160}
              autoFocus
              disabled={submitting}
            />
          </label>
          <label className="block text-sm font-medium text-[var(--ink)]">
            {t('eventListCopyNameAr')}
            <input
              type="text"
              className="mt-2 w-full rounded-lg border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm"
              value={copyNameAr}
              onChange={(event) => setCopyNameAr(event.target.value)}
              maxLength={160}
              dir="rtl"
              disabled={submitting}
            />
          </label>
        </div>
      </ConfirmModal>
    </DashboardLayout>
  )
}
