import { FormEvent, useEffect, useMemo, useState } from 'react'
import { router, usePage } from '@inertiajs/react'
import DashboardLayout from '@/layouts/DashboardLayout'
import ConfirmModal from '@/components/modals/ConfirmModal'
import ReasonModal from '@/components/modals/ReasonModal'
import { PageContent, PageHeader } from '@/components/layout'
import DataTable from '@/components/tables/DataTable'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import en from '@/locales/en'
import ar from '@/locales/ar'

type RequestRow = {
  id: string
  name: string
  email: string
  organization_name: string
  phone: string | null
  message: string | null
  status: string
  rejection_reason: string | null
  reviewed_at: string | null
  created_at: string | null
}

type Props = {
  requests: RequestRow[]
}

export default function OrganizerRequests({ requests }: Props) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const { toast } = useToast()
  const { flash } = usePage<{ flash: { status?: string } }>().props
  const [approveId, setApproveId] = useState<string | null>(null)
  const [rejectId, setRejectId] = useState<string | null>(null)
  const [loading, setLoading] = useState(false)

  useEffect(() => {
    if (flash?.status === 'organizer-approved') {
      toast(messages.organizerApprovedToast, 'success')
    }

    if (flash?.status === 'organizer-rejected') {
      toast(messages.organizerRejectedToast, 'info')
    }
  }, [flash?.status, messages.organizerApprovedToast, messages.organizerRejectedToast, toast])

  const columns = useMemo(() => [
    { key: 'name', header: messages.profileName, render: (row: RequestRow) => row.name },
    { key: 'email', header: messages.profileEmail, render: (row: RequestRow) => row.email },
    { key: 'organization_name', header: messages.registerOrganization, render: (row: RequestRow) => row.organization_name },
    { key: 'phone', header: messages.profilePhone, render: (row: RequestRow) => row.phone ?? '—' },
    { key: 'status', header: messages.status, render: (row: RequestRow) => <StatusBadge status={row.status} /> },
    {
      key: 'actions',
      header: messages.actions,
      render: (row: RequestRow) => row.status === 'pending' ? (
        <div className="flex flex-wrap gap-2">
          <button type="button" className="button-primary text-xs" onClick={() => setApproveId(row.id)}>
            {messages.approve}
          </button>
          <button type="button" className="button-secondary text-xs" onClick={() => setRejectId(row.id)}>
            {messages.reject}
          </button>
        </div>
      ) : (
        <span className="text-xs text-[var(--muted)]">{row.rejection_reason ?? messages.reviewed}</span>
      ),
    },
  ], [messages])

  function submitAction(url: string, body?: Record<string, string>) {
    setLoading(true)

    router.post(url, body ?? {}, {
      preserveScroll: true,
      onFinish: () => {
        setLoading(false)
        setApproveId(null)
        setRejectId(null)
      },
      onError: () => toast(messages.actionFailed, 'error'),
    })
  }

  return (
    <DashboardLayout title={messages.organizerRequestsTitle}>
      <PageHeader title={messages.organizerRequestsTitle} description={messages.organizerRequestsDescription} />
      <PageContent>
        <DataTable columns={columns} rows={requests} emptyMessage={messages.organizerRequestsEmpty} getRowKey={(row) => row.id} />
      </PageContent>

      <ConfirmModal
        open={approveId !== null}
        title={messages.approveOrganizerTitle}
        message={messages.approveOrganizerMessage}
        confirmLabel={messages.approve}
        cancelLabel={messages.cancel}
        loading={loading}
        onCancel={() => setApproveId(null)}
        onConfirm={() => approveId && submitAction(`/platform/organizer-requests/${approveId}/approve`)}
      />

      <ReasonModal
        open={rejectId !== null}
        title={messages.rejectOrganizerTitle}
        message={messages.rejectOrganizerMessage}
        reasonLabel={messages.rejectionReason}
        confirmLabel={messages.reject}
        cancelLabel={messages.cancel}
        loading={loading}
        onCancel={() => setRejectId(null)}
        onConfirm={(reason) => rejectId && submitAction(`/platform/organizer-requests/${rejectId}/reject`, { reason })}
      />
    </DashboardLayout>
  )
}
