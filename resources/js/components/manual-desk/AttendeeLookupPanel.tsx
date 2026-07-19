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
  const { locale, t } = useLocale()

  if (loading) {
    return <LoadingState />
  }

  if (result === null) {
    return (
      <EmptyState
        title={t('attendeeLookupSearchTitle')}
        detail={t('attendeeLookupSearchDetail')}
      />
    )
  }

  if (result.too_many) {
    return (
      <EmptyState
        title={t('attendeeLookupTooMany')}
        detail={t('attendeeLookupTooManyDetail')}
      />
    )
  }

  if (result.matches.length === 0) {
    return (
      <EmptyState
        title={t('attendeeLookupNoResults')}
        detail={t('attendeeLookupNoResultsDetail')}
      />
    )
  }

  return (
    <section className="ta-card space-y-3 p-0 overflow-hidden">
      <div className="ta-table-toolbar">
        <h2 className="text-lg font-semibold text-[var(--ink)]">
          {t('attendeeLookupResults')}
        </h2>
        <span className="text-sm text-[var(--muted)]">
          {result.matches.length}{' '}
          {locale === 'ar'
            ? t('attendeeLookupResultCount')
            : result.matches.length === 1
              ? t('attendeeLookupResultSingular')
              : t('attendeeLookupResultPlural')}
        </span>
      </div>
      <ul className="divide-y divide-[var(--border)]">
        {result.matches.map((match, index) => {
          const name = match.display_name ?? t('attendeeLookupUnknown')
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
                      ? t('attendeeLookupOverride')
                      : t('attendeeLookupCheckIn')}
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

export function DeskSearchHint() {
  const { t } = useLocale()
  return (
    <p className="flex items-center gap-2 text-sm text-[var(--muted)]">
      <Search className="h-4 w-4 shrink-0" aria-hidden />
      {t('attendeeLookupHint')}
    </p>
  )
}
