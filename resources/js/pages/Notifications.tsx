import { Bell, BellOff, Check, CheckCheck, ExternalLink } from 'lucide-react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { auditActionLabel, auditTargetTypeLabel } from '@/lib/permissionCatalog'
import { apiFetch } from '@/lib/apiFetch'
import en from '@/locales/en'
import ar from '@/locales/ar'

type InAppNotification = {
  id: number
  type: string
  action: string
  target_type: string | null
  target_id: string | null
  actor_name: string | null
  link: string | null
  data: Record<string, unknown> | null
  read_at: string | null
  created_at: string
}

type PaginatedData = {
  data: InAppNotification[]
  current_page: number
  last_page: number
  next_page_url: string | null
  prev_page_url: string | null
}

type Props = {
  notifications: PaginatedData
  filter: string
}

function relativeTime(iso: string, locale: 'en' | 'ar'): string {
  const messages = locale === 'ar' ? ar : en
  const diff = Date.now() - new Date(iso).getTime()
  const minutes = Math.floor(diff / 60_000)
  if (minutes < 1) return messages.timeJustNow
  if (minutes < 60) return locale === 'ar' ? `منذ ${minutes} دقيقة` : `${minutes}m ago`
  const hours = Math.floor(minutes / 60)
  if (hours < 24) return locale === 'ar' ? `منذ ${hours} ساعة` : `${hours}h ago`
  const days = Math.floor(hours / 24)
  return locale === 'ar' ? `منذ ${days} يوم` : `${days}d ago`
}

export default function Notifications({ notifications, filter }: Props) {
  const { locale, localizedPath, t } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const messages = locale === 'ar' ? ar : en

  const markAsRead = async (id: number) => {
    try {
      await apiFetch(localizedPath(`/api/notifications/${id}/read`), { method: 'PATCH' })
      localizedRouter.get('/notifications', { filter }, { preserveState: true })
    } catch {
      // silently fail
    }
  }

  const markAllRead = async () => {
    try {
      await apiFetch(localizedPath('/api/notifications/read-all'), { method: 'PATCH' })
      localizedRouter.get('/notifications', { filter }, { preserveState: false })
    } catch {
      // silently fail
    }
  }

  const handleClick = async (item: InAppNotification) => {
    if (!item.read_at) {
      try {
        await apiFetch(localizedPath(`/api/notifications/${item.id}/read`), { method: 'PATCH' })
      } catch {
        // silently fail
      }
    }
    if (item.link) {
      localizedRouter.visit(item.link)
    }
  }

  const unreadCount = notifications.data.filter(n => !n.read_at).length

  return (
    <DashboardLayout title={messages.notificationsPageTitle}>
      <PageHeader
        title={messages.notificationsPageTitle}
        description={messages.notificationsPageDescription}
        breadcrumbs={[
          { label: messages.overview, href: '/dashboard' },
          { label: messages.notificationsPageTitle },
        ]}
        actions={(
          <button
            type="button"
            className="button-secondary flex items-center gap-1.5"
            onClick={markAllRead}
          >
            <CheckCheck className="h-4 w-4" />
            {messages.notificationsMarkAllRead}
          </button>
        )}
      />
      <PageContent>
        {/* Filter tabs */}
        <div className="mb-6 flex items-center gap-1 rounded-lg border border-[var(--border)] bg-[var(--surface)] p-1 sm:w-fit">
          <button
            type="button"
            className={`rounded-md px-4 py-2 text-sm font-medium transition-all ${filter === 'all'
              ? 'bg-[var(--brand)] text-white shadow-sm'
              : 'text-[var(--muted)] hover:text-[var(--foreground)] hover:bg-[var(--surface-hover)]'
            }`}
            onClick={() => localizedRouter.get('/notifications', { filter: 'all' }, { preserveState: true })}
          >
            {messages.notificationsAll}
          </button>
          <button
            type="button"
            className={`flex items-center gap-1.5 rounded-md px-4 py-2 text-sm font-medium transition-all ${filter === 'unread'
              ? 'bg-[var(--brand)] text-white shadow-sm'
              : 'text-[var(--muted)] hover:text-[var(--foreground)] hover:bg-[var(--surface-hover)]'
            }`}
            onClick={() => localizedRouter.get('/notifications', { filter: 'unread' }, { preserveState: true })}
          >
            {messages.notificationsUnread}
            {unreadCount > 0 && (
              <span className={`flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-[10px] font-bold ${filter === 'unread' ? 'bg-white/25 text-white' : 'bg-[var(--brand-soft)] text-[var(--brand)]'}`}>
                {unreadCount}
              </span>
            )}
          </button>
        </div>

        {notifications.data.length === 0 ? (
          <section className="state-panel text-center">
            <div className="mx-auto mb-3 flex h-12 w-12 items-center justify-center rounded-full bg-[var(--brand-soft)] text-[var(--brand)]">
              <BellOff className="h-6 w-6" />
            </div>
            <h2 className="text-lg font-semibold">
              {filter === 'unread'
                ? t('notificationsPageNoUnread')
                : messages.notificationsEmpty}
            </h2>
            <p className="mx-auto mt-2 max-w-xl text-slate-600 dark:text-slate-300">
              {t('notificationsPageNotificationsAppear')}
            </p>
          </section>
        ) : (
          <div className="ta-card overflow-hidden p-0">
            {notifications.data.map((item, index) => (
              <div
                key={item.id}
                className={`group flex items-start gap-4 px-5 py-4 transition-colors hover:bg-[var(--surface-hover)] ${index > 0 ? 'border-t border-[var(--border)]' : ''} ${!item.read_at ? 'bg-[var(--brand-soft)]/30' : ''}`}
              >
                {/* Icon */}
                <div className={`mt-0.5 flex h-10 w-10 shrink-0 items-center justify-center rounded-full ${!item.read_at ? 'bg-[var(--brand)] text-white' : 'bg-[var(--surface)] text-[var(--muted)] ring-1 ring-[var(--border)]'}`}>
                  <Bell className="h-4 w-4" />
                </div>

                {/* Content */}
                <div className="min-w-0 flex-1">
                  <div className="flex items-start justify-between gap-3">
                    <div className="min-w-0 flex-1">
                      <button
                        type="button"
                        className={`text-start text-sm leading-snug hover:underline ${!item.read_at ? 'font-semibold' : 'font-medium text-[var(--muted)]'}`}
                        onClick={() => handleClick(item)}
                      >
                        {auditActionLabel(item.action, locale)}
                      </button>
                      <div className="mt-1 flex flex-wrap items-center gap-x-2 gap-y-0.5">
                        {item.actor_name && (
                          <span className="inline-flex items-center gap-1 rounded-md bg-[var(--surface)] px-2 py-0.5 text-xs font-medium text-[var(--foreground)] ring-1 ring-[var(--border)]">
                            {item.actor_name}
                          </span>
                        )}
                        {item.target_type && (
                          <span className="inline-flex items-center gap-1 rounded-md bg-[var(--brand-soft)] px-2 py-0.5 text-xs font-medium text-[var(--brand)]">
                            {auditTargetTypeLabel(item.target_type, locale)}
                          </span>
                        )}
                        <span className="text-xs text-[var(--muted)]">
                          {relativeTime(item.created_at, locale)}
                        </span>
                      </div>
                    </div>

                    {/* Actions */}
                    <div className="flex shrink-0 items-center gap-1 opacity-0 transition-opacity group-hover:opacity-100">
                      {item.link && (
                        <button
                          type="button"
                          className="rounded-lg p-1.5 text-[var(--muted)] transition-colors hover:bg-[var(--surface)] hover:text-[var(--foreground)]"
                          title={t('notificationsPageOpen')}
                          onClick={() => handleClick(item)}
                        >
                          <ExternalLink className="h-4 w-4" />
                        </button>
                      )}
                      {!item.read_at ? (
                        <button
                          type="button"
                          className="rounded-lg p-1.5 text-[var(--brand)] transition-colors hover:bg-[var(--brand-soft)]"
                          title={messages.notificationsMarkRead}
                          onClick={() => markAsRead(item.id)}
                        >
                          <Check className="h-4 w-4" />
                        </button>
                      ) : (
                        <span className="p-1.5 text-green-500">
                          <Check className="h-4 w-4" />
                        </span>
                      )}
                    </div>
                  </div>
                </div>
              </div>
            ))}
          </div>
        )}

        {/* Pagination */}
        {notifications.last_page > 1 && (
          <div className="mt-6 flex items-center justify-center gap-3">
            <button
              type="button"
              className="button-secondary text-sm disabled:opacity-40"
              disabled={!notifications.prev_page_url}
              onClick={() => localizedRouter.get('/notifications', { filter, page: notifications.current_page - 1 }, { preserveState: true })}
            >
              {t('notificationsPagePrevious')}
            </button>
            <span className="rounded-lg bg-[var(--surface)] px-3 py-1.5 text-sm font-medium text-[var(--muted)] ring-1 ring-[var(--border)]">
              {notifications.current_page} / {notifications.last_page}
            </span>
            <button
              type="button"
              className="button-secondary text-sm disabled:opacity-40"
              disabled={!notifications.next_page_url}
              onClick={() => localizedRouter.get('/notifications', { filter, page: notifications.current_page + 1 }, { preserveState: true })}
            >
              {t('notificationsPageNext')}
            </button>
          </div>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
