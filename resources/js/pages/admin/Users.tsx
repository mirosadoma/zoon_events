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
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { useToast } from '@/hooks/useToast'
import en from '@/locales/en'
import ar from '@/locales/ar'

type RoleOption = {
  id: string
  name: string
  is_system: boolean
}

type UserRow = {
  id: string
  name: string
  email: string
  status: string
  user_status: string
  created_at?: string | null
  roles?: Array<{ id: string; name: string }>
}

type Props = {
  tenantId: string
  users: UserRow[]
  roles?: RoleOption[]
}

function readCsrfToken(): string | null {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)

  return match ? decodeURIComponent(match[1]) : null
}

export default function AdminUsers({ tenantId, users: initialUsers, roles = [] }: Props) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const { toast } = useToast()
  const localizedRouter = useLocalizedRouter()
  const [users, setUsers] = useState(initialUsers)
  const [statusFilter, setStatusFilter] = useState('')
  const [pendingAction, setPendingAction] = useState<{ id: string; status: string } | null>(null)
  const [loading, setLoading] = useState(false)
  const [assigningFor, setAssigningFor] = useState<string | null>(null)
  const [selectedRoleByUser, setSelectedRoleByUser] = useState<Record<string, string>>({})

  const filteredUsers = useMemo(() => {
    if (!statusFilter) {
      return users
    }

    return users.filter((user) => user.status === statusFilter)
  }, [statusFilter, users])

  const roleOptions = useMemo(
    () => roles.map((role) => ({ value: role.id, label: role.name })),
    [roles],
  )

  async function applyStatusChange(reason: string) {
    if (!pendingAction) {
      return
    }

    setLoading(true)

    try {
      const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Tenant-ID': tenantId,
        'Idempotency-Key': crypto.randomUUID(),
      }
      const csrfToken = readCsrfToken()
      if (csrfToken) {
        headers['X-XSRF-TOKEN'] = csrfToken
      }

      const response = await fetch(`/api/v1/tenant/memberships/${pendingAction.id}`, {
        method: 'PATCH',
        credentials: 'include',
        headers,
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

  async function assignRole(user: UserRow) {
    const roleId = selectedRoleByUser[user.id]
    if (!roleId) {
      return
    }

    setAssigningFor(user.id)

    try {
      const headers: Record<string, string> = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Tenant-ID': tenantId,
        'Idempotency-Key': crypto.randomUUID(),
      }
      const csrfToken = readCsrfToken()
      if (csrfToken) {
        headers['X-XSRF-TOKEN'] = csrfToken
      }

      const response = await fetch('/api/v1/tenant/role-assignments', {
        method: 'POST',
        credentials: 'include',
        headers,
        body: JSON.stringify({ membership_id: user.id, role_id: roleId }),
      })
      const body = await response.json()

      if (!response.ok) {
        toast(body.message ?? messages.errorState, 'error')

        return
      }

      const role = roles.find((item) => item.id === roleId)
      if (role) {
        setUsers((current) =>
          current.map((row) =>
            row.id === user.id
      ? { ...row, roles: [...(row.roles ?? []).filter((item) => item.id !== role.id), { id: role.id, name: role.name }] }
              : row,
          ),
        )
      }

      toast(locale === 'ar' ? 'تم تعيين الدور.' : 'Role assigned.', 'success')
    } finally {
      setAssigningFor(null)
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
              localizedRouter.get('/admin/users', event.target.value ? { status: event.target.value } : {}, { preserveState: true })
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
                key: 'roles',
                header: locale === 'ar' ? 'الأدوار' : 'Roles',
                render: (row) => {
                  const user = row as unknown as UserRow
                  const userRoles = user.roles ?? []

                  return (
                    <div className="flex flex-wrap gap-1">
                      {userRoles.length === 0 ? (
                        <span className="text-[var(--muted)]">—</span>
                      ) : (
                        userRoles.map((role) => (
                          <span key={role.id} className="ta-badge ta-badge-neutral">{role.name}</span>
                        ))
                      )}
                    </div>
                  )
                },
              },
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
                    <div className="flex flex-col gap-2">
                      <PermissionGate permission="role.assign">
                        <div className="flex flex-wrap items-end gap-2">
                          <SelectInput
                            label={locale === 'ar' ? 'تعيين دور' : 'Assign role'}
                            name={`role_${user.id}`}
                            value={selectedRoleByUser[user.id] ?? ''}
                            onChange={(event) =>
                              setSelectedRoleByUser((current) => ({ ...current, [user.id]: event.target.value }))
                            }
                            options={[{ value: '', label: locale === 'ar' ? 'اختر دوراً' : 'Select role' }, ...roleOptions]}
                          />
                          <button
                            type="button"
                            className="button-secondary cursor-pointer text-sm"
                            disabled={!selectedRoleByUser[user.id] || assigningFor === user.id}
                            onClick={() => void assignRole(user)}
                          >
                            {locale === 'ar' ? 'تعيين' : 'Assign'}
                          </button>
                        </div>
                      </PermissionGate>
                      <PermissionGate permission="membership.manage">
                        <div className="flex flex-wrap gap-2">
                          {user.status !== 'active' ? (
                            <button
                              type="button"
                              className="button-secondary cursor-pointer text-sm"
                              onClick={() => setPendingAction({ id: user.id, status: 'active' })}
                            >
                              {messages.adminActivateUser}
                            </button>
                          ) : null}
                          {user.status === 'active' ? (
                            <button
                              type="button"
                              className="button-secondary cursor-pointer text-sm"
                              onClick={() => setPendingAction({ id: user.id, status: 'suspended' })}
                            >
                              {messages.adminSuspendUser}
                            </button>
                          ) : null}
                        </div>
                      </PermissionGate>
                    </div>
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
