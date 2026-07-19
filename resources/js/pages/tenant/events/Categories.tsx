import { useEffect, useState } from 'react'
import { router } from '@inertiajs/react'
import { Plus, Pencil, Trash2 } from 'lucide-react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import DashboardLayout from '@/layouts/DashboardLayout'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import { localizedPath } from '@/lib/localePath'

type Privilege = {
  id?: string
  key: string
  label: string
  label_ar: string
  effect: 'allow' | 'deny'
  target_type: string
  target_id: string
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
  categories: Category[]
  canManage: boolean
}

export default function Categories({ event, tenantId, categories: initialCategories, canManage }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const eventName = locale === 'ar' ? event.name.ar || event.name.en : event.name.en
  const [categories, setCategories] = useState(initialCategories)
  const [deletingId, setDeletingId] = useState<string | null>(null)

  useEffect(() => {
    setCategories(initialCategories)
  }, [initialCategories])

  function refreshCategories() {
    router.reload({ only: ['categories'] })
  }

  async function handleDelete(category: Category) {
    const categoryId = String(category.id)
    if (deletingId) {
      return
    }

    if (!confirm(t('categoryDeleteConfirm').replace(':name', category.name))) {
      return
    }

    setDeletingId(categoryId)

    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/categories/${categoryId}`, {
        method: 'DELETE',
        tenantId,
        idempotency: true,
      })
      toast(t('deleted'), 'success')
      setCategories((current) => current.filter((item) => String(item.id) !== categoryId))
      refreshCategories()
    } catch (caught) {
      if (caught instanceof ApiFetchError && caught.status === 404) {
        setCategories((current) => current.filter((item) => String(item.id) !== categoryId))
        refreshCategories()
        toast(t('categoryAlreadyDeleted'), 'error')
      } else {
        toast(caught instanceof ApiFetchError ? caught.message : t('categoryCouldNotDelete'), 'error')
      }
    } finally {
      setDeletingId(null)
    }
  }

  async function applyTemplates() {
    if (!confirm(t('categoryApplyTemplatesConfirm'))) {
      return
    }

    try {
      const created = await apiFetch<Category[]>(`/api/v1/tenant/events/${event.id}/categories/apply-templates`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: {},
      })
      const count = Array.isArray(created) ? created.length : 0
      if (count === 0) {
        toast(t('categoryAllTemplatesApplied'), 'success')
      } else {
        toast(t('categoryApplied').replace(':count', String(count)), 'success')
      }
      refreshCategories()
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('categoryCouldNotApply'), 'error')
    }
  }

  return (
    <DashboardLayout title={t('categories')}>
      <PageHeader
        title={t('categories')}
        description={eventName}
        actions={
          <div className="flex flex-wrap gap-2">
            <LocalizedLink href={localizedPath(locale, `/tenant/events/${event.id}`)} className="button-secondary">
              {t('back')}
            </LocalizedLink>
            {canManage && (
              <>
                <button type="button" className="button-secondary" onClick={() => void applyTemplates()}>
                  {t('categoryApplyTemplates')}
                </button>
                <LocalizedLink
                  href={localizedPath(locale, `/tenant/events/${event.id}/categories/create`)}
                  className="button-primary"
                >
                  <Plus className="me-1 h-4 w-4" />
                  {t('categoryAdd')}
                </LocalizedLink>
              </>
            )}
          </div>
        }
      />

      <PageContent>
        {categories.length === 0 ? (
          <EmptyState
            title={t('categoryNoCategories')}
            detail={t('categoryNoCategoriesDetail')}
          />
        ) : (
          <div className="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
            {categories.map((category) => (
              <article key={category.id} className="ta-card flex flex-col gap-4">
                <div className="flex items-start justify-between gap-3">
                  <div className="flex items-center gap-3">
                    <span
                      className="h-4 w-4 shrink-0 rounded-full border border-[var(--border)]"
                      style={{ backgroundColor: category.color ?? 'var(--brand)' }}
                    />
                    <div>
                      <h3 className="font-semibold text-[var(--ink)]">
                        {locale === 'ar' ? category.name_ar || category.name : category.name}
                      </h3>
                      <p className="text-xs text-[var(--muted)]">
                        {category.capacity == null
                          ? t('categoryNoCapacityLimit')
                          : t('categoryCapacity').replace(':count', String(category.capacity))}
                      </p>
                    </div>
                  </div>
                  {canManage && (
                    <div className="flex gap-1">
                      <LocalizedLink
                        href={localizedPath(locale, `/tenant/events/${event.id}/categories/${category.id}/edit`)}
                        className="button-secondary p-2"
                        aria-label={t('edit')}
                      >
                        <Pencil className="h-3.5 w-3.5" />
                      </LocalizedLink>
                      <button
                        type="button"
                        className="button-danger p-2"
                        disabled={deletingId === String(category.id)}
                        onClick={() => void handleDelete(category)}
                      >
                        <Trash2 className="h-3.5 w-3.5" />
                      </button>
                    </div>
                  )}
                </div>

                {category.privileges.length > 0 && (
                  <ul className="space-y-1.5 border-t border-[var(--border)] pt-3">
                    {category.privileges.map((privilege) => (
                      <li key={privilege.id ?? privilege.key} className="flex items-center justify-between text-sm">
                        <span className="text-[var(--ink)]">
                          {locale === 'ar' ? privilege.label_ar || privilege.label : privilege.label}
                        </span>
                        <span className={`rounded px-1.5 py-0.5 text-xs font-medium ${
                          privilege.effect === 'allow'
                            ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300'
                            : 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300'
                        }`}>
                          {privilege.effect}
                        </span>
                      </li>
                    ))}
                  </ul>
                )}
              </article>
            ))}
          </div>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
