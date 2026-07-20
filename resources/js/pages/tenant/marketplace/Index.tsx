import { useState, useCallback } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState, ErrorState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import type { CatalogAsset, RentalLine } from '@/types/phase6'
import CatalogFilters, { emptyCatalogFilters } from './Components/CatalogFilters'
import CatalogAssetCard from './Components/CatalogAssetCard'
import RentalQuotePanel from './Components/RentalQuotePanel'

type EventOption = { id: string; name: { en: string; ar: string } }

type Props = {
  assets?: CatalogAsset[]
  events?: EventOption[]
  filters?: ReturnType<typeof emptyCatalogFilters>
  unavailable?: boolean
  tenantId?: string
}

type QuoteResponse = {
  lines: RentalLine[]
  total_minor: number
  currency: string
  digest: string
  version: number
}

export default function MarketplaceIndex({
  assets = [],
  events = [],
  filters = emptyCatalogFilters(),
  unavailable = false,
  tenantId,
}: Props) {
  const { t } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const [filterValues, setFilterValues] = useState(filters)
  const [selectedAssets, setSelectedAssets] = useState<CatalogAsset[]>([])
  const [selectedEventId, setSelectedEventId] = useState('')
  const [windowStart, setWindowStart] = useState(filters.start_at ?? '')
  const [windowEnd, setWindowEnd] = useState(filters.end_at ?? '')
  const [quoteLines, setQuoteLines] = useState<RentalLine[]>([])
  const [quoteMeta, setQuoteMeta] = useState<{ total: number; currency: string; digest: string; version: number } | null>(null)
  const [quoteChanged, setQuoteChanged] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const hasFilters = Object.values(filterValues).some(Boolean)
  const selectedVenueId = selectedAssets[0]?.venue_id ?? null

  function toggleAsset(asset: CatalogAsset) {
    setSelectedAssets((current) => {
      const exists = current.some((item) => item.id === asset.id)
      if (exists) {
        return current.filter((item) => item.id !== asset.id)
      }
      if (selectedVenueId && asset.venue_id !== selectedVenueId) {
        return [asset]
      }
      return [...current, asset]
    })
    setQuoteChanged(true)
  }

  const fetchQuote = useCallback(async () => {
    if (selectedAssets.length === 0 || !windowStart || !windowEnd) return
    setError(null)
    try {
      const result = await apiFetch<QuoteResponse>('/api/v1/tenant/marketplace/quotes', {
        method: 'POST',
        tenantId,
        body: {
          publication_public_ids: selectedAssets.map((a) => a.publication_id),
          requested_start_at: windowStart,
          requested_end_at: windowEnd,
        },
      })
      setQuoteLines(result.lines ?? [])
      setQuoteMeta({
        total: result.total_minor,
        currency: result.currency,
        digest: result.digest,
        version: result.version,
      })
      setQuoteChanged(false)
    } catch (err) {
      setError(err instanceof ApiFetchError ? err.message : t('requestFailed'))
    }
  }, [selectedAssets, windowStart, windowEnd, tenantId, t])

  const handleSubmit = useCallback(async () => {
    if (!selectedEventId || selectedAssets.length === 0 || !quoteMeta) return
    setSubmitting(true)
    setError(null)
    try {
      await apiFetch('/api/v1/tenant/marketplace/rentals', {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: {
          event_id: selectedEventId,
          publication_public_ids: selectedAssets.map((a) => a.publication_id),
          requested_start_at: windowStart,
          requested_end_at: windowEnd,
          quote_digest: quoteMeta.digest,
          quote_version: quoteMeta.version,
        },
      })
      localizedRouter.visit('/tenant/marketplace/rentals')
    } catch (err) {
      setError(err instanceof ApiFetchError ? err.message : t('requestFailed'))
    } finally {
      setSubmitting(false)
    }
  }, [selectedEventId, selectedAssets, quoteMeta, windowStart, windowEnd, tenantId, localizedRouter, t])

  function applyFilters() {
    localizedRouter.get('/tenant/marketplace', filterValues, { preserveState: true, preserveScroll: true })
  }

  return (
    <DashboardLayout title={t('catalogTitle')}>
      <PageHeader
        title={t('catalogTitle')}
        description={t('catalogDescription')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('marketplace') },
        ]}
      />
      <PageContent>
        {error ? (
          <p className="mb-4 rounded-xl bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{error}</p>
        ) : null}

        <CatalogFilters
          values={filterValues}
          onChange={setFilterValues}
          onApply={applyFilters}
          onClear={() => {
            setFilterValues(emptyCatalogFilters())
            localizedRouter.get('/tenant/marketplace', {}, { preserveState: true })
          }}
        />

        {unavailable ? (
          <div className="mt-6">
            <ErrorState title={t('catalogUnavailable')} detail={t('catalogRetry')} />
            <button type="button" className="button-secondary mt-3" onClick={() => window.location.reload()}>
              {t('catalogRetry')}
            </button>
          </div>
        ) : assets.length === 0 ? (
          <EmptyState
            title={hasFilters ? t('noCatalogMatch') : t('noCatalogAssets')}
            detail={hasFilters ? t('noCatalogMatchDetail') : t('noCatalogAssetsDetail')}
          />
        ) : (
          <div className="mt-6 grid gap-4 lg:grid-cols-[2fr_1fr]">
            <div
              className="grid gap-4 sm:grid-cols-2"
              role="list"
              aria-label={t('catalogTitle')}
            >
              {assets.map((asset) => (
                <CatalogAssetCard
                  key={asset.id}
                  asset={asset}
                  selected={selectedAssets.some((item) => item.id === asset.id)}
                  disabled={Boolean(selectedVenueId && asset.venue_id !== selectedVenueId)}
                  advisoryWindow={Boolean(filterValues.start_at && filterValues.end_at)}
                  onSelect={toggleAsset}
                />
              ))}
            </div>
            <RentalQuotePanel
              events={events}
              selectedEventId={selectedEventId}
              onEventChange={setSelectedEventId}
              windowStart={windowStart}
              windowEnd={windowEnd}
              onWindowChange={(start, end) => {
                setWindowStart(start)
                setWindowEnd(end)
                setQuoteChanged(true)
              }}
              venueTimezone={selectedAssets[0]?.venue_timezone}
              selectedAssets={selectedAssets}
              quoteLines={quoteLines}
              totalMinor={quoteMeta?.total ?? null}
              currency={quoteMeta?.currency ?? null}
              quoteChanged={quoteChanged}
              submitting={submitting}
              onQuote={fetchQuote}
              onSubmit={handleSubmit}
            />
          </div>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
