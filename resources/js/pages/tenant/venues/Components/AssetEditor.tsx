import SelectInput from '@/components/forms/SelectInput'
import TextInput from '@/components/forms/TextInput'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { ASSET_TYPES, assetTypeLabelKey } from '@/lib/marketplaceLabels'
import type { AssetType, VenueAssetRow } from '@/types/phase6'

export type AssetFormValues = {
  asset_type: AssetType
  name_en: string
  name_ar: string
  location_en: string
  location_ar: string
  operational_status: string
  pricing_model: string
  price_minor: string
  currency: string
  capacity_per_minute: string
  binding_value: string
}

type Props = {
  asset?: VenueAssetRow | null
  values: AssetFormValues
  onChange: (values: AssetFormValues) => void
  errors?: Record<string, string>
  readOnly?: boolean
}

export default function AssetEditor({ asset, values, onChange, errors = {}, readOnly = false }: Props) {
  const { t } = useLocale()

  function patch(partial: Partial<AssetFormValues>) {
    onChange({ ...values, ...partial })
  }

  return (
    <section className="ta-card space-y-4" aria-label={t('venueAssets')}>
      <div className="flex flex-wrap items-center gap-2">
        <h3 className="text-lg font-semibold text-[var(--ink)]">{t('venueAssets')}</h3>
        {asset ? (
          <>
            <StatusBadge status={asset.operational_status} />
            {asset.publication_status ? <StatusBadge status={asset.publication_status} /> : null}
          </>
        ) : null}
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <SelectInput
          label={t('assetType')}
          name="asset_type"
          value={values.asset_type}
          onChange={(event) => patch({ asset_type: event.target.value as AssetType })}
          options={ASSET_TYPES.map((type) => ({ value: type, label: t(assetTypeLabelKey(type)) }))}
          disabled={readOnly}
        />
        <SelectInput
          label={t('assetStatus')}
          name="operational_status"
          value={values.operational_status}
          onChange={(event) => patch({ operational_status: event.target.value })}
          options={['draft', 'active', 'maintenance', 'offline', 'retired'].map((status) => ({
            value: status,
            label: status,
          }))}
          disabled={readOnly}
        />
        <TextInput
          label={`${t('venueName')} (EN)`}
          name="name_en"
          value={values.name_en}
          onChange={(event) => patch({ name_en: event.target.value })}
          error={errors.name_en}
          disabled={readOnly}
          required
        />
        <TextInput
          label={`${t('venueName')} (AR)`}
          name="name_ar"
          value={values.name_ar}
          onChange={(event) => patch({ name_ar: event.target.value })}
          error={errors.name_ar}
          disabled={readOnly}
          required
        />
        <TextInput
          label={`${t('venueLocation')} (EN)`}
          name="location_en"
          value={values.location_en}
          onChange={(event) => patch({ location_en: event.target.value })}
          error={errors.location_en}
          disabled={readOnly}
        />
        <TextInput
          label={`${t('venueLocation')} (AR)`}
          name="location_ar"
          value={values.location_ar}
          onChange={(event) => patch({ location_ar: event.target.value })}
          error={errors.location_ar}
          disabled={readOnly}
        />
        <SelectInput
          label={t('unitPrice')}
          name="pricing_model"
          value={values.pricing_model}
          onChange={(event) => patch({ pricing_model: event.target.value })}
          options={[
            { value: 'per_hour', label: 'per_hour' },
            { value: 'per_day', label: 'per_day' },
            { value: 'per_rental', label: 'per_rental' },
          ]}
          disabled={readOnly}
        />
        <TextInput
          label={t('unitPrice')}
          name="price_minor"
          type="number"
          min={0}
          value={values.price_minor}
          onChange={(event) => patch({ price_minor: event.target.value })}
          error={errors.price_minor}
          disabled={readOnly}
        />
        <TextInput
          label={t('filterCurrency')}
          name="currency"
          value={values.currency}
          onChange={(event) => patch({ currency: event.target.value.toUpperCase() })}
          error={errors.currency}
          disabled={readOnly}
        />
        <TextInput
          label={t('filterMinCapacity')}
          name="capacity_per_minute"
          type="number"
          min={0}
          value={values.capacity_per_minute}
          onChange={(event) => patch({ capacity_per_minute: event.target.value })}
          error={errors.capacity_per_minute}
          disabled={readOnly}
        />
        {values.asset_type !== 'camera' ? (
          <div className="md:col-span-2">
            <TextInput
              label={t('bindingMasked')}
              name="binding_value"
              type="password"
              autoComplete="off"
              value={values.binding_value}
              onChange={(event) => patch({ binding_value: event.target.value })}
              error={errors.binding_value}
              disabled={readOnly}
              placeholder={asset?.has_binding ? '••••••••' : ''}
            />
            {asset?.has_binding ? (
              <p className="mt-1 text-sm text-[var(--muted)]">{t('bindingMasked')}</p>
            ) : null}
          </div>
        ) : null}
      </div>
    </section>
  )
}

export function emptyAssetFormValues(): AssetFormValues {
  return {
    asset_type: 'room',
    name_en: '',
    name_ar: '',
    location_en: '',
    location_ar: '',
    operational_status: 'draft',
    pricing_model: 'per_hour',
    price_minor: '',
    currency: 'SAR',
    capacity_per_minute: '',
    binding_value: '',
  }
}
