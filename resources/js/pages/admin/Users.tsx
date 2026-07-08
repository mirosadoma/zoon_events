import { router } from '@inertiajs/react'
import { useMemo, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import SelectInput from '@/components/forms/SelectInput'
import { PageContent, PageHeader, PermissionGate } from '@/components/layout'
import ReasonModal from '@/components/modals/ReasonModal'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import en from '@/locales/en'
import ar from '@/locales/ar'

type UserRow = {
  id: string
  name: string
  email: string
  status: string
  user_status: string
  created_at?: string | null
}

type Props = {
  tenantId: string
  users: UserRow[]
}

export default function AdminUsers({ tenantId, users: initialUsers }: Props) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const { toast } = useToast()
  const [users, setUsers] = useState(initialUsers)
  const [statusFilter, setStatusFilter] = useState('')
  const [pendingAction, setPendingAction] = useState<{ id: string; status: string } | null>(null)
  const [loading, setLoading] = useState(false)

  const filteredUsers = useMemo(() => {
    if (!statusFilter) {
      return users
    }

    return users.filter((user) => user.status === statusFilter)
  }, [statusFilter, users])

  async function applyStatusChange(reason: string) {
    if (!pendingAction) {
      return
    }

    setLoading(true)

    try {
      const response = await fetch(`/api/v1/tenant/memberships/${pendingAction.id}`, {
        method: 'PATCH',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Tenant-ID': tenantId,
          'Idempotency-Key': crypto.randomUUID(),
        },
        body: JSON.stringify({ status: pendingAction.status, reason }),
      })

      const body = await response.json()

      if (!response.ok) {
        toast(body.message ?? messages.errorState, 'error')
        return
      }

      setUsers((current) =>
        current.map((user) =>
          user.id === pendingAction.id ? { ...user, status: pendingAction.status } : user,
        ),
      )
      toast(messages.adminUserUpdated, 'success')
      setPendingAction(null)
    } finally {
      setLoading(false)
    }
  }

  return (
    <DashboardLayout title={messages.users}>
      <PageHeader
        title={messages.users}
        description={messages.adminUsersDescription}
        breadcrumbs={[
          { label: messages.overview, href: '/dashboard' },
          { label: messages.administration, href: '/admin/users' },
          { label: messages.users },
        ]}
      />
      <PageContent>
        <FiltersBar>
          <SelectInput
            label={messages.allStatuses}
            name="status"
            value={statusFilter}
            onChange={(event) => {
              setStatusFilter(event.target.value)
              router.get('/admin/users', event.target.value ? { status: event.target.value } : {}, { preserveState: true })
            }}
            options={[
              { value: '', label: messages.allStatuses },
              { value: 'active', label: messages.statusActive },
              { value: 'suspended', label: messages.statusSuspended },
              { value: 'deactivated', label: messages.statusDeactivated },
            ]}
          />
        </FiltersBar>

        {filteredUsers.length === 0 ? (
          <EmptyState title={messages.adminNoUsers} />
        ) : (
          <DataTable
            rows={filteredUsers as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              { key: 'name', header: messages.profileName },
              { key: 'email', header: messages.profileEmail },
              {
                key: 'status',
                header: messages.orderStatus,
                render: (row) => <StatusBadge status={String(row.status)} />,
              },
              {
                key: 'user_status',
                header: messages.adminAccountStatus,
                render: (row) => <StatusBadge status={String(row.user_status)} />,
              },
              {
                key: 'actions',
                header: messages.adminActions,
                render: (row) => {
                  const user = row as unknown as UserRow

                  return (
                    <PermissionGate permission="membership.manage">
                      <div className="flex flex-wrap gap-2">
                        {user.status !== 'active' ? (
                          <button
                            type="button"
                            className="button-secondary text-sm"
                            onClick={() => setPendingAction({ id: user.id, status: 'active' })}
                          >
                            {messages.adminActivateUser}
                          </button>
                        ) : null}
                        {user.status === 'active' ? (
                          <button
                            type="button"
                            className="button-secondary text-sm"
                            onClick={() => setPendingAction({ id: user.id, status: 'suspended' })}
                          >
                            {messages.adminSuspendUser}
                          </button>
                        ) : null}
                      </div>
                    </PermissionGate>
                  )
                },
              },
            ]}
          />
        )}
      </PageContent>

      <ReasonModal
        open={pendingAction !== null}
        title={pendingAction?.status === 'active' ? messages.adminActivateUser : messages.adminSuspendUser}
        message={messages.adminStatusChangeReason}
        reasonLabel={messages.reasonRequired.replace('.', '')}
        confirmLabel={messages.confirm}
        cancelLabel={messages.cancel}
        loading={loading}
        onCancel={() => setPendingAction(null)}
        onConfirm={applyStatusChange}
      />
    </DashboardLayout>
  )
}
