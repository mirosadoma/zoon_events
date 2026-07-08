import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import CheckboxInput from '@/components/forms/CheckboxInput'
import { PageContent, PageHeader, PermissionGate } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import en from '@/locales/en'
import ar from '@/locales/ar'

type RoleRow = {
  id: string
  name: string
  description?: string | null
  is_system: boolean
  permissions: string[]
}

type Props = {
  tenantId: string
  roles: RoleRow[]
  availablePermissions: string[]
}

export default function AdminRoles({ tenantId, roles: initialRoles, availablePermissions }: Props) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const { toast } = useToast()
  const [roles, setRoles] = useState(initialRoles)
  const [selectedRoleId, setSelectedRoleId] = useState<string | null>(initialRoles[0]?.id ?? null)
  const [draftPermissions, setDraftPermissions] = useState<string[]>(initialRoles[0]?.permissions ?? [])
  const [saving, setSaving] = useState(false)

  const selectedRole = roles.find((role) => role.id === selectedRoleId) ?? null

  function selectRole(role: RoleRow) {
    setSelectedRoleId(role.id)
    setDraftPermissions(role.permissions)
  }

  function togglePermission(key: string) {
    setDraftPermissions((current) =>
      current.includes(key) ? current.filter((item) => item !== key) : [...current, key],
    )
  }

  async function savePermissions() {
    if (!selectedRole || selectedRole.is_system) {
      return
    }

    setSaving(true)

    try {
      const response = await fetch(`/api/v1/tenant/roles/${selectedRole.id}/permissions`, {
        method: 'PUT',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-Tenant-ID': tenantId,
          'Idempotency-Key': crypto.randomUUID(),
        },
        body: JSON.stringify({ permissions: draftPermissions }),
      })

      const body = await response.json()

      if (!response.ok) {
        toast(body.message ?? messages.errorState, 'error')
        return
      }

      setRoles((current) =>
        current.map((role) =>
          role.id === selectedRole.id ? { ...role, permissions: draftPermissions } : role,
        ),
      )
      toast(messages.adminRoleUpdated, 'success')
    } finally {
      setSaving(false)
    }
  }

  return (
    <DashboardLayout title={messages.roles}>
      <PageHeader
        title={messages.roles}
        description={messages.adminRolesDescription}
        breadcrumbs={[
          { label: messages.overview, href: '/dashboard' },
          { label: messages.administration, href: '/admin/users' },
          { label: messages.roles },
        ]}
      />
      <PageContent>
        {roles.length === 0 ? (
          <EmptyState title={messages.adminNoRoles} />
        ) : (
          <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <div className="space-y-2">
              {roles.map((role) => (
                <button
                  key={role.id}
                  type="button"
                  className={`w-full rounded-xl border p-4 text-left ${selectedRoleId === role.id ? 'border-sky-500 bg-sky-50 dark:bg-sky-950/30' : 'border-slate-200 dark:border-slate-800'}`}
                  onClick={() => selectRole(role)}
                >
                  <div className="flex items-center justify-between gap-3">
                    <span className="font-medium">{role.name}</span>
                    {role.is_system ? <StatusBadge status="system" /> : null}
                  </div>
                  {role.description ? <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">{role.description}</p> : null}
                  <p className="mt-2 text-xs text-slate-500">{role.permissions.length} permissions</p>
                </button>
              ))}
            </div>

            {selectedRole ? (
              <div className="rounded-xl border border-slate-200 p-4 dark:border-slate-800">
                <h2 className="text-lg font-semibold">{selectedRole.name}</h2>
                {selectedRole.is_system ? (
                  <p className="mt-2 text-sm text-amber-700 dark:text-amber-300">{messages.adminSystemRoleProtected}</p>
                ) : (
                  <PermissionGate permission="role.manage">
                    <div className="mt-4 grid max-h-[28rem] gap-2 overflow-y-auto sm:grid-cols-2">
                      {availablePermissions.map((permission) => (
                        <CheckboxInput
                          key={permission}
                          label={permission}
                          name={permission}
                          checked={draftPermissions.includes(permission)}
                          onChange={() => togglePermission(permission)}
                        />
                      ))}
                    </div>
                    <button type="button" className="button-primary mt-4" disabled={saving} onClick={() => void savePermissions()}>
                      {messages.save}
                    </button>
                  </PermissionGate>
                )}
              </div>
            ) : null}
          </div>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
