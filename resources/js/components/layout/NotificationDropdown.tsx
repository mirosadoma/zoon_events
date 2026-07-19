import { Bell, CheckCheck, ExternalLink } from 'lucide-react'
import { useCallback, useRef, useState } from 'react'
import { usePage } from '@inertiajs/react'
import { useClickOutside } from '@/hooks/useClickOutside'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { auditActionLabel, auditTargetTypeLabel } from '@/lib/permissionCatalog'
import { apiFetch } from '@/lib/apiFetch'
import ar from '@/locales/ar'
import en from '@/locales/en'

type InAppNotification = {
  id: number
  type: string
  action: string
  target_type: string | null
  target_id: string | null
  actor_name: string | null
  link: string | null
  read_at: string | null
  created_at: string
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

export default function NotificationDropdown() {
  const { locale, localizedPath } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const messages = locale === 'ar' ? ar : en
  const [open, setOpen] = useState(false)
  const [items, setItems] = useState<InAppNotification[]>([])
  const [loading, setLoading] = useState(false)
  const [fetched, setFetched] = useState(false)
  const ref = useRef<HTMLDivElement>(null)
  const page = usePage()
  const unreadCount = (page.props as Record<string, unknown>).unread_notifications_count as number | undefined
  const fetchRef = useRef(0)

  useClickOutside(ref, () => setOpen(false), open)

  const doFetch = useCallback(async (seq: number) => {
    try {
      const data = await apiFetch<InAppNotification[]>(localizedPath('/api/notifications/recent'), {
        skipAuthRedirect: true,
      })
      if (fetchRef.current === seq) {
        setItems(Array.isArray(data) ? data : [])
      }
    } catch {
      if (fetchRef.current === seq) setItems([])
    } finally {
      if (fetchRef.current === seq) {
        setLoading(false)
        setFetched(true)
      }
    }
  }, [localizedPath])

  const handleOpen = useCallback((nextOpen: boolean) => {
    setOpen(nextOpen)
    if (nextOpen) {
      setLoading(true)
      setFetched(false)
      const seq = ++fetchRef.current
      doFetch(seq)
    }
  }, [doFetch])

  const markAllRead = async () => {
    try {
      await apiFetch(localizedPath('/api/notifications/read-all'), { method: 'PATCH' })
      setItems(prev => prev.map(n => ({ ...n, read_at: new Date().toISOString() })))
    } catch {
      // silently fail
    }
  }

  const handleItemClick = async (item: InAppNotification) => {
    if (!item.read_at) {
      try {
        await apiFetch(localizedPath(`/api/notifications/${item.id}/read`), { method: 'PATCH' })
      } catch {
        // silently fail
      }
    }
    setOpen(false)
    if (item.link) {
      localizedRouter.visit(item.link)
    }
  }

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        className="ta-topbar-action relative"
        onClick={() => handleOpen(!open)}
        aria-expanded={open}
        aria-label={messages.notifications}
      >
        <Bell className="h-4 w-4" />
        {(unreadCount ?? 0) > 0 && (
          <span className="absolute end-2 top-2 h-2 w-2 rounded-full bg-[var(--warning)] ring-2 ring-[var(--surface-elevated)]" />
        )}
      </button>
      {open ? (
          <>
          <button
            type="button"
            className="fixed inset-0 z-40 bg-black/20 sm:hidden"
            aria-label={messages.notificationDropdownCloseLabel}
            onClick={() => setOpen(false)}
          />
          <div className="fixed inset-x-4 top-[3.75rem] z-50 overflow-hidden rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] shadow-xl sm:absolute sm:inset-x-auto sm:top-full sm:mt-2 sm:w-96 sm:end-0">
            <div className="flex items-center justify-between gap-3 border-b border-[var(--border)] bg-[var(--surface)] px-4 py-3">
              <div className="flex items-center gap-2">
                <Bell className="h-4 w-4 text-[var(--brand)]" />
                <p className="text-sm font-semibold">{messages.notifications}</p>
              </div>
              <div className="flex items-center gap-2">
                {(unreadCount ?? 0) > 0 && (
                  <button
                    type="button"
                    className="flex items-center gap-1 rounded-md px-2 py-1 text-xs font-medium text-[var(--brand)] transition-colors hover:bg-[var(--brand-soft)]"
                    onClick={markAllRead}
                  >
                    <CheckCheck className="h-3.5 w-3.5" />
                    {messages.notificationsMarkAllRead}
                  </button>
                )}
                {(unreadCount ?? 0) > 0 && (
                  <span className="flex h-5 min-w-5 items-center justify-center rounded-full bg-[var(--brand)] px-1.5 text-[10px] font-bold text-white">
                    {unreadCount}
                  </span>
                )}
              </div>
            </div>

            <div className="max-h-80 overflow-y-auto">
              {loading && !fetched ? (
                  <div className="flex items-center justify-center gap-2 p-6">
                  <div className="h-5 w-5 animate-spin rounded-full border-2 border-[var(--border)] border-t-[var(--brand)]" />
                  <span className="text-xs text-[var(--muted)]">{messages.notificationDropdownLoading}</span>
                </div>
              ) : fetched && items.length === 0 ? (
                <div className="flex flex-col items-center gap-2 p-8">
                  <div className="flex h-10 w-10 items-center justify-center rounded-full bg-[var(--brand-soft)]">
                    <Bell className="h-5 w-5 text-[var(--brand)]" />
                  </div>
                  <p className="text-sm font-medium text-[var(--muted)]">{messages.notificationsEmpty}</p>
                </div>
              ) : (
                <ul>
                  {items.map((item, index) => (
                    <li key={item.id}>
                      <button
                        type="button"
                        className={`flex w-full items-start gap-3 px-4 py-3 text-start transition-colors hover:bg-[var(--surface-hover)] ${index > 0 ? 'border-t border-[var(--border)]' : ''} ${!item.read_at ? 'bg-[var(--brand-soft)]/40' : ''}`}
                        onClick={() => handleItemClick(item)}
                      >
                        <div className={`mt-0.5 flex h-8 w-8 shrink-0 items-center justify-center rounded-full ${!item.read_at ? 'bg-[var(--brand)] text-white' : 'bg-[var(--surface)] text-[var(--muted)]'}`}>
                          <Bell className="h-3.5 w-3.5" />
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className={`text-sm leading-snug ${!item.read_at ? 'font-semibold' : 'font-medium text-[var(--muted)]'}`}>
                            {auditActionLabel(item.action, locale)}
                          </p>
                          <div className="mt-0.5 flex items-center gap-1.5 text-xs text-[var(--muted)]">
                            {item.actor_name && <span className="truncate">{item.actor_name}</span>}
                            {item.actor_name && item.target_type && <span>·</span>}
                            {item.target_type && (
                              <span className="truncate">{auditTargetTypeLabel(item.target_type, locale)}</span>
                            )}
                          </div>
                          <p className="mt-0.5 text-[11px] text-[var(--muted)]">
                            {relativeTime(item.created_at, locale)}
                          </p>
                        </div>
                      </button>
                    </li>
                  ))}
                </ul>
              )}
            </div>

            <div className="border-t border-[var(--border)] bg-[var(--surface)]">
              <button
                type="button"
                className="flex w-full items-center justify-center gap-1.5 px-4 py-2.5 text-xs font-semibold text-[var(--brand)] transition-colors hover:bg-[var(--brand-soft)]"
                onClick={() => {
                  setOpen(false)
                  localizedRouter.visit('/notifications')
                }}
              >
                <ExternalLink className="h-3.5 w-3.5" />
                {messages.notificationsViewAll}
              </button>
            </div>
          </div>
        </>
      ) : null}
    </div>
  )
}
