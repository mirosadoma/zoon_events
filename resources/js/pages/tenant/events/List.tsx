import { router, usePage } from '@inertiajs/react'
import { useState } from 'react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import PublishReadinessBadge from '@/components/events/PublishReadinessBadge'
import { PageContent, PageHeader } from '@/components/layout'
import SideDetailPane, {
  SideDetailActions,
  SideDetailInfoGrid,
  sideDetailActionClassName,
} from '@/components/layout/SideDetailPane'
import ConfirmModal from '@/components/modals/ConfirmModal'
import StatusBadge from '@/components/status/StatusBadge'
import { DataTable } from '@/components/tables'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { labelForEventTier, requiresTicketing } from '@/lib/eventOptions'
import type { PublishReadinessContext } from '@/lib/publishReadinessCatalog'
import { ArrowUpRight } from 'lucide-react'

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
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [copying, setCopying] = useState<EventRow | null>(null)
  const [copyNameEn, setCopyNameEn] = useState('')
  const [copyNameAr, setCopyNameAr] = useState('')
  const [submitting, setSubmitting] = useState(false)

  const selected = events.find((event) => event.id === selectedId) ?? null
  const notAvailable = t('notAvailable')

  function closePane() {
    setSelectedId(null)
  }

  function goToEdit() {
    if (!selectedId) return
    router.visit(localizedPath(`/tenant/events/${selectedId}/edit`))
  }

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
            selectedRowKey={selectedId}
            onRowClick={(row) => setSelectedId(String(row.id))}
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
            ]}
          />
        )}
      </PageContent>

      <SideDetailPane
        open={selected !== null}
        title={selected ? selected.name[locale] : ''}
        subtitle={selected ? labelForEventTier(selected.tier, locale) : null}
        onClose={closePane}
        onEdit={goToEdit}
        footer={selected ? (
          <SideDetailActions>
            <LocalizedLink
              href={`/tenant/events/${selected.id}`}
              className={sideDetailActionClassName('primary')}
            >
              {t('view')}
              <ArrowUpRight className="h-4 w-4" aria-hidden />
            </LocalizedLink>
            <button type="button" className={sideDetailActionClassName()} onClick={goToEdit}>
              {t('edit')}
            </button>
            <button type="button" className={sideDetailActionClassName()} onClick={() => openCopyModal(selected)}>
              {t('eventListCopy')}
            </button>
          </SideDetailActions>
        ) : null}
      >
        {selected ? (
          <SideDetailInfoGrid
            items={[
              {
                label: t('eventListName'),
                value: selected.name[locale],
              },
              {
                label: t('eventListTier'),
                value: labelForEventTier(selected.tier, locale),
              },
              {
                label: t('eventListType'),
                value: selected.event_type || notAvailable,
              },
              {
                label: t('eventListRegistration'),
                value: selected.registration_mode || notAvailable,
              },
              {
                label: t('status'),
                value: <StatusBadge status={selected.status} />,
              },
              {
                label: t('eventListTimezone'),
                value: selected.timezone || notAvailable,
              },
            ]}
          />
        ) : null}
      </SideDetailPane>

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
