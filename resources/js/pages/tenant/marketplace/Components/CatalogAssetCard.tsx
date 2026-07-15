import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { assetTypeLabelKey, formatMinorUnits } from '@/lib/marketplaceLabels'
import type { CatalogAsset } from '@/types/phase6'

type Props = {
  asset: CatalogAsset
  selected?: boolean
  onSelect?: (asset: CatalogAsset) => void
  disabled?: boolean
  advisoryWindow?: boolean
}

export default function CatalogAssetCard({
  asset,
  selected = false,
  onSelect,
  disabled = false,
  advisoryWindow = false,
}: Props) {
  const { locale, t } = useLocale()

  return (
    <article
      className={`ta-card space-y-2 ${selected ? 'ring-2 ring-[var(--brand)]' : ''}`}
      aria-label={asset.name[locale]}
    >
      <div className="flex flex-wrap items-start justify-between gap-2">
        <div>
          <h3 className="font-semibold text-[var(--ink)]">{asset.name[locale]}</h3>
          <p className="text-sm text-[var(--muted)]">{asset.venue_name[locale]}</p>
        </div>
        <StatusBadge status="published" />
      </div>
      <dl className="grid gap-1 text-sm">
        <div className="flex justify-between gap-2">
          <dt className="text-[var(--muted)]">{t('assetType')}</dt>
          <dd>{t(assetTypeLabelKey(asset.asset_type))}</dd>
        </div>
        <div className="flex justify-between gap-2">
          <dt className="text-[var(--muted)]">{t('venueLocation')}</dt>
          <dd>{[asset.city_code, asset.country_code].filter(Boolean).join(', ') || '—'}</dd>
        </div>
        <div className="flex justify-between gap-2">
          <dt className="text-[var(--muted)]">{t('unitPrice')}</dt>
          <dd>{formatMinorUnits(asset.price_minor, asset.currency, locale)}</dd>
        </div>
      </dl>
      {advisoryWindow ? (
        <p className="text-xs text-[var(--muted)]" role="note">
          {t('advisoryAvailability')}
        </p>
      ) : null}
      <button
        type="button"
        className="button-secondary w-full"
        disabled={disabled}
        aria-pressed={selected}
        onClick={() => onSelect?.(asset)}
      >
        {t('requestQuote')}
      </button>
    </article>
  )
}
