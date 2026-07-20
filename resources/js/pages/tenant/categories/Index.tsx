import { useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import { Lock, Pencil, Plus, Trash2 } from 'lucide-react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'

type Privilege = {
  id?: string
  key: string
  label: string
  label_ar: string | null
  effect: 'allow' | 'deny'
  target_type: string | null
  target_id: string | null
}

type Category = {
  id: string
  name: string
  name_ar: string | null
  slug: string
  color: string | null
  locked: boolean
  privileges: Privilege[]
}

type Props = {
  tenantId: string
  categories: Category[]
  canManage: boolean
}

export default function CategoryIndex({ tenantId, categories: initialCategories, canManage }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [categories, setCategories] = useState(initialCategories)
  const [deletingId, setDeletingId] = useState<string | null>(null)

  useEffect(() => {
    setCategories(initialCategories)
  }, [initialCategories])

  async function handleDelete(category: Category) {
    if (category.locked || deletingId) {
      return
    }

    if (!confirm(t('categoryDeleteConfirm').replace(':name', category.name))) {
      return
    }

    setDeletingId(category.id)

    try {
      await apiFetch(`/api/v1/tenant/category-templates/${category.id}`, {
        method: 'DELETE',
        tenantId,
        idempotency: true,
      })
      toast(t('deleted'), 'success')
      setCategories((current) => current.filter((item) => item.id !== category.id))
      router.reload({ only: ['categories'] })
    } catch (caught) {
      const message = caught instanceof ApiFetchError
        ? caught.message
        : t('categoryCouldNotDelete')
      toast(message, 'error')
    } finally {
      setDeletingId(null)
    }
  }

  return (
    <DashboardLayout title={t('categories')}>
      <PageHeader
        title={t('categories')}
        description={t('tenantCategoriesDescription')}
        breadcrumbs={[{ label: t('categories') }]}
        actions={canManage ? (
          <LocalizedLink
            href="/tenant/categories/create"
            className="button-primary inline-flex items-center gap-2"
          >
            <Plus className="h-4 w-4" aria-hidden="true" />
            {t('categoryAdd')}
          </LocalizedLink>
        ) : undefined}
      />
      <PageContent>
        {categories.length === 0 ? (
          <EmptyState
            title={t('categoryNoCategories')}
            detail={t('tenantCategoriesEmptyDetail')}
          />
        ) : (
          <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
            {categories.map((category) => {
              const name = locale === 'ar'
                ? (category.name_ar || category.name)
                : category.name

              return (
                <article
                  key={category.id}
                  className="state-panel flex flex-col gap-4 p-4"
                >
                  <div className="flex items-start justify-between gap-3">
                    <div className="flex items-center gap-3">
                      <span
                        className="h-4 w-4 rounded-full border border-black/10"
                        style={{ backgroundColor: category.color || '#94a3b8' }}
                        aria-hidden="true"
                      />
                      <div>
                        <h2 className="text-base font-semibold text-[var(--ink)]">{name}</h2>
                        <p className="text-xs text-[var(--muted)]">{category.slug}</p>
                      </div>
                    </div>
                    {category.locked ? (
                      <span className="inline-flex items-center gap-1 text-xs text-amber-700 dark:text-amber-300">
                        <Lock className="h-3.5 w-3.5" aria-hidden="true" />
                        {t('categoryLocked')}
                      </span>
                    ) : null}
                  </div>

                  <p className="text-sm text-[var(--muted)]">
                    {t('categoryPrivilegesEnabled').replace(':count', String(category.privileges.length))}
                  </p>

                  {canManage ? (
                    <div className="mt-auto flex gap-2">
                      {category.locked ? null : (
                        <LocalizedLink
                          href={`/tenant/categories/${category.id}/edit`}
                          className="button-secondary inline-flex flex-1 items-center justify-center gap-2"
                        >
                          <Pencil className="h-4 w-4" aria-hidden="true" />
                          {t('edit')}
                        </LocalizedLink>
                      )}
                      <button
                        type="button"
                        className="button-secondary inline-flex items-center justify-center gap-2 text-red-600 disabled:opacity-50"
                        disabled={category.locked || deletingId === category.id}
                        onClick={() => handleDelete(category)}
                        title={category.locked ? t('categoryLockedHint') : undefined}
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
