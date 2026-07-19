import { FormEvent, useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import { KeyRound } from 'lucide-react'
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
  id: string
  key: string
  label: string
  label_ar: string | null
  effect: PrivilegeEffect
  target_type: string | null
  target_id: string | null
  sort_order: number
  in_use?: boolean
}

type Props = {
  tenantId: string
  privilege: Privilege | null
}

type PrivilegeFormState = {
  key: string
  label: string
  label_ar: string
  effect: PrivilegeEffect
  target_type: string
  target_id: string
  sort_order: string
}

const TARGET_TYPES = ['gate', 'zone', 'parking', 'lounge', 'other'] as const

function toForm(privilege: Privilege | null): PrivilegeFormState {
  return {
    key: privilege?.key ?? '',
    label: privilege?.label ?? '',
    label_ar: privilege?.label_ar ?? '',
    effect: privilege?.effect === 'deny' ? 'deny' : 'allow',
    target_type: privilege?.target_type ?? '',
    target_id: privilege?.target_id ?? '',
    sort_order: String(privilege?.sort_order ?? 0),
  }
}

export default function PrivilegeForm({ tenantId, privilege }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const isEdit = privilege !== null
  const listPath = localizedPath(locale, '/tenant/privileges')
  const pageTitle = isEdit ? t('privilegeEdit') : t('privilegeNew')
  const [form, setForm] = useState<PrivilegeFormState>(() => toForm(privilege))
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    setForm(toForm(privilege))
    setError(null)
  }, [privilege])

  const previewLabel = locale === 'ar'
    ? (form.label_ar.trim() || form.label.trim() || t('privilegeLabelEn'))
    : (form.label.trim() || form.label_ar.trim() || t('privilegeLabelEn'))

  async function handleSubmit(e: FormEvent) {
    e.preventDefault()
    setSubmitting(true)
    setError(null)

    const payload = {
      key: form.key.trim(),
      label: form.label.trim(),
      label_ar: form.label_ar.trim() || null,
      effect: form.effect,
      target_type: form.target_type.trim() || null,
      target_id: form.target_id.trim() || null,
      sort_order: Number.parseInt(form.sort_order, 10) || 0,
    }

    try {
      if (isEdit) {
        await apiFetch(`/api/v1/tenant/privileges/${privilege.id}`, {
          method: 'PATCH',
          tenantId,
          idempotency: true,
          body: payload,
        })
        toast(t('privilegeUpdated'), 'success')
      } else {
        await apiFetch('/api/v1/tenant/privileges', {
          method: 'POST',
          tenantId,
          idempotency: true,
          body: payload,
        })
        toast(t('privilegeCreated'), 'success')
      }

      router.visit(listPath)
    } catch (caught) {
      setError(caught instanceof ApiFetchError ? caught.message : t('privilegeCouldNotSave'))
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <DashboardLayout title={pageTitle}>
      <PageHeader
        title={pageTitle}
        description={t('tenantPrivilegesDescription')}
        breadcrumbs={[
          { label: t('privileges'), href: '/tenant/privileges' },
          { label: pageTitle },
        ]}
        actions={(
          <LocalizedLink href="/tenant/privileges" className="button-secondary">
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
              <div className="flex items-start gap-3">
                <span className="mt-0.5 flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--brand-soft)] text-[var(--brand)]">
                  <KeyRound className="h-4 w-4" />
                </span>
                <div>
                  <h2 className="text-base font-semibold text-[var(--ink)]">{t('privilegeDetails')}</h2>
                  <p className="mt-1 text-sm text-[var(--muted)]">{t('privilegeDetailsDescription')}</p>
                </div>
              </div>
              <div className="rounded-[var(--radius-control)] border border-[var(--border)] bg-[var(--surface)] px-3 py-2 text-sm font-medium text-[var(--ink)]">
                {previewLabel}
              </div>
            </div>

            <div className="grid gap-4 sm:grid-cols-2">
              <TextInput
                label={t('privilegeKey')}
                value={form.key}
                onChange={(e) => setForm((current) => ({ ...current, key: e.target.value }))}
                required
                disabled={isEdit}
                hint={t('privilegeKeyHint')}
              />
              <div className="grid gap-2 text-sm">
                <span className="font-medium text-[var(--ink)]">{t('privilegeEffect')}</span>
                <select
                  className="control"
                  value={form.effect}
                  onChange={(e) => setForm((current) => ({
                    ...current,
                    effect: e.target.value === 'deny' ? 'deny' : 'allow',
                  }))}
                >
                  <option value="allow">{t('categoryPrivilegeAllow')}</option>
                  <option value="deny">{t('categoryPrivilegeDeny')}</option>
                </select>
              </div>
              <TextInput
                label={t('privilegeLabelEn')}
                value={form.label}
                onChange={(e) => setForm((current) => ({ ...current, label: e.target.value }))}
                required
              />
              <TextInput
                label={t('privilegeLabelAr')}
                value={form.label_ar}
                onChange={(e) => setForm((current) => ({ ...current, label_ar: e.target.value }))}
              />
              <div className="grid gap-2 text-sm">
                <span className="font-medium text-[var(--ink)]">{t('privilegeTargetType')}</span>
                <select
                  className="control"
                  value={form.target_type}
                  onChange={(e) => setForm((current) => ({ ...current, target_type: e.target.value }))}
                >
                  <option value="">{t('privilegeTargetNone')}</option>
                  {TARGET_TYPES.map((type) => (
                    <option key={type} value={type}>{type}</option>
                  ))}
                </select>
              </div>
              <TextInput
                label={t('privilegeTargetId')}
                value={form.target_id}
                onChange={(e) => setForm((current) => ({ ...current, target_id: e.target.value }))}
              />
              <TextInput
                label={t('privilegeSortOrder')}
                type="number"
                min={0}
                value={form.sort_order}
                onChange={(e) => setForm((current) => ({ ...current, sort_order: e.target.value }))}
              />
            </div>
          </section>

          <div className="flex justify-end gap-3">
            <LocalizedLink href="/tenant/privileges" className="button-secondary">
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
