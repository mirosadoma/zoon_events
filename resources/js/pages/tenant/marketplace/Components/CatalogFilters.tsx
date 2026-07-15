import SelectInput from '@/components/forms/SelectInput'
import TextInput from '@/components/forms/TextInput'
import { useLocale } from '@/hooks/useLocale'
import { ASSET_TYPES, assetTypeLabelKey } from '@/lib/marketplaceLabels'

export type CatalogFilterValues = {
  venue_id: string
  country_code: string
  city_code: string
  asset_type: string
  capability: string
  min_capacity: string
  currency: string
  start_at: string
  end_at: string
}

type Props = {
  values: CatalogFilterValues
  onChange: (values: CatalogFilterValues) => void
  onApply: () => void
  onClear: () => void
}

export default function CatalogFilters({ values, onChange, onApply, onClear }: Props) {
  const { t } = useLocale()

  function patch(partial: Partial<CatalogFilterValues>) {
    onChange({ ...values, ...partial })
  }

  return (
    <section className="ta-card grid gap-3 md:grid-cols-3" aria-label={t('applyFilters')}>
      <TextInput
        label={t('filterVenue')}
        name="venue_id"
        value={values.venue_id}
        onChange={(event) => patch({ venue_id: event.target.value })}
      />
      <TextInput
        label={t('filterCountry')}
        name="country_code"
        value={values.country_code}
        onChange={(event) => patch({ country_code: event.target.value.toUpperCase() })}
      />
      <TextInput
        label={t('filterCity')}
        name="city_code"
        value={values.city_code}
        onChange={(event) => patch({ city_code: event.target.value })}
      />
      <SelectInput
        label={t('filterAssetType')}
        name="asset_type"
        value={values.asset_type}
        onChange={(event) => patch({ asset_type: event.target.value })}
        options={[
          { value: '', label: t('allTypes') },
          ...ASSET_TYPES.map((type) => ({ value: type, label: t(assetTypeLabelKey(type)) })),
        ]}
      />
      <TextInput
        label={t('filterCapability')}
        name="capability"
        value={values.capability}
        onChange={(event) => patch({ capability: event.target.value })}
      />
      <TextInput
        label={t('filterMinCapacity')}
        name="min_capacity"
        type="number"
        min={0}
        value={values.min_capacity}
        onChange={(event) => patch({ min_capacity: event.target.value })}
      />
      <TextInput
        label={t('filterCurrency')}
        name="currency"
        value={values.currency}
        onChange={(event) => patch({ currency: event.target.value.toUpperCase() })}
      />
      <TextInput
        label={t('filterStart')}
        name="start_at"
        type="datetime-local"
        value={values.start_at}
        onChange={(event) => patch({ start_at: event.target.value })}
      />
      <TextInput
        label={t('filterEnd')}
        name="end_at"
        type="datetime-local"
        value={values.end_at}
        onChange={(event) => patch({ end_at: event.target.value })}
      />
      <div className="flex items-end gap-2 md:col-span-3">
        <button type="button" className="button-primary" onClick={onApply}>
          {t('applyFilters')}
        </button>
        <button type="button" className="button-secondary" onClick={onClear}>
          {t('clearFilters')}
        </button>
      </div>
    </section>
  )
}

export function emptyCatalogFilters(): CatalogFilterValues {
  return {
    venue_id: '',
    country_code: '',
    city_code: '',
    asset_type: '',
    capability: '',
    min_capacity: '',
    currency: '',
    start_at: '',
    end_at: '',
  }
}
