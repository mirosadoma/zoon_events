import { Search } from 'lucide-react'
import { EmptyState, LoadingState } from '@/components/feedback'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'

interface LookupMatch {
  attendee_id: string | null
  credential_id: string | null
  display_name: string | null
  ticket_type_label: string | null
  checkin_status: string
}

interface AttendeeLookupResult {
  too_many: boolean
  matches: LookupMatch[]
}

interface AttendeeLookupPanelProps {
  result: AttendeeLookupResult | null
  loading: boolean
  onSelect: (match: LookupMatch) => void
  selecting?: boolean
}

export function AttendeeLookupPanel({
  result,
  loading,
  onSelect,
  selecting = false,
}: AttendeeLookupPanelProps) {
  const { locale } = useLocale()
  const ar = locale === 'ar'

  if (loading) {
    return <LoadingState />
  }

  if (result === null) {
    return (
      <EmptyState
        title={ar ? 'ابحث عن حاضر' : 'Search for an attendee'}
        detail={ar ? 'أدخل الاسم أو البريد أو الهاتف لبدء تسجيل الحضور.' : 'Enter a name, email, or phone to start check-in.'}
      />
    )
  }

  if (result.too_many) {
    return (
      <EmptyState
        title={ar ? 'نتائج كثيرة جداً' : 'Too many matches'}
        detail={ar ? 'ضيّق البحث للحصول على نتائج أدق.' : 'Please refine your search to narrow the results.'}
      />
    )
  }

  if (result.matches.length === 0) {
    return (
      <EmptyState
        title={ar ? 'لا توجد نتائج' : 'No attendees found'}
        detail={ar ? 'جرّب عبارة بحث أخرى.' : 'Try a different search term.'}
      />
    )
  }

  return (
    <section className="ta-card space-y-3 p-0 overflow-hidden">
      <div className="ta-table-toolbar">
        <h2 className="text-lg font-semibold text-[var(--ink)]">
          {ar ? 'نتائج البحث' : 'Search results'}
        </h2>
        <span className="text-sm text-[var(--muted)]">
          {result.matches.length} {ar ? 'نتيجة' : result.matches.length === 1 ? 'match' : 'matches'}
        </span>
      </div>
      <ul className="divide-y divide-[var(--border)]">
        {result.matches.map((match, index) => {
          const name = match.display_name ?? (ar ? 'غير معروف' : 'Unknown')
          const ticket = match.ticket_type_label ?? '—'
          const needsOverride = match.checkin_status === 'rejected'

          return (
            <li key={match.attendee_id ?? match.credential_id ?? index}>
              <button
                type="button"
                disabled={selecting || !match.credential_id}
                className="flex w-full flex-wrap items-center justify-between gap-3 px-4 py-3 text-start transition hover:bg-[var(--surface)] disabled:opacity-50"
                onClick={() => onSelect(match)}
              >
                <div className="min-w-0">
                  <p className="font-medium text-[var(--ink)]">{name}</p>
                  <p className="mt-0.5 text-sm text-[var(--muted)]">{ticket}</p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                  <StatusBadge status={match.checkin_status} />
                  <span className="text-sm font-medium text-[var(--brand)]">
                    {needsOverride
                      ? (ar ? 'تجاوز' : 'Override')
                      : (ar ? 'تسجيل حضور' : 'Check in')}
                  </span>
                </div>
              </button>
            </li>
          )
        })}
      </ul>
    </section>
  )
}

export function DeskSearchHint({ ar }: { ar: boolean }) {
  return (
    <p className="flex items-center gap-2 text-sm text-[var(--muted)]">
      <Search className="h-4 w-4 shrink-0" aria-hidden />
      {ar ? 'ابحث ثم اختر الحاضر من النتائج.' : 'Search, then select an attendee from the results.'}
    </p>
  )
}
