import { Bell } from 'lucide-react'
import { useRef, useState } from 'react'
import { useClickOutside } from '@/hooks/useClickOutside'
import { useLocale } from '@/hooks/useLocale'
import ar from '@/locales/ar'
import en from '@/locales/en'

export default function NotificationDropdown() {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const [open, setOpen] = useState(false)
  const ref = useRef<HTMLDivElement>(null)

  useClickOutside(ref, () => setOpen(false), open)

  return (
    <div ref={ref} className="relative">
      <button
        type="button"
        className="button-secondary relative p-2"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
        aria-label={messages.notifications}
      >
        <Bell className="h-4 w-4" />
        <span className="absolute end-1.5 top-1.5 h-2 w-2 rounded-full bg-orange-500" />
      </button>
      {open ? (
        <div className="absolute end-0 z-50 mt-2 w-80 rounded-xl border border-[var(--border)] bg-[var(--surface-elevated)] p-4 shadow-xl">
          <div className="flex items-center justify-between gap-3">
            <p className="text-sm font-semibold">{messages.notifications}</p>
            <span className="ta-badge ta-badge-neutral">0</span>
          </div>
          <div className="mt-4 rounded-lg border border-dashed border-[var(--border)] p-4 text-center">
            <p className="text-sm text-[var(--muted)]">{messages.notificationsEmpty}</p>
          </div>
        </div>
      ) : null}
    </div>
  )
}
