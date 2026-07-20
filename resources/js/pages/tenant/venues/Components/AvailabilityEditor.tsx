import { EmptyState } from '@/components/feedback'
import TextInput from '@/components/forms/TextInput'
import { useLocale } from '@/hooks/useLocale'
import type { AvailabilityWindow } from '@/types/phase6'

export type AvailabilityDraft = {
  id?: string
  available_from: string
  available_until: string
}

type Props = {
  windows: AvailabilityWindow[]
  draft: AvailabilityDraft
  onDraftChange: (draft: AvailabilityDraft) => void
  onAdd?: () => void
  readOnly?: boolean
  timezone?: string
}

export default function AvailabilityEditor({
  windows = [],
  draft,
  onDraftChange,
  onAdd,
  readOnly = false,
  timezone,
}: Props) {
  const { t } = useLocale()

  return (
    <section className="ta-card space-y-4" aria-label={t('availability')}>
      <div>
        <h3 className="text-lg font-semibold text-[var(--ink)]">{t('availability')}</h3>
        {timezone ? (
          <p className="text-sm text-[var(--muted)]">
            {t('venueTimezone')}: {timezone}
          </p>
        ) : null}
      </div>

      {windows.length === 0 ? (
        <EmptyState title={t('noAvailability')} detail={t('noAvailabilityDetail')} />
      ) : (
        <ul className="space-y-2" aria-label={t('availability')}>
          {windows.map((window) => (
            <li key={window.id} className="rounded-xl border border-[var(--border)] px-3 py-2 text-sm">
              <span>{window.available_from}</span>
              <span aria-hidden="true"> — </span>
              <span>{window.available_until}</span>
              <span className="ms-2 text-[var(--muted)]">({window.status})</span>
            </li>
          ))}
        </ul>
      )}

      {!readOnly ? (
        <div className="grid gap-3 md:grid-cols-2">
          <TextInput
            label={t('filterStart')}
            name="available_from"
            type="datetime-local"
            value={draft.available_from}
            onChange={(event) => onDraftChange({ ...draft, available_from: event.target.value })}
          />
          <TextInput
            label={t('filterEnd')}
            name="available_until"
            type="datetime-local"
            value={draft.available_until}
            onChange={(event) => onDraftChange({ ...draft, available_until: event.target.value })}
          />
          <div className="md:col-span-2">
            <button type="button" className="button-secondary" onClick={onAdd}>
              {t('applyFilters')}
            </button>
          </div>
        </div>
      ) : null}
    </section>
  )
}
