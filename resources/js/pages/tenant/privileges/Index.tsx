import { useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import { KeyRound, Plus } from 'lucide-react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import SideDetailPane, {
  SideDetailActions,
  SideDetailInfoGrid,
  sideDetailActionClassName,
} from '@/components/layout/SideDetailPane'
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
  const { locale, t, localizedPath } = useLocale()
  const { toast } = useToast()
  const [privileges, setPrivileges] = useState(initialPrivileges)
  const [selectedId, setSelectedId] = useState<string | null>(null)
  const [deletingId, setDeletingId] = useState<string | null>(null)

  useEffect(() => {
    setPrivileges(initialPrivileges)
  }, [initialPrivileges])

  const selected = privileges.find((priv) => priv.id === selectedId) ?? null
  const notAvailable = t('notAvailable')

  function closePane() {
    setSelectedId(null)
  }

  function goToEdit() {
    if (!selectedId) return
    router.visit(localizedPath(`/tenant/privileges/${selectedId}/edit`))
  }

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
                  className="state-panel flex flex-col gap-4 p-4 cursor-pointer transition hover:border-[var(--brand)]/30"
                  onClick={() => setSelectedId(privilege.id)}
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
                </article>
              )
            })}
          </div>
        )}
      </PageContent>

      <SideDetailPane
        open={selected !== null}
        title={selected ? (locale === 'ar' ? (selected.label_ar || selected.label) : selected.label) : ''}
        subtitle={selected?.key || null}
        onClose={closePane}
        onEdit={canManage ? goToEdit : null}
        onDelete={!selected?.in_use && canManage ? () => handleDelete(selected) : null}
        footer={selected && canManage ? (
          <SideDetailActions>
            <button type="button" className={sideDetailActionClassName('primary')} onClick={goToEdit}>
              {t('edit')}
            </button>
          </SideDetailActions>
        ) : null}
      >
        {selected ? (
          <SideDetailInfoGrid
            items={[
              {
                label: t('privilegeLabel'),
                value: locale === 'ar' ? (selected.label_ar || selected.label) : selected.label,
              },
              {
                label: t('privilegeKey'),
                value: <code className="font-mono text-sm">{selected.key}</code>,
              },
              {
                label: t('privilegeEffect'),
                value: selected.effect === 'allow' ? t('categoryPrivilegeAllow') : t('categoryPrivilegeDeny'),
              },
              {
                label: t('privilegeTarget'),
                value: selected.target_type || notAvailable,
              },
              {
                label: t('privilegeInUse'),
                value: selected.in_use ? t('yes') : t('no'),
              },
              {
                label: t('privilegeSortOrder'),
                value: String(selected.sort_order),
              },
            ]}
          />
        ) : null}
      </SideDetailPane>
    </DashboardLayout>
  )
}
