import { router } from '@inertiajs/react'
import { FormEvent, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import DateTimeInput from '@/components/forms/DateTimeInput'
import SelectInput from '@/components/forms/SelectInput'
import TextInput from '@/components/forms/TextInput'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'

type AuditLogRow = {
  id: string
  actor_id?: string | null
  action: string
  target_type?: string | null
  target_id?: string | null
  outcome: string
  metadata?: Record<string, unknown> | null
  occurred_at?: string | null
}

type Filters = {
  from?: string | null
  to?: string | null
  action?: string | null
  outcome?: string | null
  actor_id?: string | null
}

type Props = {
  tenantId: string
  auditLogs: AuditLogRow[]
  filters: Filters
}

export default function AdminAuditLogs({ auditLogs, filters }: Props) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const [draft, setDraft] = useState({
    from: filters.from?.slice(0, 16) ?? '',
    to: filters.to?.slice(0, 16) ?? '',
    action: filters.action ?? '',
    outcome: filters.outcome ?? '',
    actor_id: filters.actor_id ?? '',
  })

  function submitFilters(event: FormEvent) {
    event.preventDefault()

    const query: Record<string, string> = {}
    if (draft.from) query.from = new Date(draft.from).toISOString()
    if (draft.to) query.to = new Date(draft.to).toISOString()
    if (draft.action) query.action = draft.action
    if (draft.outcome) query.outcome = draft.outcome
    if (draft.actor_id) query.actor_id = draft.actor_id

    router.get('/admin/audit-logs', query, { preserveState: true })
  }

  return (
    <DashboardLayout title={messages.audit}>
      <PageHeader
        title={messages.audit}
        description={messages.adminAuditDescription}
        breadcrumbs={[
          { label: messages.overview, href: '/dashboard' },
          { label: messages.administration, href: '/admin/users' },
          { label: messages.audit },
        ]}
      />
      <PageContent>
        <form onSubmit={submitFilters}>
          <FiltersBar>
            <DateTimeInput label={messages.adminFilterFrom} name="from" value={draft.from} onChange={(event) => setDraft((current) => ({ ...current, from: event.target.value }))} />
            <DateTimeInput label={messages.adminFilterTo} name="to" value={draft.to} onChange={(event) => setDraft((current) => ({ ...current, to: event.target.value }))} />
            <TextInput label={messages.adminFilterAction} name="action" value={draft.action} onChange={(event) => setDraft((current) => ({ ...current, action: event.target.value }))} />
            <SelectInput
              label={messages.adminFilterOutcome}
              name="outcome"
              value={draft.outcome}
              onChange={(event) => setDraft((current) => ({ ...current, outcome: event.target.value }))}
              options={[
                { value: '', label: messages.allStatuses },
                { value: 'succeeded', label: messages.adminOutcomeSucceeded },
                { value: 'failed', label: messages.adminOutcomeFailed },
              ]}
            />
            <TextInput label={messages.adminFilterActor} name="actor_id" value={draft.actor_id} onChange={(event) => setDraft((current) => ({ ...current, actor_id: event.target.value }))} />
            <button type="submit" className="button-primary">{messages.search}</button>
          </FiltersBar>
        </form>

        {auditLogs.length === 0 ? (
          <EmptyState title={messages.emptyAudit} />
        ) : (
          <DataTable
            rows={auditLogs as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              {
                key: 'occurred_at',
                header: messages.adminOccurredAt,
                render: (row) => (row.occurred_at ? new Date(String(row.occurred_at)).toLocaleString() : '—'),
              },
              { key: 'action', header: messages.adminFilterAction },
              {
                key: 'outcome',
                header: messages.adminFilterOutcome,
                render: (row) => <StatusBadge status={String(row.outcome)} />,
              },
              { key: 'actor_id', header: messages.adminFilterActor },
              { key: 'target_type', header: messages.adminTargetType },
              { key: 'target_id', header: messages.adminTargetId },
            ]}
          />
        )}
      </PageContent>
    </DashboardLayout>
  )
}
