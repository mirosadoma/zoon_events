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

type AssignedPrivilege = {
  id?: string
  privilege_id: string
  key: string
  label: string
  label_ar: string | null
  effect: PrivilegeEffect
  target_type: string | null
  target_id: string | null
}

type PrivilegeCatalogItem = {
  id: string
  key: string
  label: string
  label_ar: string | null
  effect: PrivilegeEffect
  target_type: string | null
  target_id: string | null
}

type Category = {
  id: string
  name: string
  name_ar: string | null
  slug: string
  color: string | null
  locked?: boolean
  privileges: AssignedPrivilege[]
}

type Props = {
  tenantId: string
  category: Category | null
  privilegeCatalog: PrivilegeCatalogItem[]
}

type PrivilegeRow = {
  privilege_id: string
  key: string
  label: string
  label_ar: string
  enabled: boolean
  effect: PrivilegeEffect
}

type CategoryFormState = {
  name: string
  name_ar: string
  color: string
  privileges: PrivilegeRow[]
}

function buildPrivilegeRows(catalog: PrivilegeCatalogItem[], assigned: AssignedPrivilege[]): PrivilegeRow[] {
  const assignedById = new Map(assigned.map((privilege) => [privilege.privilege_id, privilege]))

  return catalog.map((item) => {
    const current = assignedById.get(item.id)

    return {
      privilege_id: item.id,
      key: item.key,
      label: item.label,
      label_ar: item.label_ar ?? '',
      enabled: current !== undefined,
      effect: current?.effect === 'deny'
        ? 'deny'
        : current
          ? 'allow'
          : (item.effect === 'deny' ? 'deny' : 'allow'),
    }
  })
}

function toForm(category: Category | null, catalog: PrivilegeCatalogItem[]): CategoryFormState {
  return {
    name: category?.name ?? '',
    name_ar: category?.name_ar ?? '',
    color: category?.color ?? '#3c50e0',
    privileges: buildPrivilegeRows(catalog, category?.privileges ?? []),
  }
}

export default function CategoryForm({ tenantId, category, privilegeCatalog }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const isEdit = category !== null
  const listPath = localizedPath(locale, '/tenant/categories')
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

  function updatePrivilege(privilegeId: string, patch: Partial<PrivilegeRow>) {
    setForm((current) => ({
      ...current,
      privileges: current.privileges.map((row) => (
        row.privilege_id === privilegeId ? { ...row, ...patch } : row
      )),
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
      privileges: form.privileges
        .filter((privilege) => privilege.enabled)
        .map((privilege) => ({
          privilege_id: Number(privilege.privilege_id),
          effect: privilege.effect,
        })),
    }

    try {
      if (isEdit) {
        await apiFetch(`/api/v1/tenant/category-templates/${category.id}`, {
          method: 'PATCH',
          tenantId,
          idempotency: true,
          body: payload,
        })
        toast(t('categoryUpdated'), 'success')
      } else {
        await apiFetch('/api/v1/tenant/category-templates', {
          method: 'POST',
          tenantId,
          idempotency: true,
          body: payload,
        })
        toast(t('categoryCreated'), 'success')
      }

      router.visit(listPath)
    } catch (caught) {
      setError(caught instanceof ApiFetchError ? caught.message : t('categoryCouldNotSave'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <DashboardLayout title={pageTitle}>
      <PageHeader
        title={pageTitle}
        description={t('tenantCategoriesDescription')}
        breadcrumbs={[
          { label: t('categories'), href: '/tenant/categories' },
          { label: pageTitle },
        ]}
        actions={(
          <LocalizedLink href="/tenant/categories" className="button-secondary">
            {t('back')}
          </LocalizedLink>
        )}
      />
      <PageContent>
        <form onSubmit={handleSubmit} className="mx-auto space-y-5">
          {error ? (
            <div className="rounded-[var(--radius-control)] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700 dark:border-red-800 dark:bg-red-900/20 dark:text-red-300">
              {error}
            </div>
          ) : null}

          <section className="ta-card space-y-5">
            <div className="flex items-start justify-between gap-4">
              <div>
                <h2 className="text-base font-semibold text-[var(--ink)]">{t('categoryDetails')}</h2>
                <p className="mt-1 text-sm text-[var(--muted)]">{t('tenantCategoryDetailsDescription')}</p>
              </div>
              <div className="flex items-center gap-2 rounded-[var(--radius-control)] border border-[var(--border)] bg-[var(--surface)] px-3 py-2">
                <span
                  className="h-3.5 w-3.5 shrink-0 rounded-full border border-[var(--border)]"
                  style={{ backgroundColor: form.color || 'var(--brand)' }}
                />
                <span className="max-w-[10rem] truncate text-sm font-medium text-[var(--ink)]">{previewName}</span>
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
              <div className="grid gap-2 text-sm sm:col-span-2">
                <span className="font-medium text-[var(--ink)]">{t('categoryColor')}</span>
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
            </div>
          </section>

          <section className="ta-card space-y-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="flex items-start gap-3">
                <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--brand-soft)] text-[var(--brand)]">
                  <Shield className="h-4 w-4" />
                </span>
                <div>
                  <h2 className="text-base font-semibold text-[var(--ink)]">{t('categoryPrivileges')}</h2>
                  <p className="mt-1 text-sm text-[var(--muted)]">{t('categoryPrivilegesDescription')}</p>
                </div>
              </div>
              <span className="rounded-full bg-[var(--surface)] px-3 py-1 text-xs font-medium text-[var(--muted)]">
                {t('categoryPrivilegesEnabled').replace(':count', String(enabledCount))}
              </span>
            </div>

            {form.privileges.length === 0 ? (
              <div className="space-y-3 rounded-[var(--radius-control)] border border-dashed border-[var(--border)] px-4 py-8 text-center">
                <p className="text-sm text-[var(--muted)]">{t('categoryNoPrivileges')}</p>
                <LocalizedLink href="/tenant/privileges/create" className="button-secondary inline-flex">
                  {t('privilegeAdd')}
                </LocalizedLink>
              </div>
            ) : (
              <ul className="space-y-2">
                {form.privileges.map((privilege) => {
                  const label = locale === 'ar'
                    ? (privilege.label_ar || privilege.label)
                    : privilege.label

                  return (
                    <li
                      key={privilege.privilege_id}
                      className="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-control)] border border-[var(--border)] px-3 py-2"
                    >
                      <label className="flex min-w-0 items-center gap-2 text-sm text-[var(--ink)]">
                        <input
                          type="checkbox"
                          checked={privilege.enabled}
                          onChange={(e) => updatePrivilege(privilege.privilege_id, { enabled: e.target.checked })}
                        />
                        <span className="truncate">{label}</span>
                        <span className="font-mono text-xs text-[var(--muted)]">{privilege.key}</span>
                      </label>
                      {privilege.enabled ? (
                        <select
                          className="control w-auto"
                          value={privilege.effect}
                          aria-label={t('categoryPrivilegeEffect').replace(':name', label)}
                          onChange={(e) => updatePrivilege(privilege.privilege_id, {
                            effect: e.target.value === 'deny' ? 'deny' : 'allow',
                          })}
                        >
                          <option value="allow">{t('categoryPrivilegeAllow')}</option>
                          <option value="deny">{t('categoryPrivilegeDeny')}</option>
                        </select>
                      ) : null}
                    </li>
                  )
                })}
              </ul>
            )}
          </section>

          <div className="flex justify-end gap-3">
            <LocalizedLink href="/tenant/categories" className="button-secondary">
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
