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

type SectionProps = {
  section: string
  rows: Record<string, unknown>[]
  canManage: boolean
  health?: HealthReport | null
  users: Array<{ id: string; name: string; email: string }>
}

const TITLES: Record<string, { en: string; ar: string }> = {
  tenants: { en: 'Platform tenants', ar: 'مستأجرو المنصة' },
  users: { en: 'Platform users', ar: 'مستخدمو المنصة' },
  roles: { en: 'Platform roles', ar: 'أدوار المنصة' },
  audit: { en: 'Platform audit', ar: 'تدقيق المنصة' },
  health: { en: 'Platform health', ar: 'صحة المنصة' },
  'feature-flags': { en: 'Feature flags', ar: 'أعلام الميزات' },
  configuration: { en: 'Configuration schemas', ar: 'مخططات التكوين' },
}

export default function PlatformSection({ section, rows, canManage, health, users }: SectionProps) {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const { toast } = useToast()
  const [loading, setLoading] = useState(false)
  const title = TITLES[section]?.[locale] ?? section

  const healthCheckColumns = useMemo(
    () => [
      {
        key: 'category',
        header: locale === 'ar' ? 'الفئة' : 'Category',
        render: (row: HealthCheck) => row.category.replace(/_/g, ' '),
      },
      {
        key: 'status',
        header: locale === 'ar' ? 'الحالة' : 'Status',
        render: (row: HealthCheck) => <StatusBadge status={row.status} />,
      },
      {
        key: 'duration_ms',
        header: locale === 'ar' ? 'المدة (مللي ثانية)' : 'Duration (ms)',
        render: (row: HealthCheck) => String(row.duration_ms),
      },
      {
        key: 'reason_code',
        header: locale === 'ar' ? 'رمز السبب' : 'Reason code',
        render: (row: HealthCheck) => row.reason_code ?? '—',
      },
    ],
    [locale],
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
    if (rows.length === 0) {
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
  }, [rows])

  async function submitApi(url: string, method: string, body: Record<string, unknown>) {
    setLoading(true)

    try {
      const response = await fetch(url, {
        method,
        credentials: 'include',
        headers: {
          Accept: 'application/json',
          'Content-Type': 'application/json',
          'Idempotency-Key': crypto.randomUUID(),
        },
        body: JSON.stringify(body),
      })

      const payload = await response.json()

      if (!response.ok) {
        toast(String(payload.message ?? payload.detail ?? messages.errorState), 'error')
        return
      }

      toast(locale === 'ar' ? 'تم الحفظ.' : 'Saved successfully.', 'success')
      window.location.reload()
    } finally {
      setLoading(false)
    }
  }

  function CreateTenantForm() {
    const [form, setForm] = useState({
      name: '',
      slug: '',
      default_locale: 'en',
      timezone: 'Africa/Cairo',
      data_residency_region: 'eg',
      initial_admin_user_id: users[0]?.id ?? '',
      reason: 'Demo tenant provisioning',
    })

    return (
      <form
        className="ta-card mb-4 grid gap-3 md:grid-cols-2"
        onSubmit={(event: FormEvent) => {
          event.preventDefault()
          void submitApi('/api/v1/platform/tenants', 'POST', form)
        }}
      >
        <TextInput label="Name" name="name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        <TextInput label="Slug" name="slug" value={form.slug} onChange={(e) => setForm({ ...form, slug: e.target.value })} required />
        <SelectInput
          label="Admin user"
          name="initial_admin_user_id"
          value={form.initial_admin_user_id}
          onChange={(e) => setForm({ ...form, initial_admin_user_id: e.target.value })}
          options={users.map((user) => ({ value: user.id, label: `${user.name} (${user.email})` }))}
        />
        <SubmitButtonWithLoader label={locale === 'ar' ? 'إضافة مستأجر' : 'Add tenant'} loading={loading} />
      </form>
    )
  }

  function CreateUserForm() {
    const [form, setForm] = useState({
      name: '',
      email: '',
      password: 'ChangeMeNow123!',
      preferred_locale: 'en',
      reason: 'Demo user provisioning',
    })

    return (
      <form
        className="ta-card mb-4 grid gap-3 md:grid-cols-2"
        onSubmit={(event: FormEvent) => {
          event.preventDefault()
          void submitApi('/api/v1/platform/users', 'POST', form)
        }}
      >
        <TextInput label="Name" name="name" value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
        <TextInput label="Email" name="email" type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
        <TextInput label="Password" name="password" type="password" value={form.password} onChange={(e) => setForm({ ...form, password: e.target.value })} required />
        <SubmitButtonWithLoader label={locale === 'ar' ? 'إضافة مستخدم' : 'Add user'} loading={loading} />
      </form>
    )
  }

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
        <SubmitButtonWithLoader label={locale === 'ar' ? 'إضافة علم' : 'Add flag'} loading={loading} />
      </form>
    )
  }

  return (
    <DashboardLayout title={title}>
      <PageHeader
        title={title}
        breadcrumbs={[
          { label: messages.overview, href: '/dashboard' },
          { label: messages.navGroupPlatform, href: '/platform/tenants' },
          { label: title },
        ]}
      />
      <PageContent>
        {section === 'configuration' && (
          <p className="mb-4 text-sm text-[var(--muted)]">
            {locale === 'ar'
              ? 'مخططات التحقق من إعدادات المنصة (العلامة التجارية، النطاقات، إقامة البيانات، الاحتفاظ). لا تُعدّل القيم هنا — هذا مرجع للحقول المسموح بها عند ضبط مستأجر أو موقع.'
              : 'Validation schemas for platform settings (branding, domains, data residency, retention). This page documents allowed fields — it does not edit live values.'}
          </p>
        )}

        {section === 'health' && health && (
          <>
            <div className="mb-6 grid gap-4 sm:grid-cols-2">
              <StatCard
                label={locale === 'ar' ? 'الحالة' : 'Status'}
                value={health.status}
                status={health.status}
              />
              <StatCard
                label={locale === 'ar' ? 'آخر فحص' : 'Checked at'}
                value={formatCheckedAt(health.checked_at)}
              />
            </div>
            <DataTable
              rows={health.checks}
              columns={healthCheckColumns}
              getRowKey={(row) => row.category}
              title={locale === 'ar' ? 'فحوصات النظام' : 'System checks'}
            />
          </>
        )}

        {canManage && section === 'tenants' && <CreateTenantForm />}
        {canManage && section === 'users' && <CreateUserForm />}
        {canManage && section === 'feature-flags' && <CreateFlagForm />}

        {section !== 'health' && (
          rows.length === 0 ? (
            <EmptyState title={messages.emptyState} detail={locale === 'ar' ? 'لا توجد سجلات في هذا القسم.' : 'No records in this section yet.'} />
          ) : (
            <DataTable rows={rows} columns={columns} getRowKey={(row) => String(row.id ?? row.key ?? JSON.stringify(row))} title={title} />
          )
        )}

        {section === 'tenants' && rows.length > 0 && canManage && (
          <p className="mt-3 text-sm text-[var(--muted)]">
            {locale === 'ar' ? 'لتحديث حالة مستأجر، استخدم API PATCH /api/v1/platform/tenants/{id}' : 'To update tenant status use API PATCH /api/v1/platform/tenants/{id}'}
          </p>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
