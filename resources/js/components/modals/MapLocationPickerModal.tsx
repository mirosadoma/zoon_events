import { lazy, Suspense, useEffect, useState } from 'react'
import { useLocale } from '@/hooks/useLocale'

const MapPicker = lazy(() => import('@/components/forms/MapPicker'))

type Props = {
  open: boolean
  latitude: number | null
  longitude: number | null
  onSave: (latitude: number, longitude: number) => void
  onCancel: () => void
}

export default function MapLocationPickerModal({
  open,
  latitude,
  longitude,
  onSave,
  onCancel,
}: Props) {
  const { t } = useLocale()
  const [draftLat, setDraftLat] = useState('')
  const [draftLng, setDraftLng] = useState('')

  useEffect(() => {
    if (!open) {
      return
    }

    setDraftLat(latitude === null || Number.isNaN(latitude) ? '' : String(latitude))
    setDraftLng(longitude === null || Number.isNaN(longitude) ? '' : String(longitude))
  }, [open, latitude, longitude])

  if (!open) {
    return null
  }

  const parsedLat = Number(draftLat.trim())
  const parsedLng = Number(draftLng.trim())
  const canSave = draftLat.trim() !== ''
    && draftLng.trim() !== ''
    && Number.isFinite(parsedLat)
    && Number.isFinite(parsedLng)
    && parsedLat >= -90
    && parsedLat <= 90
    && parsedLng >= -180
    && parsedLng <= 180

  return (
    <div
      className="fixed inset-0 z-50 grid place-items-center bg-slate-950/50 p-4"
      role="dialog"
      aria-modal="true"
      aria-labelledby="map-location-picker-title"
    >
      <div className="ta-card relative flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden shadow-xl">
        <div className="border-b border-[var(--border)] px-4 py-3 sm:px-5">
          <h2 id="map-location-picker-title" className="text-lg font-semibold">
            {t('venueMapPickLocationTitle')}
          </h2>
          <p className="mt-1 text-sm text-[var(--muted)]">{t('venueMapPickLocationHint')}</p>
        </div>

        <div className="min-h-0 flex-1 overflow-y-auto px-4 py-4 sm:px-5">
          <Suspense
            fallback={(
              <div className="min-h-[30rem] w-full animate-pulse rounded-lg bg-slate-200 dark:bg-slate-700" aria-hidden />
            )}
          >
            <MapPicker
              label={t('venueMapPickLocationTitle')}
              latitude={draftLat}
              longitude={draftLng}
              onLatitudeChange={setDraftLat}
              onLongitudeChange={setDraftLng}
            />
          </Suspense>
        </div>

        <div className="flex justify-end gap-3 border-t border-[var(--border)] px-4 py-3 sm:px-5">
          <button type="button" className="button-secondary" onClick={onCancel}>
            {t('cancel')}
          </button>
          <button
            type="button"
            className="button-primary"
            disabled={!canSave}
            onClick={() => onSave(parsedLat, parsedLng)}
          >
            {t('save')}
          </button>
        </div>
      </div>
    </div>
  )
}
