import { FormEvent, useMemo, useState } from 'react'
import { usePage } from '@inertiajs/react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import CheckboxInput from '@/components/forms/CheckboxInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import { PageContent, PageHeader, PermissionGate } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { groupPermissionsLocalized } from '@/lib/permissionCatalog'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import en from '@/locales/en'
import ar from '@/locales/ar'

type RoleRow = {
  id: string
  name: string
  name_en: string
  name_ar: string
  description?: string | null
  is_system: boolean
  permissions: string[]
}

type PermissionMeta = {
  key: string
  module: string
}

type Props = {
  tenantId: string
  roles: RoleRow[]
  availablePermissions: PermissionMeta[]
}

type PageProps = {
  session?: { tenant?: { id: string | number } | null }
}

function roleDisplayName(role: RoleRow, locale: 'en' | 'ar'): string {
  return locale === 'ar' ? role.name_ar : role.name_en
}

function normalizeRole(role: RoleRow): RoleRow {
  return { ...role, id: String(role.id) }
}

export default function AdminRoles({ tenantId: tenantIdProp, roles: initialRoles, availablePermissions }: Props) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const { toast } = useToast()
  const { props } = usePage<PageProps>()
  const tenantId = String(tenantIdProp ?? props.session?.tenant?.id ?? '')
  const normalizedInitialRoles = useMemo(() => initialRoles.map(normalizeRole), [initialRoles])
  const [roles, setRoles] = useState(normalizedInitialRoles)
  const [selectedRoleId, setSelectedRoleId] = useState<string | null>(normalizedInitialRoles[0]?.id ?? null)
  const [draftPermissions, setDraftPermissions] = useState<string[]>(normalizedInitialRoles[0]?.permissions ?? [])
  const [createForm, setCreateForm] = useState({ name_en: '', name_ar: '', description: '' })
  const [editNames, setEditNames] = useState({
    name_en: normalizedInitialRoles[0]?.name_en ?? '',
    name_ar: normalizedInitialRoles[0]?.name_ar ?? '',
  })
  const [saving, setSaving] = useState(false)
  const [creating, setCreating] = useState(false)

  const selectedRole = roles.find((role) => role.id === selectedRoleId) ?? null
  const permissionGroups = useMemo(
    () => groupPermissionsLocalized(availablePermissions, locale),
    [availablePermissions, locale],
  )

  function selectRole(role: RoleRow) {
    const normalized = normalizeRole(role)
    setSelectedRoleId(normalized.id)
    setDraftPermissions(normalized.permissions)
    setEditNames({ name_en: normalized.name_en, name_ar: normalized.name_ar })
  }

  function togglePermission(key: string) {
    setDraftPermissions((current) =>
      current.includes(key) ? current.filter((item) => item !== key) : [...current, key],
    )
  }

  async function createRole(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (!tenantId || tenantId === 'undefined') {
      toast(locale === 'ar' ? 'تعذر تحديد المستأجر الحالي.' : 'Unable to resolve the current tenant.', 'error')
      return
    }

    setCreating(true)

    try {
      const body = await apiFetch<RoleRow>('/api/v1/tenant/roles', {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: createForm,
      })

      const created = normalizeRole(body)
      setRoles((current) => [...current, created].sort((a, b) => roleDisplayName(a, locale).localeCompare(roleDisplayName(b, locale), locale)))
      setCreateForm({ name_en: '', name_ar: '', description: '' })
      selectRole(created)
      toast(locale === 'ar' ? 'تم إنشاء الدور.' : 'Role created.', 'success')
    } catch (error) {
      const message = error instanceof ApiFetchError ? error.message : messages.errorState
      toast(message, 'error')
      window.dispatchEvent(new CustomEvent('zonetec:tour-hint', {
        detail: {
          message: locale === 'ar'
            ? 'تأكد من اختيار دور غير نظامي ومن وجود صلاحية إدارة الأدوار.'
            : 'Make sure you selected a custom role and that you have permission to manage roles.',
        },
      }))
    } finally {
      setCreating(false)
    }
  }

  async function saveRole() {
    if (!selectedRole || selectedRole.is_system) {
      return
    }

    if (!tenantId || tenantId === 'undefined') {
      toast(locale === 'ar' ? 'تعذر تحديد المستأجر الحالي.' : 'Unable to resolve the current tenant.', 'error')
      return
    }

    setSaving(true)

    try {
      await apiFetch(`/api/v1/tenant/roles/${String(selectedRole.id)}/permissions`, {
        method: 'PUT',
        tenantId,
        idempotency: true,
        body: { permissions: draftPermissions },
      })

      const updatedRole = await apiFetch<RoleRow>(`/api/v1/tenant/roles/${String(selectedRole.id)}`, {
        method: 'PATCH',
        tenantId,
        idempotency: true,
        body: editNames,
      })

      setRoles((current) =>
        current.map((role) =>
          role.id === selectedRole.id
            ? normalizeRole({
                ...role,
                ...updatedRole,
                permissions: draftPermissions,
              })
            : role,
        ),
      )
      toast(messages.adminRoleUpdated, 'success')
    } catch (error) {
      const message = error instanceof ApiFetchError ? error.message : messages.errorState
      toast(message, 'error')
      window.dispatchEvent(new CustomEvent('zonetec:tour-hint', {
        detail: {
          message: locale === 'ar'
            ? 'تأكد من اختيار دور غير نظامي ومن وجود صلاحية إدارة الأدوار.'
            : 'Make sure you selected a custom role and that you have permission to manage roles.',
        },
      }))
    } finally {
      setSaving(false)
    }
  }

  async function deleteRole(role: RoleRow) {
    if (role.is_system) {
      return
    }

    try {
      await apiFetch(`/api/v1/tenant/roles/${String(role.id)}`, {
        method: 'DELETE',
        tenantId,
        idempotency: true,
      })

      setRoles((current) => current.filter((item) => item.id !== role.id))
      if (selectedRoleId === role.id) {
        setSelectedRoleId(null)
        setDraftPermissions([])
      }
      toast(locale === 'ar' ? 'تم حذف الدور.' : 'Role deleted.', 'success')
    } catch (error) {
      const message = error instanceof ApiFetchError ? error.message : messages.errorState
      toast(message, 'error')
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
        <PermissionGate permission="role.manage">
          <form className="state-panel mb-6 grid gap-4 md:grid-cols-2" onSubmit={createRole}>
            <h2 className="md:col-span-2 text-lg font-semibold">
              {locale === 'ar' ? 'إضافة دور جديد' : 'Add new role'}
            </h2>
            <TextInput
              label={locale === 'ar' ? 'اسم الدور بالإنجليزية' : 'Role name (English)'}
              name="name_en"
              value={createForm.name_en}
              onChange={(event) => setCreateForm({ ...createForm, name_en: event.target.value })}
              required
            />
            <TextInput
              label={locale === 'ar' ? 'اسم الدور بالعربية' : 'Role name (Arabic)'}
              name="name_ar"
              value={createForm.name_ar}
              onChange={(event) => setCreateForm({ ...createForm, name_ar: event.target.value })}
              required
            />
            <TextareaInput
              wrapperClassName="md:col-span-2"
              label={locale === 'ar' ? 'الوصف' : 'Description'}
              name="description"
              value={createForm.description}
              onChange={(event) => setCreateForm({ ...createForm, description: event.target.value })}
            />
            <div className="md:col-span-2">
              <SubmitButtonWithLoader
                loading={creating}
                label={locale === 'ar' ? 'إنشاء الدور' : 'Create role'}
              />
            </div>
          </form>
        </PermissionGate>

        {roles.length === 0 ? (
          <EmptyState title={messages.adminNoRoles} />
        ) : (
          <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <div className="space-y-2">
              {roles.map((role) => (
                <button
                  key={role.id}
                  type="button"
                  className={`w-full rounded-xl border p-4 text-start ${selectedRoleId === role.id ? 'border-[var(--brand)] bg-[var(--brand-soft)]' : 'border-[var(--border)] bg-[var(--surface-elevated)]'}`}
                  onClick={() => selectRole(role)}
                >
                  <div className="flex items-center justify-between gap-3">
                    <span className="font-medium">{roleDisplayName(role, locale)}</span>
                    {role.is_system ? <StatusBadge status="system" /> : null}
                  </div>
                  {role.description ? <p className="mt-1 text-sm text-[var(--muted)]">{role.description}</p> : null}
                  <p className="mt-2 text-xs text-[var(--muted)]">{role.permissions.length} {locale === 'ar' ? 'صلاحية' : 'permissions'}</p>
                </button>
              ))}
            </div>

            {selectedRole ? (
              <div className="rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <h2 className="text-lg font-semibold">{roleDisplayName(selectedRole, locale)}</h2>
                  {!selectedRole.is_system && (
                    <PermissionGate permission="role.manage">
                      <button type="button" className="button-secondary text-sm text-red-600" onClick={() => void deleteRole(selectedRole)}>
                        {locale === 'ar' ? 'حذف الدور' : 'Delete role'}
                      </button>
                    </PermissionGate>
                  )}
                </div>
                {selectedRole.is_system ? (
                  <p className="mt-2 text-sm text-amber-700 dark:text-amber-300">{messages.adminSystemRoleProtected}</p>
                ) : (
                  <PermissionGate permission="role.manage">
                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                      <TextInput
                        label={locale === 'ar' ? 'الاسم بالإنجليزية' : 'English name'}
                        name="edit_name_en"
                        value={editNames.name_en}
                        onChange={(event) => setEditNames((current) => ({ ...current, name_en: event.target.value }))}
                      />
                      <TextInput
                        label={locale === 'ar' ? 'الاسم بالعربية' : 'Arabic name'}
                        name="edit_name_ar"
                        value={editNames.name_ar}
                        onChange={(event) => setEditNames((current) => ({ ...current, name_ar: event.target.value }))}
                      />
                    </div>
                    <div className="mt-4 max-h-[28rem] space-y-5 overflow-y-auto">
                      {permissionGroups.map((group) => (
                        <section key={group.module}>
                          <h3 className="mb-2 text-sm font-semibold text-[var(--brand)]">{group.label}</h3>
                          <div className="grid gap-2 sm:grid-cols-2">
                            {group.items.map((permission) => (
                              <CheckboxInput
                                key={permission.key}
                                label={permission.label}
                                name={permission.key}
                                checked={draftPermissions.includes(permission.key)}
                                onChange={() => togglePermission(permission.key)}
                              />
                            ))}
                          </div>
                        </section>
                      ))}
                    </div>
                    <button type="button" className="button-primary mt-4 cursor-pointer" disabled={saving} onClick={() => void saveRole()}>
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
