import { useState, useCallback } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { EmptyState } from '@/components/feedback'
import ReasonModal from '@/components/modals/ReasonModal'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'
import { assetTypeLabelKey } from '@/lib/marketplaceLabels'
import type { VenueDetail, VenueAssetRow } from '@/types/phase6'
import VenueForm, { emptyVenueFormValues, type VenueFormValues } from './Components/VenueForm'
import AssetEditor, { emptyAssetFormValues, type AssetFormValues } from './Components/AssetEditor'
import AvailabilityEditor, { type AvailabilityDraft } from './Components/AvailabilityEditor'
import PublicationPanel from './Components/PublicationPanel'

type Props = {
  venue?: VenueDetail | null
  venuePublicId?: string
  tenantId?: string
}

function venueToFormValues(venue: VenueDetail | null | undefined): VenueFormValues {
  if (!venue) return emptyVenueFormValues()

  return {
    name: venue.name,
    description: venue.description ?? { en: '', ar: '' },
    address: venue.address ?? { en: '', ar: '' },
    country_code: venue.country_code ?? '',
    city_code: venue.city_code ?? '',
    timezone: venue.timezone ?? 'Africa/Cairo',
    business_contact_name: venue.business_contact_name ?? '',
    business_contact_email: venue.business_contact_email ?? '',
    business_contact_phone: venue.business_contact_phone ?? '',
    publish_contact: venue.publish_contact ?? false,
  }
}

export default function VenueShow({ venue = null, venuePublicId, tenantId }: Props) {
  const { locale, t } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const readOnly = venue?.status === 'archived'
  const publicId = venuePublicId ?? venue?.public_id
  const venueVersion = venue?.version
  const [formValues, setFormValues] = useState(() => venueToFormValues(venue))
  const [selectedAsset, setSelectedAsset] = useState<VenueAssetRow | null>(null)
  const [assetValues, setAssetValues] = useState<AssetFormValues>(emptyAssetFormValues())
  const [availabilityDraft, setAvailabilityDraft] = useState<AvailabilityDraft>({
    available_from: '',
    available_until: '',
  })
  const [archiveOpen, setArchiveOpen] = useState(false)
  const [saving, setSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [success, setSuccess] = useState<string | null>(null)

  const assets = venue?.assets ?? []

  const handleSave = useCallback(async () => {
    if (!publicId) return
    setSaving(true)
    setError(null)
    setSuccess(null)
    try {
      await apiFetch(`/api/v1/tenant/venues/${publicId}`, {
        method: 'PATCH',
        tenantId,
        idempotency: true,
        body: {
          ...formValues,
          expected_version: venueVersion,
        },
      })
      setSuccess(t('saved'))
      localizedRouter.visit(`/tenant/venues/${publicId}`, { preserveScroll: true })
    } catch (err) {
      setError(err instanceof ApiFetchError ? err.message : t('requestFailed'))
    } finally {
      setSaving(false)
    }
  }, [publicId, venueVersion, tenantId, formValues, localizedRouter, t])

  const handleArchive = useCallback(async (reason: string) => {
    if (!publicId) return
    setArchiveOpen(false)
    setSaving(true)
    setError(null)
    try {
      await apiFetch(`/api/v1/tenant/venues/${publicId}/archive`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: { reason, expected_version: venueVersion },
      })
      localizedRouter.visit(`/tenant/venues/${publicId}`, { preserveScroll: true })
    } catch (err) {
      setError(err instanceof ApiFetchError ? err.message : t('requestFailed'))
    } finally {
      setSaving(false)
    }
  }, [publicId, venueVersion, tenantId, localizedRouter, t])

  if (!venue) {
    return (
      <DashboardLayout title={t('venueDetails')}>
        <PageContent>
          <EmptyState title={t('noVenues')} detail={t('noVenuesDetail')} />
        </PageContent>
      </DashboardLayout>
    )
  }

  return (
    <DashboardLayout title={venue.name[locale]}>
      <PageHeader
        title={venue.name[locale]}
        description={t('venueDetails')}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('venues'), href: '/tenant/venues' },
          { label: venue.name[locale] },
        ]}
        actions={
          <div className="flex items-center gap-2">
            <StatusBadge status={venue.status} size="md" />
            {!readOnly ? (
              <PermissionGate permission="venue.manage">
                <button type="button" className="button-secondary" disabled={saving} onClick={() => setArchiveOpen(true)}>
                  {t('archiveVenue')}
                </button>
              </PermissionGate>
            ) : null}
          </div>
        }
      />
      <PageContent>
        {error ? (
          <p className="mb-4 rounded-xl bg-red-50 px-4 py-2 text-sm text-red-700" role="alert">{error}</p>
        ) : null}
        {success ? (
          <p className="mb-4 rounded-xl bg-green-50 px-4 py-2 text-sm text-green-700" role="status">{success}</p>
        ) : null}

        {readOnly ? (
          <p className="mb-4 text-sm text-amber-700" role="status">
            {t('venueReadOnly')}
          </p>
        ) : null}

        <VenueForm
          venue={venue}
          values={formValues}
          onChange={setFormValues}
          readOnly={readOnly}
        />

        <div className="mt-4 flex gap-2">
          <PermissionGate permission="venue.manage">
            <button
              type="button"
              className="button-primary"
              disabled={readOnly || saving}
              onClick={handleSave}
            >
              {saving ? t('saving') : t('saveVenue')}
            </button>
          </PermissionGate>
        </div>

        <section className="mt-8 space-y-4">
          <h2 className="text-lg font-semibold text-[var(--ink)]">{t('venueAssets')}</h2>
          {assets.length === 0 ? (
            <EmptyState title={t('noAssets')} detail={t('noAssetsDetail')} />
          ) : (
            <DataTable
              title={t('venueAssets')}
              rows={assets as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'name',
                  header: t('venueName'),
                  render: (row) => {
                    const asset = row as unknown as VenueAssetRow
                    return asset.name[locale]
                  },
                },
                {
                  key: 'asset_type',
                  header: t('assetType'),
                  render: (row) => t(assetTypeLabelKey((row as unknown as VenueAssetRow).asset_type)),
                },
                {
                  key: 'operational_status',
                  header: t('assetStatus'),
                  render: (row) => <StatusBadge status={String(row.operational_status)} />,
                },
                {
                  key: 'publication_status',
                  header: t('publicationStatus'),
                  render: (row) => {
                    const status = (row as unknown as VenueAssetRow).publication_status
                    return status ? <StatusBadge status={status} /> : '—'
                  },
                },
                {
                  key: 'actions',
                  header: t('actions'),
                  render: (row) => (
                    <button
                      type="button"
                      className="ta-table-action"
                      onClick={() => {
                        const asset = row as unknown as VenueAssetRow
                        setSelectedAsset(asset)
                        setAssetValues({
                          ...emptyAssetFormValues(),
                          asset_type: asset.asset_type,
                          name_en: asset.name.en,
                          name_ar: asset.name.ar,
                          location_en: asset.location?.en ?? '',
                          location_ar: asset.location?.ar ?? '',
                          operational_status: asset.operational_status,
                          pricing_model: asset.pricing_model ?? 'per_hour',
                          price_minor: asset.price_minor != null ? String(asset.price_minor) : '',
                          currency: asset.currency ?? 'SAR',
                          capacity_per_minute: asset.capacity_per_minute != null ? String(asset.capacity_per_minute) : '',
                          binding_value: '',
                        })
                      }}
                    >
                      {t('viewVenue')}
                    </button>
                  ),
                },
              ]}
            />
          )}
        </section>

        {selectedAsset ? (
          <div className="mt-8 space-y-6">
            <AssetEditor
              asset={selectedAsset}
              values={assetValues}
              onChange={setAssetValues}
              readOnly={readOnly}
            />
            <AvailabilityEditor
              windows={[]}
              draft={availabilityDraft}
              onDraftChange={setAvailabilityDraft}
              timezone={venue.timezone}
              readOnly={readOnly}
            />
            <PublicationPanel
              asset={selectedAsset}
              readiness={selectedAsset.publication_readiness ?? venue.publication_readiness ?? []}
              readOnly={readOnly}
            />
          </div>
        ) : null}
      </PageContent>

      <ReasonModal
        open={archiveOpen}
        title={t('archiveVenue')}
        message={t('archiveVenueConfirm')}
        reasonLabel={t('reason')}
        confirmLabel={t('archiveVenue')}
        cancelLabel={t('cancel')}
        loading={saving}
        onConfirm={handleArchive}
        onCancel={() => setArchiveOpen(false)}
      />
    </DashboardLayout>
  )
}
