import { useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import { KeyRound, Pencil, Plus, Trash2 } from 'lucide-react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'

type Privilege = {
  id: string
  key: string
  label: string
  label_ar: string | null
  effect: 'allow' | 'deny'
  target_type: string | null
  target_id: string | null
  sort_order: number
  in_use: boolean
}

type Props = {
  tenantId: string
  privileges: Privilege[]
  canManage: boolean
}

export default function PrivilegeIndex({ tenantId, privileges: initialPrivileges, canManage }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [privileges, setPrivileges] = useState(initialPrivileges)
  const [deletingId, setDeletingId] = useState<string | null>(null)

  useEffect(() => {
    setPrivileges(initialPrivileges)
  }, [initialPrivileges])

  async function handleDelete(privilege: Privilege) {
    if (privilege.in_use || deletingId) {
      return
    }

    if (!confirm(t('privilegeDeleteConfirm').replace(':name', privilege.label))) {
      return
    }

    setDeletingId(privilege.id)

    try {
      await apiFetch(`/api/v1/tenant/privileges/${privilege.id}`, {
        method: 'DELETE',
        tenantId,
        idempotency: true,
      })
      toast(t('deleted'), 'success')
      setPrivileges((current) => current.filter((item) => item.id !== privilege.id))
      router.reload({ only: ['privileges'] })
    } catch (caught) {
      const message = caught instanceof ApiFetchError
        ? caught.message
        : t('privilegeCouldNotDelete')
      toast(message, 'error')
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <DashboardLayout title={t('privileges')}>
      <PageHeader
        title={t('privileges')}
        description={t('tenantPrivilegesDescription')}
        breadcrumbs={[{ label: t('privileges') }]}
        actions={canManage ? (
          <LocalizedLink
            href="/tenant/privileges/create"
            className="button-primary inline-flex items-center gap-2"
          >
            <Plus className="h-4 w-4" aria-hidden="true" />
            {t('privilegeAdd')}
          </LocalizedLink>
        ) : undefined}
      />
      <PageContent>
        {privileges.length === 0 ? (
          <EmptyState
            title={t('privilegeNoPrivileges')}
            detail={t('tenantPrivilegesEmptyDetail')}
          />
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {privileges.map((privilege) => {
              const label = locale === 'ar'
                ? (privilege.label_ar || privilege.label)
                : privilege.label

              return (
                <article
                  key={privilege.id}
                  className="state-panel flex flex-col gap-4 p-4"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                      <span className="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-[var(--brand-soft)] text-[var(--brand)]">
                        <KeyRound className="h-4 w-4" aria-hidden="true" />
                      </span>
                      <div>
                        <h2 className="text-base font-semibold text-[var(--ink)]">{label}</h2>
                        <p className="font-mono text-xs text-[var(--muted)]">{privilege.key}</p>
                      </div>
                    </div>
                    <span className={`rounded-full px-2.5 py-1 text-xs font-medium ${
                      privilege.effect === 'allow'
                        ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                        : 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                    }`}>
                      {privilege.effect === 'allow' ? t('categoryPrivilegeAllow') : t('categoryPrivilegeDeny')}
                    </span>
                  </div>

                  <div className="flex flex-wrap gap-2 text-xs text-[var(--muted)]">
                    {privilege.target_type ? (
                      <span className="rounded-[var(--radius-control)] border border-[var(--border)] px-2 py-1">
                        {t('privilegeTarget')}: {privilege.target_type}
                      </span>
                    ) : null}
                    {privilege.in_use ? (
                      <span className="rounded-[var(--radius-control)] border border-amber-200 bg-amber-50 px-2 py-1 text-amber-700 dark:border-amber-800 dark:bg-amber-900/20 dark:text-amber-300">
                        {t('privilegeInUse')}
                      </span>
                    ) : null}
                  </div>

                  {canManage ? (
                    <div className="mt-auto flex gap-2">
                      <LocalizedLink
                        href={`/tenant/privileges/${privilege.id}/edit`}
                        className="button-secondary inline-flex flex-1 items-center justify-center gap-2"
                      >
                        <Pencil className="h-4 w-4" aria-hidden="true" />
                        {t('edit')}
                      </LocalizedLink>
                      <button
                        type="button"
                        className="button-secondary inline-flex items-center justify-center gap-2 text-red-600 disabled:opacity-50"
                        disabled={privilege.in_use || deletingId === privilege.id}
                        onClick={() => handleDelete(privilege)}
                        title={privilege.in_use ? t('privilegeInUseHint') : undefined}
                      >
                        <Trash2 className="h-4 w-4" aria-hidden="true" />
                        {t('delete')}
                      </button>
                    </div>
                  ) : null}
                </article>
              )
            })}
          </div>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
