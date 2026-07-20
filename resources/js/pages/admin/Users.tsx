import { FormEvent, useMemo, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import SelectInput from '@/components/forms/SelectInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import { PageContent, PageHeader, PermissionGate } from '@/components/layout'
import ValidationHintPopover from '@/components/feedback/ValidationHintPopover'
import ReasonModal from '@/components/modals/ReasonModal'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import FiltersBar from '@/components/tables/FiltersBar'
import { useFormValidation } from '@/hooks/useFormValidation'
import { useLocale } from '@/hooks/useLocale'
import { useTenantId } from '@/hooks/useTenantId'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { USER_INVITE_FIELD_LABELS, formFieldProps } from '@/lib/formatValidationErrors'
import type { AppLocale } from '@/lib/localePath'
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

type MembershipResponse = {
  id: string | number
  status: string
  created_at?: string | null
  user: {
    id: string | number
    name: string
    email: string
    status: string
  }
}

type Props = {
  tenantId: string
  users: UserRow[]
  roles?: RoleOption[]
}

function membershipToRow(membership: MembershipResponse): UserRow {
  return {
    id: String(membership.id),
    name: membership.user.name,
    email: membership.user.email,
    status: membership.status,
    user_status: membership.user.status,
    created_at: membership.created_at ?? null,
    roles: [],
  }
}

export default function AdminUsers({ tenantId: pageTenantId, users: initialUsers, roles = [] }: Props) {
  const { locale, t } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const { toast } = useToast()
  const inviteValidation = useFormValidation({
    titleKey: 'couldNotSaveUser',
    fieldLabels: USER_INVITE_FIELD_LABELS,
  })
  const tenantId = useTenantId(pageTenantId)
  const localizedRouter = useLocalizedRouter()
  const [users, setUsers] = useState(initialUsers)
  const [statusFilter, setStatusFilter] = useState('')
  const [pendingAction, setPendingAction] = useState<{ id: string; status: string } | null>(null)
  const [loading, setLoading] = useState(false)
  const [assigningFor, setAssigningFor] = useState<string | null>(null)
  const [selectedRoleByUser, setSelectedRoleByUser] = useState<Record<string, string>>({})
  const [addOpen, setAddOpen] = useState(false)
  const [inviting, setInviting] = useState(false)
  const [inviteForm, setInviteForm] = useState({
    name: '',
    email: '',
    password: '',
    preferred_locale: locale,
  })

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

  function resetInviteForm() {
    setInviteForm({
      name: '',
      email: '',
      password: '',
      preferred_locale: locale,
    })
    inviteValidation.clearValidation()
  }

  async function applyStatusChange(reason: string) {
    if (!pendingAction) {
      return
    }

    if (!tenantId) {
      toast(t('adminUsersTenantUnavailable'), 'error')

      return
    }

    setLoading(true)

    try {
      await apiFetch(`/api/v1/tenant/memberships/${pendingAction.id}`, {
        method: 'PATCH',
        tenantId,
        idempotency: true,
        body: { status: pendingAction.status, reason },
      })

      setUsers((current) =>
        current.map((user) =>
          user.id === pendingAction.id ? { ...user, status: pendingAction.status } : user,
        ),
      )
      toast(messages.adminUserUpdated, 'success')
      setPendingAction(null)
    } catch (error) {
      toast(
        error instanceof ApiFetchError ? error.message : messages.errorState,
        'error',
      )
    } finally {
      setLoading(false)
    }
  }

  async function assignRole(user: UserRow) {
    const roleId = selectedRoleByUser[user.id]
    if (!roleId) {
      return
    }

    if (!tenantId) {
      toast(t('adminUsersTenantUnavailable'), 'error')

      return
    }

    setAssigningFor(user.id)

    try {
      await apiFetch('/api/v1/tenant/role-assignments', {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: { membership_id: String(user.id), role_id: String(roleId) },
      })

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

      toast(t('adminUsersRoleAssigned'), 'success')
    } catch (error) {
      toast(
        error instanceof ApiFetchError ? error.message : messages.errorState,
        'error',
      )
    } finally {
      setAssigningFor(null)
    }
  }

  async function inviteUser(event: FormEvent) {
    event.preventDefault()
    setInviting(true)
    inviteValidation.clearValidation()

    try {
      const membership = await apiFetch<MembershipResponse>('/api/v1/tenant/memberships', {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: inviteForm,
      })

      setUsers((current) => [membershipToRow(membership), ...current])
      toast(messages.adminAddUserSuccess, 'success')
      setAddOpen(false)
      resetInviteForm()
    } catch (error) {
      if (error instanceof ApiFetchError) {
        inviteValidation.applyApiError(error)
        toast(error.message, 'error')
      } else {
        toast(messages.errorState, 'error')
      }
    } finally {
      setInviting(false)
    }
  }

  return (
    <DashboardLayout title={messages.users}>
      <PageHeader
        title={messages.users}
        description={t('adminUsersDescription_')}
        breadcrumbs={[
          { label: messages.overview, href: '/dashboard' },
          { label: messages.administration, href: '/admin/users' },
          { label: messages.users },
        ]}
        actions={(
          <PermissionGate permission="membership.manage">
            <button
              type="button"
              className="button-primary cursor-pointer"
              onClick={() => {
                resetInviteForm()
                setAddOpen(true)
              }}
            >
              {messages.adminAddUser}
            </button>
          </PermissionGate>
        )}
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
          <EmptyState
            title={messages.adminNoUsers}
            detail={t('adminUsersEmptyDetail')}
            action={(
              <PermissionGate permission="membership.manage">
                <button
                  type="button"
                  className="button-primary cursor-pointer"
                  onClick={() => {
                    resetInviteForm()
                    setAddOpen(true)
                  }}
                >
                  {messages.adminAddUser}
                </button>
              </PermissionGate>
            )}
          />
        ) : (
          <DataTable
            rows={filteredUsers as unknown as Record<string, unknown>[]}
            getRowKey={(row) => String(row.id)}
            columns={[
              { key: 'name', header: messages.profileName },
              { key: 'email', header: messages.profileEmail },
              {
                key: 'roles',
                header: t('adminUsersRoles'),
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
                            label={t('adminUsersAssignRole')}
                            name={`role_${user.id}`}
                            value={selectedRoleByUser[user.id] ?? ''}
                            onChange={(event) =>
                              setSelectedRoleByUser((current) => ({ ...current, [user.id]: event.target.value }))
                            }
                            options={[{ value: '', label: t('adminUsersSelectRole') }, ...roleOptions]}
                          />
                          <button
                            type="button"
                            className="button-secondary cursor-pointer text-sm"
                            disabled={!selectedRoleByUser[user.id] || assigningFor === user.id}
                            onClick={() => void assignRole(user)}
                          >
                            {t('adminUsersAssign')}
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

      {addOpen ? (
        <div className="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4" role="dialog" aria-modal="true" aria-labelledby="invite-user-title">
          <form className="relative ta-card w-full max-w-lg shadow-xl" onSubmit={(event) => void inviteUser(event)}>
            <h2 id="invite-user-title" className="text-lg font-semibold">{messages.adminAddUserTitle}</h2>
            <p className="mt-2 text-sm text-[var(--muted)]">{messages.adminAddUserDescription}</p>
            <div className="mt-4 grid gap-4">
              <TextInput
                label={messages.profileName}
                name="name"
                value={inviteForm.name}
                required
                error={inviteValidation.fieldError('name')}
                {...formFieldProps('name')}
                onChange={(event) => setInviteForm((current) => ({ ...current, name: event.target.value }))}
              />
              <TextInput
                label={messages.profileEmail}
                name="email"
                type="email"
                value={inviteForm.email}
                required
                error={inviteValidation.fieldError('email')}
                {...formFieldProps('email')}
                onChange={(event) => setInviteForm((current) => ({ ...current, email: event.target.value }))}
              />
              <TextInput
                label={t('adminUsersPassword')}
                name="password"
                type="password"
                value={inviteForm.password}
                required
                error={inviteValidation.fieldError('password')}
                {...formFieldProps('password')}
                onChange={(event) => setInviteForm((current) => ({ ...current, password: event.target.value }))}
              />
              <SelectInput
                label={messages.adminDefaultLocale}
                name="preferred_locale"
                value={inviteForm.preferred_locale}
                onChange={(event) => setInviteForm((current) => ({ ...current, preferred_locale: event.target.value as AppLocale }))}
                options={[
                  { value: 'en', label: 'English' },
                  { value: 'ar', label: 'العربية' },
                ]}
                error={inviteValidation.fieldError('preferred_locale')}
                {...formFieldProps('preferred_locale')}
              />
            </div>
            <ValidationHintPopover {...inviteValidation.hintProps} />
            <div className="mt-6 flex justify-end gap-3">
              <button
                type="button"
                className="button-secondary"
                disabled={inviting}
                onClick={() => {
                  setAddOpen(false)
                  resetInviteForm()
                }}
              >
                {messages.cancel}
              </button>
              <SubmitButtonWithLoader loading={inviting} label={messages.adminAddUser} />
            </div>
          </form>
        </div>
      ) : null}

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
