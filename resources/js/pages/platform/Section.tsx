import { FormEvent, useMemo, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { StatCard } from '@/components/cards'
import { EmptyState } from '@/components/feedback'
import TextInput from '@/components/forms/TextInput'
import SelectInput from '@/components/forms/SelectInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { PageContent, PageHeader } from '@/components/layout'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { formatDateOnly } from '@/lib/formatters'
import en from '@/locales/en'
import ar from '@/locales/ar'

type HealthCheck = {
  category: string
  status: string
  duration_ms: number
  reason_code: string | null
}

type HealthReport = {
  status: string
  checked_at: string
  checks: HealthCheck[]
}

type TenantAdmin = {
  id: string
  name: string
  email: string
  phone: string | null
}

type TenantRow = {
  id: string
  name: string
  slug: string
  status: string
  default_locale: string
  timezone: string
  created_at: string | null
  admin: TenantAdmin | null
}

type PlatformRoleRow = {
  id: string
  name: string
  description: string
  is_system: boolean
  permissions: string[]
  created_at: string | null
}

type PlatformUserRow = {
  id: string
  name: string
  email: string
  status: string
  created_at: string | null
  roles: { id: string; name: string }[]
}

type EventRow = {
  id: string
  name: string
  name_ar: string
  slug: string
  organizer: string
  tenant_id: string
  tenant_slug: string | null
  status: string
  event_type: string
  timezone?: string | null
  start_at: string | null
  created_at: string | null
}

type SectionProps = {
  section: string
  // eslint-disable-next-line @typescript-eslint/no-explicit-any
  rows: any
  canManage: boolean
  health?: HealthReport | null
  platformRoles?: PlatformRoleRow[]
}

const TITLES: Record<string, { en: string; ar: string }> = {
  'all-events': { en: 'All Events', ar: 'كل الفعاليات' },
  users: { en: 'Platform Admins', ar: 'مشرفو المنصة' },
  roles: { en: 'Platform Roles', ar: 'أدوار المنصة' },
  tenants: { en: 'Organizers', ar: 'المنظمون' },
  audit: { en: 'Platform audit', ar: 'تدقيق المنصة' },
  health: { en: 'Platform health', ar: 'صحة المنصة' },
  'feature-flags': { en: 'Feature flags', ar: 'أعلام الميزات' },
  configuration: { en: 'Configuration schemas', ar: 'مخططات التكوين' },
}

export default function PlatformSection({ section, rows, canManage, health, platformRoles = [] }: SectionProps) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [loading, setLoading] = useState(false)
  const title = TITLES[section]?.[locale] ?? section

  const healthCheckColumns = useMemo(
    () => [
      {
        key: 'category',
        header: t('platformSectionCategory'),
        render: (row: HealthCheck) => row.category.replace(/_/g, ' '),
      },
      {
        key: 'status',
        header: t('platformSectionStatus'),
        render: (row: HealthCheck) => <StatusBadge status={row.status} />,
      },
      {
        key: 'duration_ms',
        header: t('platformSectionDuration'),
        render: (row: HealthCheck) => String(row.duration_ms),
      },
      {
        key: 'reason_code',
        header: t('platformSectionReasonCode'),
        render: (row: HealthCheck) => row.reason_code ?? '—',
      },
    ],
    [locale, t],
  )

  function formatCheckedAt(value: string): string {
    const parsed = new Date(value)
    if (Number.isNaN(parsed.getTime())) {
      return value
    }

    return parsed.toLocaleString(locale === 'ar' ? 'ar-EG' : 'en-GB', {
      dateStyle: 'medium',
      timeStyle: 'medium',
    })
  }

  const columns = useMemo(() => {
    if (rows.length === 0 || section === 'tenants' || section === 'users' || section === 'roles' || section === 'all-events') {
      return []
    }

    return Object.keys(rows[0]).map((key) => ({
      key,
      header: key.replace(/_/g, ' '),
      render: (row: Record<string, unknown>) => {
        const value = row[key]

        if (key === 'status' || key === 'outcome') {
          return <StatusBadge status={String(value)} />
        }

        if (Array.isArray(value)) {
          return <span className="text-xs text-[var(--muted)]">{value.join(', ')}</span>
        }

        if (typeof value === 'object' && value !== null) {
          return <span className="text-xs">{JSON.stringify(value)}</span>
        }

        return String(value ?? '')
      },
    }))
  }, [rows, section])

  async function submitApi(url: string, method: string, body: Record<string, unknown>): Promise<boolean> {
    setLoading(true)

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
      ?? document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]?.replace(/%3D/g, '=') ?? ''

    try {
      const response = await fetch(url, {
        method,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
          'X-Requested-With': 'XMLHttpRequest',
          'Idempotency-Key': crypto.randomUUID(),
        },
        body: JSON.stringify(body),
      })

      const payload = await response.json()

      if (!response.ok) {
        toast(String(payload.message ?? payload.detail ?? t('errorState')), 'error')
        return false
      }

      toast(t('saved'), 'success')
      window.location.reload()
      return true
    } finally {
      setLoading(false)
    }
  }

  async function deleteApi(url: string): Promise<boolean> {
    setLoading(true)

    const csrfToken = document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')?.content
      ?? document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1]?.replace(/%3D/g, '=') ?? ''

    try {
      const response = await fetch(url, {
        method: 'DELETE',
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'X-CSRF-TOKEN': csrfToken,
          'X-XSRF-TOKEN': decodeURIComponent(document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] ?? ''),
          'X-Requested-With': 'XMLHttpRequest',
          'Idempotency-Key': crypto.randomUUID(),
        },
      })

      if (!response.ok) {
        const payload = await response.json().catch(() => ({}))
        toast(String((payload as Record<string, unknown>).detail ?? (payload as Record<string, unknown>).message ?? t('errorState')), 'error')
        return false
      }

      toast(t('platformSectionDeleted'), 'success')
      window.location.reload()
      return true
    } finally {
      setLoading(false)
    }
  }

  /* ========== Tenants Section ========== */
  function TenantsSection() {
    const [showCreate, setShowCreate] = useState(false)
    const [editId, setEditId] = useState<string | null>(null)
    const tenants = rows as unknown as TenantRow[]

    return (
      <>
        {canManage && (
          <div className="mb-4 flex justify-end">
            <button
              type="button"
              onClick={() => { setShowCreate(!showCreate); setEditId(null) }}
              className="button-primary"
            >
              {t('platformSectionAddOrganizer')}
            </button>
          </div>
        )}

        {showCreate && canManage && <CreateOrganizerForm onCancel={() => setShowCreate(false)} />}
        {editId && canManage && (
          <EditTenantForm
            tenant={tenants.find((t) => t.id === editId)!}
            onCancel={() => setEditId(null)}
          />
        )}

        {tenants.length === 0 ? (
          <EmptyState
            title={t('platformSectionNoOrganizers')}
            detail={t('platformSectionNoOrganizersDetail')}
          />
        ) : (
          <div className="ta-card overflow-hidden p-0">
            <div className="ta-table-wrap">
              <table className="ta-table w-full">
                <thead>
                  <tr>
                    <th>{t('platformSectionOrganization')}</th>
                    <th>{t('platformSectionAdmin')}</th>
                    <th>{t('platformSectionEmail')}</th>
                    <th>{t('platformSectionPhone')}</th>
                    <th>{t('platformSectionStatus')}</th>
                    <th>{t('platformSectionCreated')}</th>
                    {canManage && <th>{t('actions')}</th>}
                  </tr>
                </thead>
                <tbody>
                  {tenants.map((tenant) => (
                    <tr key={tenant.id}>
                      <td className="font-medium text-[var(--ink)]">{tenant.name}</td>
                      <td className="text-[var(--ink)]">{tenant.admin?.name ?? '—'}</td>
                      <td className="text-sm text-[var(--muted)]">{tenant.admin?.email ?? '—'}</td>
                      <td className="text-sm text-[var(--muted)]">{tenant.admin?.phone ?? '—'}</td>
                      <td><StatusBadge status={tenant.status} /></td>
                      <td className="text-sm text-[var(--muted)]">
                        {tenant.created_at ? new Date(tenant.created_at).toLocaleDateString(locale === 'ar' ? 'ar-EG' : 'en-GB') : '—'}
                      </td>
                      {canManage && (
                        <td>
                          <div className="ta-table-actions">
                            <button
                              type="button"
                              onClick={() => { setEditId(tenant.id); setShowCreate(false) }}
                              className="ta-table-action"
                            >
                              {t('edit')}
                            </button>
                            <button
                              type="button"
                              onClick={() => {
                                const msg = t('platformSectionConfirmDelete', { name: tenant.name })
                                if (window.confirm(msg)) {
                                  void deleteApi(`/api/v1/platform/tenants/${tenant.id}`)
                                }
                              }}
                              className="ta-table-action !border-[var(--danger)]/30 !text-[var(--danger)] hover:!bg-[var(--danger-soft)]"
                            >
                              {t('delete')}
                            </button>
                          </div>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </>
    )
  }

  function CreateOrganizerForm({ onCancel }: { onCancel: () => void }) {
    const [form, setForm] = useState({
      name: '',
      email: '',
      organization_name: '',
      phone: '',
      password: '',
      password_confirmation: '',
    })

    return (
      <form
        className="ta-card mb-4"
        onSubmit={(event: FormEvent) => {
          event.preventDefault()
          void submitApi('/api/v1/platform/tenants', 'POST', form)
        }}
      >
        <h3 className="mb-4 text-base font-semibold text-[var(--ink)]">
          {t('platformSectionAddNewOrganizer')}
        </h3>
        <div className="grid gap-3 sm:grid-cols-2">
          <TextInput
            label={t('name')}
            name="name"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
          />
          <TextInput
            label={t('email')}
            name="email"
            type="email"
            value={form.email}
            onChange={(e) => setForm({ ...form, email: e.target.value })}
            required
          />
          <TextInput
            label={t('platformSectionOrganizationName')}
            name="organization_name"
            value={form.organization_name}
            onChange={(e) => setForm({ ...form, organization_name: e.target.value })}
            required
          />
          <TextInput
            label={t('phone')}
            name="phone"
            value={form.phone}
            onChange={(e) => setForm({ ...form, phone: e.target.value })}
          />
          <TextInput
            label={t('password')}
            name="password"
            type="password"
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            required
          />
          <TextInput
            label={t('platformSectionConfirmPassword')}
            name="password_confirmation"
            type="password"
            value={form.password_confirmation}
            onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
            required
          />
        </div>
        <div className="mt-5 flex gap-3">
          <SubmitButtonWithLoader
            label={t('platformSectionCreateOrganizer')}
            loading={loading}
          />
          <button type="button" onClick={onCancel} className="button-secondary">
            {t('cancel')}
          </button>
        </div>
      </form>
    )
  }

  function EditTenantForm({ tenant, onCancel }: { tenant: TenantRow; onCancel: () => void }) {
    const [form, setForm] = useState({
      name: tenant.name,
      status: tenant.status,
      default_locale: tenant.default_locale,
      timezone: tenant.timezone,
      reason: '',
    })

    return (
      <form
        className="ta-card mb-4"
        onSubmit={(event: FormEvent) => {
          event.preventDefault()
          void submitApi(`/api/v1/platform/tenants/${tenant.id}`, 'PATCH', form)
        }}
      >
        <h3 className="mb-4 text-base font-semibold text-[var(--ink)]">
          {t('platformSectionEditTenant', { name: tenant.name })}
        </h3>
        <div className="grid gap-3 sm:grid-cols-2">
          <TextInput
            label={t('platformSectionOrganizationName')}
            name="name"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
          />
          <SelectInput
            label={t('platformSectionStatus')}
            name="status"
            value={form.status}
            onChange={(e) => setForm({ ...form, status: e.target.value })}
            options={[
              { value: 'active', label: t('platformSectionStatusActive') },
              { value: 'suspended', label: t('platformSectionStatusSuspended') },
              { value: 'deactivated', label: t('platformSectionStatusDeactivated') },
            ]}
          />
          <SelectInput
            label={t('platformSectionDefaultLocale')}
            name="default_locale"
            value={form.default_locale}
            onChange={(e) => setForm({ ...form, default_locale: e.target.value })}
            options={[
              { value: 'en', label: 'English' },
              { value: 'ar', label: 'العربية' },
            ]}
          />
          <TextInput
            label={t('platformSectionTimezone')}
            name="timezone"
            value={form.timezone}
            onChange={(e) => setForm({ ...form, timezone: e.target.value })}
          />
          <div className="sm:col-span-2">
            <TextInput
              label={t('platformSectionReasonForChange')}
              name="reason"
              value={form.reason}
              onChange={(e) => setForm({ ...form, reason: e.target.value })}
              required
            />
          </div>
        </div>
        <div className="mt-5 flex gap-3">
          <SubmitButtonWithLoader
            label={t('saveChanges')}
            loading={loading}
          />
          <button type="button" onClick={onCancel} className="button-secondary">
            {t('cancel')}
          </button>
        </div>
      </form>
    )
  }

  /* ========== All Events Section ========== */
  function EventsSection() {
    const events = rows as unknown as EventRow[]

    return events.length === 0 ? (
      <EmptyState
        title={t('platformSectionNoEvents')}
        detail={t('platformSectionNoEventsDetail')}
      />
    ) : (
      <div className="ta-card overflow-hidden p-0">
        <div className="ta-table-wrap">
          <table className="ta-table w-full">
            <thead>
              <tr>
                <th>{t('platformSectionEvent')}</th>
                <th>{t('platformSectionOrganizer')}</th>
                <th>{t('platformSectionStatus')}</th>
                <th>{t('platformSectionType')}</th>
                <th>{t('platformSectionStartDate')}</th>
                <th>{t('actions')}</th>
              </tr>
            </thead>
            <tbody>
              {events.map((event) => (
                <tr key={event.id}>
                  <td className="font-medium text-[var(--ink)]">{locale === 'ar' ? event.name_ar || event.name : event.name}</td>
                  <td className="text-sm text-[var(--ink)]">{event.organizer}</td>
                  <td><StatusBadge status={event.status} /></td>
                  <td className="text-sm text-[var(--muted)]">{event.event_type?.replace(/_/g, ' ') ?? '—'}</td>
                  <td className="text-sm text-[var(--muted)]">
                    {event.start_at ? formatDateOnly(event.start_at, locale, event.timezone || undefined) : '—'}
                  </td>
                  <td>
                    <a
                      href={`/${locale}/tenant/events/${event.id}`}
                      className="ta-table-action"
                    >
                      {t('platformSectionManage')}
                    </a>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </div>
    )
  }

  /* ========== Platform Users Section ========== */
  function UsersSection() {
    const [showCreate, setShowCreate] = useState(false)
    const users = rows as unknown as PlatformUserRow[]

    return (
      <>
        {canManage && (
          <div className="mb-4 flex justify-end">
            <button type="button" onClick={() => setShowCreate(!showCreate)} className="button-primary">
              {t('platformSectionAddAdmin')}
            </button>
          </div>
        )}

        {showCreate && canManage && <CreateAdminForm onCancel={() => setShowCreate(false)} />}

        {users.length === 0 ? (
          <EmptyState
            title={t('platformSectionNoAdmins')}
            detail={t('platformSectionNoAdminsDetail')}
          />
        ) : (
          <div className="ta-card overflow-hidden p-0">
            <div className="ta-table-wrap">
              <table className="ta-table w-full">
                <thead>
                  <tr>
                    <th>{t('name')}</th>
                    <th>{t('email')}</th>
                    <th>{t('platformSectionRole')}</th>
                    <th>{t('platformSectionStatus')}</th>
                    <th>{t('platformSectionCreated')}</th>
                    {canManage && <th>{t('actions')}</th>}
                  </tr>
                </thead>
                <tbody>
                  {users.map((user) => (
                    <tr key={user.id}>
                      <td className="font-medium text-[var(--ink)]">{user.name}</td>
                      <td className="text-sm text-[var(--muted)]">{user.email}</td>
                      <td className="text-sm text-[var(--ink)]">
                        {user.roles.map((r) => r.name).join(', ') || '—'}
                      </td>
                      <td><StatusBadge status={user.status} /></td>
                      <td className="text-sm text-[var(--muted)]">
                        {user.created_at ? new Date(user.created_at).toLocaleDateString(locale === 'ar' ? 'ar-EG' : 'en-GB') : '—'}
                      </td>
                      {canManage && (
                        <td>
                          <div className="ta-table-actions">
                            <button
                              type="button"
                              onClick={() => {
                                const msg = t('platformSectionConfirmDelete', { name: user.name })
                                if (window.confirm(msg)) {
                                  void deleteApi(`/api/v1/platform/users/${user.id}`)
                                }
                              }}
                              className="ta-table-action !border-[var(--danger)]/30 !text-[var(--danger)] hover:!bg-[var(--danger-soft)]"
                            >
                              {t('delete')}
                            </button>
                          </div>
                        </td>
                      )}
                    </tr>
                  ))}
                </tbody>
              </table>
            </div>
          </div>
        )}
      </>
    )
  }

  function CreateAdminForm({ onCancel }: { onCancel: () => void }) {
    const [form, setForm] = useState({
      name: '',
      email: '',
      password: '',
      password_confirmation: '',
      role_id: platformRoles[0]?.id ?? '',
    })

    return (
      <form
        className="ta-card mb-4"
        onSubmit={(event: FormEvent) => {
          event.preventDefault()
          void submitApi('/api/v1/platform/users', 'POST', form)
        }}
      >
        <h3 className="mb-4 text-base font-semibold text-[var(--ink)]">
          {t('platformSectionAddNewAdmin')}
        </h3>
        <div className="grid gap-3 sm:grid-cols-2">
          <TextInput
            label={t('name')}
            name="name"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
          />
          <TextInput
            label={t('email')}
            name="email"
            type="email"
            value={form.email}
            onChange={(e) => setForm({ ...form, email: e.target.value })}
            required
          />
          <TextInput
            label={t('password')}
            name="password"
            type="password"
            value={form.password}
            onChange={(e) => setForm({ ...form, password: e.target.value })}
            required
          />
          <TextInput
            label={t('platformSectionConfirmPassword')}
            name="password_confirmation"
            type="password"
            value={form.password_confirmation}
            onChange={(e) => setForm({ ...form, password_confirmation: e.target.value })}
            required
          />
          <SelectInput
            label={t('platformSectionRole')}
            name="role_id"
            value={form.role_id}
            onChange={(e) => setForm({ ...form, role_id: e.target.value })}
            options={platformRoles.map((role) => ({ value: role.id, label: role.name }))}
          />
        </div>
        <div className="mt-5 flex gap-3">
          <SubmitButtonWithLoader
            label={t('platformSectionCreateAdmin')}
            loading={loading}
          />
          <button type="button" onClick={onCancel} className="button-secondary">
            {t('cancel')}
          </button>
        </div>
      </form>
    )
  }

  /* ========== Platform Roles Section ========== */
  function RolesSection() {
    const [showCreate, setShowCreate] = useState(false)
    const roles = rows as unknown as PlatformRoleRow[]

    return (
      <>
        {canManage && (
          <div className="mb-4 flex justify-end">
            <button type="button" onClick={() => setShowCreate(!showCreate)} className="button-primary">
              {t('platformSectionAddRole')}
            </button>
          </div>
        )}

        {showCreate && canManage && <CreateRoleForm onCancel={() => setShowCreate(false)} />}

        {roles.length === 0 ? (
          <EmptyState
            title={t('platformSectionNoRoles')}
            detail={t('platformSectionNoRolesDetail')}
          />
        ) : (
          <div className="space-y-3">
            {roles.map((role) => (
              <div key={role.id} className="ta-card">
                <div className="flex items-center justify-between">
                  <div>
                    <h4 className="font-semibold text-[var(--ink)]">{role.name}</h4>
                    {role.description && (
                      <p className="mt-1 text-sm text-[var(--muted)]">{role.description}</p>
                    )}
                    <div className="mt-2 flex flex-wrap gap-1">
                      {role.permissions.slice(0, 8).map((perm) => (
                        <span key={perm} className="inline-block rounded bg-[var(--brand-soft)] px-2 py-0.5 text-xs text-[var(--brand)]">
                          {perm.replace(/^platform\./, '').replaceAll('.', ' · ').replaceAll('_', ' ')}
                        </span>
                      ))}
                      {role.permissions.length > 8 && (
                        <span className="inline-block rounded bg-[var(--surface)] px-2 py-0.5 text-xs text-[var(--muted)]">
                          +{role.permissions.length - 8}
                        </span>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-2">
                    {role.is_system && (
                      <span className="rounded bg-[var(--surface)] px-2 py-0.5 text-xs text-[var(--muted)]">
                        {t('platformSectionSystem')}
                      </span>
                    )}
                    {canManage && !role.is_system && (
                      <button
                        type="button"
                        onClick={() => {
                          const msg = t('platformSectionConfirmDelete', { name: role.name })
                          if (window.confirm(msg)) {
                            void deleteApi(`/api/v1/platform/roles/${role.id}`)
                          }
                        }}
                        className="ta-table-action !border-[var(--danger)]/30 !text-[var(--danger)] hover:!bg-[var(--danger-soft)]"
                      >
                        {t('delete')}
                      </button>
                    )}
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}
      </>
    )
  }

  function CreateRoleForm({ onCancel }: { onCancel: () => void }) {
    const allPlatformPermissions = [
      'platform.user.view', 'platform.user.manage',
      'platform.role.view', 'platform.role.manage',
      'platform.tenant.view', 'platform.tenant.manage',
      'platform.access.recover',
      'platform.audit.view', 'platform.audit.export', 'platform.audit.verify',
      'operations.health.view',
      'platform.feature_flag.view', 'platform.feature_flag.manage',
      'platform.configuration.view',
      'platform.marketplace.view', 'platform.marketplace.disputes.manage',
      'platform.subscription.view', 'platform.subscription.manage',
    ]

    const [form, setForm] = useState({
      name: '',
      description: '',
      permissions: [] as string[],
    })

    const togglePermission = (key: string) => {
      setForm((prev) => ({
        ...prev,
        permissions: prev.permissions.includes(key)
          ? prev.permissions.filter((p) => p !== key)
          : [...prev.permissions, key],
      }))
    }

    return (
      <form
        className="ta-card mb-4"
        onSubmit={(event: FormEvent) => {
          event.preventDefault()
          void submitApi('/api/v1/platform/roles', 'POST', form)
        }}
      >
        <h3 className="mb-4 text-base font-semibold text-[var(--ink)]">
          {t('platformSectionAddNewRole')}
        </h3>
        <div className="grid gap-3 sm:grid-cols-2">
          <TextInput
            label={t('platformSectionRoleName')}
            name="name"
            value={form.name}
            onChange={(e) => setForm({ ...form, name: e.target.value })}
            required
          />
          <TextInput
            label={t('description')}
            name="description"
            value={form.description}
            onChange={(e) => setForm({ ...form, description: e.target.value })}
          />
        </div>
        <div className="mt-4">
          <label className="mb-2 block text-sm font-medium text-[var(--ink)]">
            {t('platformSectionPermissions')}
          </label>
          <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
            {allPlatformPermissions.map((perm) => (
              <label key={perm} className="flex items-center gap-2 rounded border border-[var(--border)] px-3 py-2 text-sm cursor-pointer hover:bg-[var(--surface)]">
                <input
                  type="checkbox"
                  checked={form.permissions.includes(perm)}
                  onChange={() => togglePermission(perm)}
                  className="accent-[var(--brand)]"
                />
                <span className="text-[var(--ink)]">
                  {perm.replace(/^platform\./, '').replaceAll('.', ' · ').replaceAll('_', ' ')}
                </span>
              </label>
            ))}
          </div>
        </div>
        <div className="mt-5 flex gap-3">
          <SubmitButtonWithLoader
            label={t('platformSectionCreateRole')}
            loading={loading}
          />
          <button type="button" onClick={onCancel} className="button-secondary">
            {t('cancel')}
          </button>
        </div>
      </form>
    )
  }

  /* ========== Feature Flags ========== */
  function CreateFlagForm() {
    const [form, setForm] = useState({
      key: '',
      name: '',
      description: 'Demo feature flag',
      owner: 'platform',
      value_type: 'boolean',
      default_value: true,
    })

    return (
      <form
        className="ta-card mb-4 grid gap-3 md:grid-cols-2"
        onSubmit={(event: FormEvent) => {
          event.preventDefault()
          void submitApi('/api/v1/platform/feature-flags', 'POST', form)
        }}
      >
        <TextInput label="Key" name="key" value={form.key} onChange={(e) => setForm({ ...form, key: e.target.value })} required />
        <TextInput label="Name" name="name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        <SubmitButtonWithLoader label={t('platformSectionAddFlag')} loading={loading} />
      </form>
    )
  }

  return (
    <DashboardLayout title={title}>
      <PageHeader
        title={title}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('navGroupPlatform'), href: '/platform/tenants' },
          { label: title },
        ]}
      />
      <PageContent>
        {section === 'configuration' && (
          <p className="mb-4 text-sm text-[var(--muted)]">
            {t('platformSectionConfigurationNote')}
          </p>
        )}

        {section === 'health' && health && (
          <>
            <div className="mb-6 grid gap-4 sm:grid-cols-2">
              <StatCard
                label={t('platformSectionStatus')}
                value={health.status}
                status={health.status}
              />
              <StatCard
                label={t('platformSectionCheckedAt')}
                value={formatCheckedAt(health.checked_at)}
              />
            </div>
            <DataTable
              rows={health.checks}
              columns={healthCheckColumns}
              getRowKey={(row) => row.category}
              title={t('platformSectionSystemChecks')}
            />
          </>
        )}

        {section === 'all-events' && <EventsSection />}
        {section === 'users' && <UsersSection />}
        {section === 'roles' && <RolesSection />}
        {section === 'tenants' && <TenantsSection />}

        {canManage && section === 'feature-flags' && <CreateFlagForm />}

        {section !== 'health' && section !== 'tenants' && section !== 'users' && section !== 'roles' && section !== 'all-events' && (
          rows.length === 0 ? (
            <EmptyState title={t('emptyState')} detail={t('platformSectionNoRecords')} />
          ) : (
            <DataTable rows={rows} columns={columns} getRowKey={(row) => String(row.id ?? row.key ?? JSON.stringify(row))} title={title} />
          )
        )}
      </PageContent>
    </DashboardLayout>
  )
}
