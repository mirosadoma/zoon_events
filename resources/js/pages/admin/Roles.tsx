import { FormEvent, useMemo, useState } from 'react'
import { usePage } from '@inertiajs/react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import CheckboxInput from '@/components/forms/CheckboxInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import { PageContent, PageHeader, PermissionGate } from '@/components/layout'
import ValidationHintPopover from '@/components/feedback/ValidationHintPopover'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { useFormValidation } from '@/hooks/useFormValidation'
import { groupPermissionsLocalized } from '@/lib/permissionCatalog'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { ROLE_FIELD_LABELS, formFieldProps } from '@/lib/formatValidationErrors'
import en from '@/locales/en'
import ar from '@/locales/ar'

type RoleRow = {
  id: string
  name: string
  name_en?: string
  name_ar?: string
  description?: string | null
  is_system: boolean
  permissions: string[]
}

type PermissionMeta = {
  key: string
  module: string
}

type Props = {
  scope?: 'tenant' | 'platform'
  tenantId?: string | null
  roles: RoleRow[]
  availablePermissions: PermissionMeta[]
}

type PageProps = {
  session?: { tenant?: { id: string | number } | null }
  can?: Record<string, boolean>
}

function roleDisplayName(role: RoleRow, locale: 'en' | 'ar'): string {
  return locale === 'ar' ? (role.name_ar ?? role.name_en ?? role.name) : (role.name_en ?? role.name)
}

function normalizeRole(role: RoleRow): RoleRow {
  return {
    ...role,
    id: String(role.id),
    name_en: role.name_en ?? role.name,
    name_ar: role.name_ar ?? role.name_en ?? role.name,
  }
}

export default function AdminRoles({ scope = 'tenant', tenantId: tenantIdProp, roles: initialRoles, availablePermissions }: Props) {
  const { locale, t } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const { toast } = useToast()
  const createValidation = useFormValidation({
    titleKey: 'couldNotSaveRole',
    fieldLabels: ROLE_FIELD_LABELS,
  })
  const editValidation = useFormValidation({
    titleKey: 'couldNotSaveRole',
    fieldLabels: ROLE_FIELD_LABELS,
  })
  const { props } = usePage<PageProps>()
  const tenantId = String(tenantIdProp ?? props.session?.tenant?.id ?? '')
  const isPlatform = scope === 'platform'
  const managePermission = isPlatform ? 'platform.role.manage' : 'role.manage'
  const canManageRoles = props.can?.[managePermission] === true
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
  const isSystemRole = selectedRole?.is_system === true
  const permissionsReadOnly = isSystemRole || !canManageRoles
  const displayedPermissions = permissionsReadOnly ? (selectedRole?.permissions ?? []) : draftPermissions
  const permissionGroups = useMemo(
    () => groupPermissionsLocalized(availablePermissions, locale),
    [availablePermissions, locale],
  )

  function selectRole(role: RoleRow) {
    const normalized = normalizeRole(role)
    setSelectedRoleId(normalized.id)
    setDraftPermissions(normalized.permissions)
    setEditNames({ name_en: normalized.name_en ?? normalized.name, name_ar: normalized.name_ar ?? normalized.name })
  }

  function togglePermission(key: string) {
    setDraftPermissions((current) =>
      current.includes(key) ? current.filter((item) => item !== key) : [...current, key],
    )
  }

  async function createRole(event: FormEvent<HTMLFormElement>) {
    event.preventDefault()

    if (!isPlatform && (!tenantId || tenantId === 'undefined')) {
      toast(t('adminRolesTenantUnavailable'), 'error')
      return
    }

    setCreating(true)
    createValidation.clearValidation()

    try {
      const body = await apiFetch<RoleRow>(isPlatform ? '/api/v1/platform/roles' : '/api/v1/tenant/roles', {
        method: 'POST',
        tenantId: isPlatform ? undefined : tenantId,
        idempotency: true,
        body: isPlatform
          ? { name: createForm.name_en, description: createForm.description }
          : createForm,
      })

      const created = normalizeRole(body)
      setRoles((current) => [...current, created].sort((a, b) => roleDisplayName(a, locale).localeCompare(roleDisplayName(b, locale), locale)))
      setCreateForm({ name_en: '', name_ar: '', description: '' })
      selectRole(created)
      toast(t('adminRolesCreated'), 'success')
    } catch (error) {
      if (!createValidation.applyApiError(error)) {
        const message = error instanceof ApiFetchError ? error.message : messages.errorState
        toast(message, 'error')
      } else {
        toast(error instanceof ApiFetchError ? error.message : messages.errorState, 'error')
      }
    } finally {
      setCreating(false)
    }
  }

  async function saveRole() {
    if (!selectedRole || selectedRole.is_system) {
      return
    }

    if (!isPlatform && (!tenantId || tenantId === 'undefined')) {
      toast(t('adminRolesTenantUnavailable'), 'error')
      return
    }

    setSaving(true)
    editValidation.clearValidation()

    try {
      const updatedRole = await apiFetch<RoleRow>(isPlatform ? `/api/v1/platform/roles/${String(selectedRole.id)}` : `/api/v1/tenant/roles/${String(selectedRole.id)}`, {
        method: 'PATCH',
        tenantId: isPlatform ? undefined : tenantId,
        idempotency: true,
        body: isPlatform
          ? { name: editNames.name_en, permissions: draftPermissions }
          : editNames,
      })

      if (!isPlatform) {
        await apiFetch(`/api/v1/tenant/roles/${String(selectedRole.id)}/permissions`, {
          method: 'PUT',
          tenantId,
          idempotency: true,
          body: { permissions: draftPermissions },
        })
      }

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
      if (!editValidation.applyApiError(error)) {
        const message = error instanceof ApiFetchError ? error.message : messages.errorState
        toast(message, 'error')
      } else {
        toast(error instanceof ApiFetchError ? error.message : messages.errorState, 'error')
      }
    } finally {
      setSaving(false)
    }
  }

  async function deleteRole(role: RoleRow) {
    if (role.is_system) {
      return
    }

    if (!isPlatform && (!tenantId || tenantId === 'undefined')) {
      toast(t('adminRolesTenantUnavailable'), 'error')
      return
    }

    try {
      await apiFetch(isPlatform ? `/api/v1/platform/roles/${String(role.id)}` : `/api/v1/tenant/roles/${String(role.id)}`, {
        method: 'DELETE',
        tenantId: isPlatform ? undefined : tenantId,
        idempotency: true,
      })

      const remaining = roles.filter((item) => item.id !== role.id)
      setRoles(remaining)

      if (selectedRoleId === role.id) {
        const nextRole = remaining[0] ?? null
        if (nextRole) {
          selectRole(nextRole)
        } else {
          setSelectedRoleId(null)
          setDraftPermissions([])
        }
      }

      toast(t('adminRolesDeleted'), 'success')
    } catch (error) {
      const message = error instanceof ApiFetchError ? error.message : messages.errorState
      toast(message, 'error')
    }
  }

  return (
    <DashboardLayout title={messages.roles}>
      <PageHeader
        title={isPlatform ? t('adminRolesPlatformRoles') : messages.roles}
        description={isPlatform ? t('adminRolesPlatformRolesDesc') : messages.adminRolesDescription}
        breadcrumbs={[
          { label: messages.overview, href: '/dashboard' },
          { label: isPlatform ? messages.navGroupPlatform : messages.administration, href: isPlatform ? '/platform/tenants' : '/admin/users' },
          { label: isPlatform ? t('adminRolesPlatformRoles') : messages.roles },
        ]}
      />
      <PageContent>
        <PermissionGate permission={managePermission}>
          <form className="state-panel mb-6 grid gap-4 md:grid-cols-2" onSubmit={createRole}>
            <h2 className="md:col-span-2 text-lg font-semibold">
              {t('adminRolesAddNew')}
            </h2>
            <TextInput
              label={isPlatform ? t('adminRolesRoleName') : t('adminRolesRoleNameEn')}
              name="name_en"
              value={createForm.name_en}
              onChange={(event) => setCreateForm({ ...createForm, name_en: event.target.value })}
              error={createValidation.fieldError(isPlatform ? 'name' : 'name_en')}
              {...formFieldProps(isPlatform ? 'name' : 'name_en')}
              required
            />
            {!isPlatform && (
              <TextInput
                label={t('adminRolesRoleNameAr')}
                name="name_ar"
                value={createForm.name_ar}
                onChange={(event) => setCreateForm({ ...createForm, name_ar: event.target.value })}
                error={createValidation.fieldError('name_ar')}
                {...formFieldProps('name_ar')}
                required
              />
            )}
            <TextareaInput
              wrapperClassName="md:col-span-2"
              label={t('adminRolesFieldDescription')}
              name="description"
              value={createForm.description}
              onChange={(event) => setCreateForm({ ...createForm, description: event.target.value })}
              error={createValidation.fieldError('description')}
              {...formFieldProps('description')}
            />
            <div className="md:col-span-2">
              <SubmitButtonWithLoader
                loading={creating}
                label={t('adminRolesCreateRole')}
              />
            </div>
          </form>
        </PermissionGate>

        {roles.length === 0 ? (
          <EmptyState title={messages.adminNoRoles} />
        ) : (
          <div className="grid gap-6 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.4fr)]">
            <div className="max-h-[calc(100vh-14rem)] space-y-2 overflow-y-auto overscroll-contain pe-1">
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
                <p className="mt-2 text-xs text-[var(--muted)]">{role.permissions.length} {t('adminRolesPermissions')}</p>
                </button>
              ))}
            </div>

            {selectedRole ? (
              <div className="flex max-h-[calc(100vh-14rem)] flex-col rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] p-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                  <h2 className="text-lg font-semibold">{roleDisplayName(selectedRole, locale)}</h2>
                  {!selectedRole.is_system && (
                    <PermissionGate permission={managePermission}>
                      <button type="button" className="button-secondary text-sm text-red-600" onClick={() => void deleteRole(selectedRole)}>
                        {t('adminRolesDeleteRole')}
                      </button>
                    </PermissionGate>
                  )}
                </div>
                {selectedRole.is_system ? (
                  <p className="mt-2 text-sm text-amber-700 dark:text-amber-300">{messages.adminSystemRoleProtected}</p>
                ) : (
                  <PermissionGate permission={managePermission}>
                    <div className="mt-4 grid gap-4 md:grid-cols-2">
                      <TextInput
                        label={isPlatform ? t('adminRolesRoleName') : t('adminRolesEnglishName')}
                        name="edit_name_en"
                        value={editNames.name_en}
                        onChange={(event) => setEditNames((current) => ({ ...current, name_en: event.target.value }))}
                        error={editValidation.fieldError(isPlatform ? 'name' : 'name_en')}
                        {...formFieldProps(isPlatform ? 'name' : 'name_en')}
                      />
                      {!isPlatform && (
                        <TextInput
                          label={t('adminRolesArabicName')}
                          name="edit_name_ar"
                          value={editNames.name_ar}
                          onChange={(event) => setEditNames((current) => ({ ...current, name_ar: event.target.value }))}
                          error={editValidation.fieldError('name_ar')}
                          {...formFieldProps('name_ar')}
                        />
                      )}
                    </div>
                  </PermissionGate>
                )}
                <div className="mt-4 min-h-0 flex-1 space-y-5 overflow-y-auto overscroll-contain pe-1">
                  {permissionGroups.map((group) => (
                    <section key={group.module}>
                      <h3 className="mb-2 text-sm font-semibold text-[var(--brand)]">{group.label}</h3>
                      <div className="grid gap-2 sm:grid-cols-2">
                        {group.items.map((permission) => (
                          <CheckboxInput
                            key={permission.key}
                            label={permission.label}
                            name={permission.key}
                            checked={displayedPermissions.includes(permission.key)}
                            disabled={permissionsReadOnly}
                            onChange={() => {
                              if (permissionsReadOnly) {
                                return
                              }

                              togglePermission(permission.key)
                            }}
                          />
                        ))}
                      </div>
                    </section>
                  ))}
                </div>
                {!permissionsReadOnly ? (
                  <PermissionGate permission={managePermission}>
                    <button type="button" className="button-primary mt-4 cursor-pointer" disabled={saving} onClick={() => void saveRole()}>
                      {messages.save}
                    </button>
                  </PermissionGate>
                ) : null}
              </div>
            ) : null}
          </div>
        )}
        <ValidationHintPopover {...createValidation.hintProps} />
        <ValidationHintPopover {...editValidation.hintProps} />
      </PageContent>
    </DashboardLayout>
  )
}
