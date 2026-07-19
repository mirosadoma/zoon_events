import { FormEvent, useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import { Shield } from 'lucide-react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import { PageContent, PageHeader } from '@/components/layout'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import { localizedPath } from '@/lib/localePath'

type PrivilegeEffect = 'allow' | 'deny'

type Privilege = {
  id?: string
  key: string
  label: string
  label_ar: string | null
  effect: PrivilegeEffect
  target_type: string | null
  target_id: string | null
}

type PrivilegeCatalogItem = {
  key: string
  label: string
  label_ar: string | null
  target_type: string | null
  target_id: string | null
}

type Category = {
  id: string
  name: string
  name_ar: string | null
  slug: string
  color: string | null
  capacity: number | null
  sort_order: number
  privileges: Privilege[]
}

type Props = {
  event: {
    id: string
    name: { en: string; ar: string }
  }
  tenantId: string
  category: Category | null
  privilegeCatalog: PrivilegeCatalogItem[]
}

type PrivilegeRow = {
  key: string
  label: string
  label_ar: string
  target_type: string
  target_id: string
  enabled: boolean
  effect: PrivilegeEffect
}

type CategoryFormState = {
  name: string
  name_ar: string
  color: string
  capacity: string
  privileges: PrivilegeRow[]
}

function buildPrivilegeRows(
  catalog: PrivilegeCatalogItem[],
  assigned: Privilege[],
): PrivilegeRow[] {
  const assignedByKey = new Map(assigned.map((privilege) => [privilege.key, privilege]))
  const keys = new Set<string>()
  const rows: PrivilegeRow[] = []

  for (const item of catalog) {
    keys.add(item.key)
    const current = assignedByKey.get(item.key)
    rows.push({
      key: item.key,
      label: current?.label ?? item.label,
      label_ar: current?.label_ar ?? item.label_ar ?? '',
      target_type: current?.target_type ?? item.target_type ?? '',
      target_id: current?.target_id ?? item.target_id ?? '',
      enabled: current !== undefined,
      effect: current?.effect === 'deny' ? 'deny' : (current ? 'allow' : 'deny'),
    })
  }

  for (const privilege of assigned) {
    if (keys.has(privilege.key)) {
      continue
    }

    rows.push({
      key: privilege.key,
      label: privilege.label,
      label_ar: privilege.label_ar ?? '',
      target_type: privilege.target_type ?? '',
      target_id: privilege.target_id ?? '',
      enabled: true,
      effect: privilege.effect === 'deny' ? 'deny' : 'allow',
    })
  }

  return rows
}

function toForm(category: Category | null, catalog: PrivilegeCatalogItem[]): CategoryFormState {
  return {
    name: category?.name ?? '',
    name_ar: category?.name_ar ?? '',
    color: category?.color ?? '#3c50e0',
    capacity: category?.capacity != null ? String(category.capacity) : '',
    privileges: buildPrivilegeRows(catalog, category?.privileges ?? []),
  }
}

export default function CategoryForm({ event, tenantId, category, privilegeCatalog }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const isEdit = category !== null
  const listPath = localizedPath(locale, `/tenant/events/${event.id}/categories`)
  const eventName = locale === 'ar' ? event.name.ar || event.name.en : event.name.en
  const pageTitle = isEdit ? t('categoryEdit') : t('categoryNew')

  const [form, setForm] = useState<CategoryFormState>(() => toForm(category, privilegeCatalog))
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setForm(toForm(category, privilegeCatalog))
    setError(null)
  }, [category, privilegeCatalog])

  const enabledCount = form.privileges.filter((privilege) => privilege.enabled).length
  const previewName = locale === 'ar'
    ? (form.name_ar.trim() || form.name.trim() || t('categoryNameLabel'))
    : (form.name.trim() || form.name_ar.trim() || t('categoryNameLabel'))

  function updatePrivilege(key: string, patch: Partial<PrivilegeRow>) {
    setForm((current) => ({
      ...current,
      privileges: current.privileges.map((row) => (row.key === key ? { ...row, ...patch } : row)),
    }))
  }

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setError(null)

    const payload = {
      name: form.name.trim(),
      name_ar: form.name_ar.trim() || null,
      color: form.color || null,
      capacity: form.capacity.trim() === '' ? null : Number(form.capacity),
      privileges: form.privileges
        .filter((privilege) => privilege.enabled)
        .map((privilege) => ({
          key: privilege.key,
          label: privilege.label,
          label_ar: privilege.label_ar.trim() || null,
          effect: privilege.effect,
          target_type: privilege.target_type.trim() || null,
          target_id: privilege.target_id.trim() || null,
        })),
    }

      try {
        if (isEdit) {
          await apiFetch(`/api/v1/tenant/events/${event.id}/categories/${category.id}`, {
            method: 'PATCH',
            tenantId,
            idempotency: true,
            body: payload,
          })
          toast(t('categoryUpdated'), 'success')
        } else {
          await apiFetch(`/api/v1/tenant/events/${event.id}/categories`, {
            method: 'POST',
            tenantId,
            idempotency: true,
            body: payload,
          })
          toast(t('categoryCreated'), 'success')
        }

        router.visit(listPath)
      } catch (caught) {
        if (caught instanceof ApiFetchError && caught.status === 404) {
          toast(t('categoryNotFound'), 'error')
          router.visit(listPath)
        } else {
          setError(caught instanceof ApiFetchError ? caught.message : t('categoryCouldNotSave'))
        }
      } finally {
        setSubmitting(false)
      }
    }

  return (
    <DashboardLayout title={pageTitle}>
      <PageHeader
        title={pageTitle}
        description={eventName}
        actions={
          <LocalizedLink href={listPath} className="button-secondary">
            {t('back')}
          </LocalizedLink>
        }
      />

      <PageContent>
        <form onSubmit={handleSubmit} className="mx-auto space-y-5">
          {error && (
            <div className="rounded-[var(--radius-control)] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
              {error}
            </div>
          )}

          <section className="ta-card space-y-5">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h2 className="text-base font-semibold text-[var(--ink)]">
                  {t('categoryDetails')}
                </h2>
                <p className="mt-1 text-sm text-[var(--muted)]">
                  {t('categoryDetailsDescription')}
                </p>
              </div>
              <div className="flex items-center gap-2 rounded-[var(--radius-control)] border border-[var(--border)] bg-[var(--surface)] px-3 py-2">
                <span
                  className="h-3.5 w-3.5 shrink-0 rounded-full border border-[var(--border)]"
                  style={{ backgroundColor: form.color || 'var(--brand)' }}
                />
                <span className="max-w-[10rem] truncate text-sm font-medium text-[var(--ink)]">
                  {previewName}
                </span>
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <TextInput
                label={t('categoryNameEn')}
                value={form.name}
                onChange={(e) => setForm((current) => ({ ...current, name: e.target.value }))}
                required
              />
              <TextInput
                label={t('categoryNameAr')}
                value={form.name_ar}
                onChange={(e) => setForm((current) => ({ ...current, name_ar: e.target.value }))}
              />
              <div className="grid gap-2 text-sm">
                <span className="font-medium text-[var(--ink)]">
                  {t('categoryColor')}
                </span>
                <div className="flex items-center gap-3">
                  <input
                    type="color"
                    value={form.color}
                    onChange={(e) => setForm((current) => ({ ...current, color: e.target.value }))}
                    className="h-10 w-14 cursor-pointer rounded-[var(--radius-control)] border border-[var(--border)] bg-[var(--surface-elevated)] p-1"
                    aria-label={t('categoryColorLabel')}
                  />
                  <input
                    type="text"
                    value={form.color}
                    onChange={(e) => setForm((current) => ({ ...current, color: e.target.value }))}
                    className="control font-mono text-sm uppercase"
                    maxLength={7}
                    pattern="^#[0-9A-Fa-f]{6}$"
                  />
                </div>
              </div>
              <TextInput
                label={t('categoryCapacityOptional')}
                type="number"
                min={0}
                value={form.capacity}
                onChange={(e) => setForm((current) => ({ ...current, capacity: e.target.value }))}
                placeholder={t('categoryCapacityPlaceholder')}
              />
            </div>
          </section>

          <section className="ta-card space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="flex items-start gap-3">
                <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--brand-soft)] text-[var(--brand)]">
                  <Shield className="h-4 w-4" />
                </span>
                <div>
                  <h2 className="text-base font-semibold text-[var(--ink)]">
                    {t('categoryPrivileges')}
                  </h2>
                  <p className="mt-1 text-sm text-[var(--muted)]">
                    {t('categoryPrivilegesDescription')}
                  </p>
                </div>
              </div>
              <span className="rounded-full bg-[var(--surface)] px-3 py-1 text-xs font-medium text-[var(--muted)]">
                {t('categoryPrivilegesEnabled').replace(':count', String(enabledCount))}
              </span>
            </div>

            {form.privileges.length === 0 ? (
              <div className="rounded-[var(--radius-control)] border border-dashed border-[var(--border)] px-4 py-8 text-center text-sm text-[var(--muted)]">
                {t('categoryNoPrivileges')}
              </div>
            ) : (
              <ul className="space-y-2">
                {form.privileges.map((privilege) => {
                  const label = locale === 'ar'
                    ? privilege.label_ar || privilege.label
                    : privilege.label

                  return (
                    <li
                      key={privilege.key}
                      className={`rounded-[var(--radius-control)] border px-3 py-3 transition-colors sm:px-4 ${
                        privilege.enabled
                          ? 'border-[var(--border)] bg-[var(--surface-elevated)]'
                          : 'border-transparent bg-[var(--surface)] opacity-80'
                      }`}
                    >
                      <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <label className="flex min-w-0 cursor-pointer items-start gap-3">
                          <input
                            type="checkbox"
                            className="mt-1 h-4 w-4 shrink-0 accent-[var(--brand)]"
                            checked={privilege.enabled}
                            onChange={(e) => updatePrivilege(privilege.key, { enabled: e.target.checked })}
                          />
                          <span className="min-w-0">
                            <span className="block font-medium text-[var(--ink)]">{label}</span>
                            <span className="mt-0.5 block font-mono text-xs text-[var(--muted)]">
                              {privilege.key}
                              {privilege.target_type ? ` · ${privilege.target_type}` : ''}
                            </span>
                          </span>
                        </label>

                        <div
                          className={`inline-flex shrink-0 self-start rounded-[var(--radius-control)] border border-[var(--border)] p-0.5 sm:self-center ${
                            privilege.enabled ? '' : 'pointer-events-none opacity-40'
                          }`}
                          role="group"
                          aria-label={t('categoryPrivilegeEffect').replace(':name', label)}
                        >
                          <button
                            type="button"
                            disabled={!privilege.enabled}
                            onClick={() => updatePrivilege(privilege.key, { effect: 'allow', enabled: true })}
                            className={`rounded-[calc(var(--radius-control)-2px)] px-3 py-1.5 text-xs font-semibold transition-colors ${
                              privilege.effect === 'allow'
                                ? 'bg-emerald-600 text-white'
                                : 'text-[var(--muted)] hover:text-[var(--ink)]'
                            }`}
                          >
                            {t('categoryPrivilegeAllow')}
                          </button>
                          <button
                            type="button"
                            disabled={!privilege.enabled}
                            onClick={() => updatePrivilege(privilege.key, { effect: 'deny', enabled: true })}
                            className={`rounded-[calc(var(--radius-control)-2px)] px-3 py-1.5 text-xs font-semibold transition-colors ${
                              privilege.effect === 'deny'
                                ? 'bg-red-600 text-white'
                                : 'text-[var(--muted)] hover:text-[var(--ink)]'
                            }`}
                          >
                            {t('categoryPrivilegeDeny')}
                          </button>
                        </div>
                      </div>
                    </li>
                  )
                })}
              </ul>
            )}
          </section>

          <div className="flex flex-wrap items-center justify-end gap-2 border-t border-[var(--border)] pt-4">
            <LocalizedLink href={listPath} className="button-secondary">
              {t('cancel')}
            </LocalizedLink>
            <SubmitButtonWithLoader
              label={isEdit ? t('save') : t('create')}
              loading={submitting}
            />
          </div>
        </form>
      </PageContent>
    </DashboardLayout>
  )
}
