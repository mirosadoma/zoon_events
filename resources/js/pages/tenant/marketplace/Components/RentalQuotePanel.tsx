import SelectInput from '@/components/forms/SelectInput'
import TextInput from '@/components/forms/TextInput'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { assetTypeLabelKey, formatMinorUnits } from '@/lib/marketplaceLabels'
import type { CatalogAsset, RentalLine } from '@/types/phase6'

type EventOption = { id: string; name: { en: string; ar: string } }

type Props = {
  events?: EventOption[]
  selectedEventId?: string
  onEventChange?: (eventId: string) => void
  windowStart?: string
  windowEnd?: string
  onWindowChange?: (start: string, end: string) => void
  venueTimezone?: string
  selectedAssets?: CatalogAsset[]
  quoteLines?: RentalLine[]
  totalMinor?: number | null
  currency?: string | null
  quoteChanged?: boolean
  submitting?: boolean
  onQuote?: () => void
  onSubmit?: () => void
}

export default function RentalQuotePanel({
  events = [],
  selectedEventId = '',
  onEventChange,
  windowStart = '',
  windowEnd = '',
  onWindowChange,
  venueTimezone,
  selectedAssets = [],
  quoteLines = [],
  totalMinor = null,
  currency = null,
  quoteChanged = false,
  submitting = false,
  onQuote,
  onSubmit,
}: Props) {
  const { locale, t } = useLocale()

  return (
    <section className="ta-card space-y-4" aria-label={t('rentalQuote')}>
      <h2 className="text-lg font-semibold text-[var(--ink)]">{t('rentalQuote')}</h2>

      <SelectInput
        label={t('selectEvent')}
        name="event_id"
        value={selectedEventId}
        onChange={(event) => onEventChange?.(event.target.value)}
        options={[
          { value: '', label: t('selectEvent') },
          ...events.map((item) => ({ value: item.id, label: item.name[locale] })),
        ]}
      />

      <div className="grid gap-3 md:grid-cols-2">
        <TextInput
          label={t('filterStart')}
          name="window_start"
          type="datetime-local"
          value={windowStart}
          onChange={(event) => onWindowChange?.(event.target.value, windowEnd)}
        />
        <TextInput
          label={t('filterEnd')}
          name="window_end"
          type="datetime-local"
          value={windowEnd}
          onChange={(event) => onWindowChange?.(windowStart, event.target.value)}
        />
      </div>

      {venueTimezone ? (
        <p className="text-sm text-[var(--muted)]">
          {t('venueTimezone')}: {venueTimezone}
        </p>
      ) : null}

      {selectedAssets.length > 0 ? (
        <p className="text-sm text-[var(--muted)]">
          {selectedAssets.map((asset) => asset.name[locale]).join(', ')}
        </p>
      ) : null}

      {quoteChanged ? (
        <p className="text-sm text-amber-700" role="alert">
          {t('quoteChanged')}
        </p>
      ) : null}

      {quoteLines.length > 0 ? (
        <DataTable
          title={t('rentalQuote')}
          rows={quoteLines as unknown as Record<string, unknown>[]}
          getRowKey={(row) => String(row.id)}
          columns={[
            {
              key: 'asset_name',
              header: t('venueName'),
              render: (row) => {
                const line = row as unknown as RentalLine
                return line.asset_name[locale]
              },
            },
            {
              key: 'asset_type',
              header: t('assetType'),
              render: (row) => t(assetTypeLabelKey((row as unknown as RentalLine).asset_type)),
            },
            {
              key: 'unit_price_minor',
              header: t('unitPrice'),
              render: (row) => {
                const line = row as unknown as RentalLine
                return formatMinorUnits(line.unit_price_minor, line.currency, locale)
              },
            },
            {
              key: 'billable_units',
              header: t('billableUnits'),
              render: (row) => String(row.billable_units),
            },
            {
              key: 'line_total_minor',
              header: t('lineTotal'),
              render: (row) => {
                const line = row as unknown as RentalLine
                return formatMinorUnits(line.line_total_minor, line.currency, locale)
              },
            },
          ]}
        />
      ) : null}

      {totalMinor != null && currency ? (
        <p className="text-base font-semibold text-[var(--ink)]">
          {t('quoteTotal')}: {formatMinorUnits(totalMinor, currency, locale)}
        </p>
      ) : null}

      <div className="flex gap-2">
        <button
          type="button"
          className="button-secondary"
          disabled={submitting || selectedAssets.length === 0 || !windowStart || !windowEnd}
          onClick={onQuote}
        >
          {t('getQuote')}
        </button>
        <button
          type="button"
          className="button-primary"
          disabled={submitting || selectedAssets.length === 0 || !selectedEventId || quoteChanged}
          onClick={onSubmit}
        >
          {t('submitRentalRequest')}
        </button>
      </div>
    </section>
  )
}
