import { FormEvent, useMemo, useRef, useState } from 'react'
import {
  Circle as CircleIcon,
  MousePointer2,
  Pentagon,
  Redo2,
  Square,
  Trash2,
  Undo2,
  Upload,
  ZoomIn,
  ZoomOut,
} from 'lucide-react'
import VenueMapCanvas from '@/components/venue-map/VenueMapCanvas'
import ResizableMapFrame from '@/components/venue-map/ResizableMapFrame'
import {
  defaultFillForType,
  type EditorTool,
  type MapZone,
  type RelativePoint,
  type VenueMapData,
} from '@/components/venue-map/types'
import { useMapHistory } from '@/components/venue-map/useMapHistory'
import SelectInput from '@/components/forms/SelectInput'
import TextInput from '@/components/forms/TextInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'

type Props = {
  eventId: string
  tenantId: string
  venueId: string
  venueLatitude: number | null
  venueLongitude: number | null
  initialMap: VenueMapData | null
  initialZones: MapZone[]
  zoneTypes: string[]
}

function toDraft(zones: Array<Record<string, unknown>>): MapZone[] {
  return zones.map((zone) => ({
    key: String(zone.id ?? crypto.randomUUID()),
    id: zone.id ? String(zone.id) : undefined,
    zone_name_en: String(zone.zone_name_en ?? ''),
    zone_name_ar: String(zone.zone_name_ar ?? ''),
    type: String(zone.type ?? 'hall'),
    capacity: zone.capacity === null || zone.capacity === undefined ? null : Number(zone.capacity),
    shape_type: (zone.shape_type as MapZone['shape_type']) ?? null,
    polygon_coordinates: (zone.polygon_coordinates as RelativePoint[] | null) ?? null,
    shape_radius: zone.shape_radius === null || zone.shape_radius === undefined
      ? null
      : Number(zone.shape_radius),
    label: zone.label ? String(zone.label) : null,
    google_maps_url: zone.google_maps_url ? String(zone.google_maps_url) : null,
    lat: zone.lat === null || zone.lat === undefined ? null : Number(zone.lat),
    lng: zone.lng === null || zone.lng === undefined ? null : Number(zone.lng),
    fill_color: zone.fill_color ? String(zone.fill_color) : null,
    stroke_color: zone.stroke_color ? String(zone.stroke_color) : null,
    opacity: zone.opacity === null || zone.opacity === undefined ? null : Number(zone.opacity),
    stroke_width: zone.stroke_width === null || zone.stroke_width === undefined
      ? null
      : Number(zone.stroke_width),
  }))
}

export default function VenueMapEditor({
  eventId,
  tenantId,
  venueId,
  venueLatitude,
  venueLongitude,
  initialMap,
  initialZones,
  zoneTypes,
}: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const fileRef = useRef<HTMLInputElement | null>(null)
  const [map, setMap] = useState<VenueMapData | null>(initialMap)
  const [tool, setTool] = useState<EditorTool>('select')
  const [selectedKey, setSelectedKey] = useState<string | null>(initialZones[0]?.key ?? null)
  const [draftPoint, setDraftPoint] = useState<RelativePoint | null>(null)
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)
  const history = useMapHistory(toDraft(initialZones as unknown as Array<Record<string, unknown>>))

  const selected = history.zones.find((zone) => zone.key === selectedKey) ?? null

  const typeOptions = useMemo(
    () => zoneTypes.map((type) => ({
      value: type,
      label: t(`eventZoneType_${type}` as 'eventZoneType_hall'),
    })),
    [zoneTypes, t],
  )

  function updateSelected(patch: Partial<MapZone>) {
    if (!selected) return
    history.commit(history.zones.map((zone) => (
      zone.key === selected.key ? { ...zone, ...patch } : zone
    )))
  }

  async function uploadMap(file: File) {
    setUploading(true)
    try {
      const body = new FormData()
      body.append('image', file)

      const image = await createImageBitmap(file).catch(() => null)
      if (image) {
        body.append('width', String(image.width))
        body.append('height', String(image.height))
        image.close()
      }

      const result = await apiFetch<{
        map: VenueMapData | null
        zones: Array<Record<string, unknown>>
      }>(`/api/v1/tenant/events/${eventId}/venues/${venueId}/map`, {
        method: 'POST',
        tenantId,
        body,
      })

      setMap(result.map)
      history.replace(toDraft(result.zones))
      toast(t('venueMapUploaded'), 'success')
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('requestFailed'), 'error')
    } finally {
      setUploading(false)
    }
  }

  async function saveZones(event: FormEvent) {
    event.preventDefault()
    setSaving(true)
    try {
      const incomplete = history.zones.find((zone) => (
        zone.zone_name_en.trim() === '' || zone.zone_name_ar.trim() === '' || zone.type.trim() === ''
      ))
      if (incomplete) {
        toast(t('eventZonesIncomplete'), 'error')
        return
      }

      const result = await apiFetch<{ zones: Array<Record<string, unknown>> }>(
        `/api/v1/tenant/events/${eventId}/zones`,
        {
          method: 'PUT',
          tenantId,
          idempotency: true,
          body: {
            venue_id: Number(venueId),
            zones: history.zones.map((zone) => ({
              id: zone.id ? Number(zone.id) : undefined,
              zone_name_en: zone.zone_name_en.trim(),
              zone_name_ar: zone.zone_name_ar.trim(),
              type: zone.type,
              capacity: zone.capacity,
              shape_type: zone.shape_type,
              polygon_coordinates: zone.polygon_coordinates,
              shape_radius: zone.shape_radius,
              label: zone.label,
              google_maps_url: zone.google_maps_url,
              lat: zone.lat,
              lng: zone.lng,
              fill_color: zone.fill_color ?? defaultFillForType(zone.type),
              stroke_color: zone.stroke_color ?? '#111827',
              opacity: zone.opacity ?? 45,
              stroke_width: zone.stroke_width ?? 2,
            })),
          },
        },
      )

      history.replace(toDraft(result.zones))
      toast(t('saved'), 'success')
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('requestFailed'), 'error')
    } finally {
      setSaving(false)
    }
  }

  function exportJson() {
    const payload = {
      map,
      zones: history.zones,
    }
    const blob = new Blob([JSON.stringify(payload, null, 2)], { type: 'application/json' })
    const url = URL.createObjectURL(blob)
    const anchor = document.createElement('a')
    anchor.href = url
    anchor.download = `venue-${venueId}-map.json`
    anchor.click()
    URL.revokeObjectURL(url)
  }

  async function importJson(file: File) {
    try {
      const parsed = JSON.parse(await file.text()) as { zones?: Array<Record<string, unknown>> }
      if (!Array.isArray(parsed.zones)) {
        toast(t('venueMapImportInvalid'), 'error')
        return
      }
      history.replace(toDraft(parsed.zones))
      toast(t('venueMapImported'), 'success')
    } catch {
      toast(t('venueMapImportInvalid'), 'error')
    }
  }

  const tools: Array<{ id: EditorTool; icon: typeof MousePointer2; label: string }> = [
    { id: 'select', icon: MousePointer2, label: t('venueMapToolSelect') },
    { id: 'polygon', icon: Pentagon, label: t('venueMapToolPolygon') },
    { id: 'rectangle', icon: Square, label: t('venueMapToolRectangle') },
    { id: 'circle', icon: CircleIcon, label: t('venueMapToolCircle') },
    { id: 'delete', icon: Trash2, label: t('venueMapToolDelete') },
  ]

  return (
    <form className="venue-map-editor" onSubmit={(submitEvent) => void saveZones(submitEvent)}>
      <aside className="venue-map-editor__tools" aria-label={t('venueMapTools')}>
        {tools.map((item) => {
          const Icon = item.icon
          return (
            <button
              key={item.id}
              type="button"
              className={tool === item.id ? 'is-active' : undefined}
              title={item.label}
              aria-label={item.label}
              onClick={() => setTool(item.id)}
            >
              <Icon size={18} />
            </button>
          )
        })}
      </aside>

      <section className="venue-map-editor__stage">
        <div className="venue-map-editor__toolbar">
          <button type="button" className="button-secondary" disabled={!history.canUndo} onClick={history.undo}>
            <Undo2 size={16} />
            {t('venueMapUndo')}
          </button>
          <button type="button" className="button-secondary" disabled={!history.canRedo} onClick={history.redo}>
            <Redo2 size={16} />
            {t('venueMapRedo')}
          </button>
          <button type="button" className="button-secondary" onClick={() => fileRef.current?.click()}>
            <Upload size={16} />
            {uploading ? t('venueMapUploading') : t('venueMapUpload')}
          </button>
          <input
            ref={fileRef}
            type="file"
            accept="image/*"
            hidden
            onChange={(changeEvent) => {
              const file = changeEvent.target.files?.[0]
              if (file) void uploadMap(file)
              changeEvent.target.value = ''
            }}
          />
          <button type="button" className="button-secondary" onClick={exportJson}>
            {t('venueMapExport')}
          </button>
          <label className="button-secondary">
            {t('venueMapImport')}
            <input
              type="file"
              accept="application/json"
              hidden
              onChange={(changeEvent) => {
                const file = changeEvent.target.files?.[0]
                if (file) void importJson(file)
                changeEvent.target.value = ''
              }}
            />
          </label>
          {draftPoint ? (
            <span className="venue-map-editor__coords">
              x: {draftPoint.x.toFixed(3)}, y: {draftPoint.y.toFixed(3)}
            </span>
          ) : null}
          <div className="ms-auto flex gap-2">
            <span className="inline-flex items-center gap-1 text-sm text-[var(--muted)]">
              <ZoomIn size={14} /> / <ZoomOut size={14} />
              {t('venueMapZoomHint')}
            </span>
            <SubmitButtonWithLoader label={t('venueMapSave')} loading={saving} />
          </div>
        </div>

        {map?.image_url ? (
          <ResizableMapFrame
            storageKey={`venue-map-frame:${eventId}:${venueId}`}
            hint={t('venueMapResizeHint')}
          >
            <VenueMapCanvas
              imageUrl={map.image_url}
              naturalWidth={map.width ?? 1200}
              naturalHeight={map.height ?? 800}
              zones={history.zones}
              selectedKey={selectedKey}
              tool={tool}
              locale={locale}
              onSelect={setSelectedKey}
              onZonesChange={history.commit}
              onDraftPoint={setDraftPoint}
            />
          </ResizableMapFrame>
        ) : (
          <ResizableMapFrame
            storageKey={`venue-map-frame:${eventId}:${venueId}`}
            hint={t('venueMapResizeHint')}
          >
            <div className="venue-map-editor__empty">
              <p>{t('venueMapEmpty')}</p>
              <button type="button" className="button-primary" onClick={() => fileRef.current?.click()}>
                {t('venueMapUpload')}
              </button>
            </div>
          </ResizableMapFrame>
        )}
      </section>

      <aside className="venue-map-editor__sidebar">
        <div>
          <h2>{t('venueMapZones')}</h2>
          <p className="text-sm text-[var(--muted)]">{t('venueMapZonesHint')}</p>
          <ul className="venue-map-editor__zone-list">
            {history.zones.map((zone) => (
              <li key={zone.key} className="venue-map-editor__zone-row">
                <button
                  type="button"
                  className={zone.key === selectedKey ? 'is-active' : undefined}
                  onClick={() => setSelectedKey(zone.key)}
                >
                  <span
                    className="venue-map-editor__swatch"
                    style={{ background: zone.fill_color ?? defaultFillForType(zone.type) }}
                  />
                  <span className="venue-map-editor__zone-name">
                    {locale === 'ar'
                      ? (zone.zone_name_ar || zone.zone_name_en)
                      : (zone.zone_name_en || zone.zone_name_ar)}
                  </span>
                  {!zone.shape_type ? (
                    <em className="text-xs text-[var(--muted)]">{t('venueMapNoShape')}</em>
                  ) : null}
                </button>
                <button
                  type="button"
                  className="venue-map-editor__zone-delete"
                  title={t('delete')}
                  aria-label={t('delete')}
                  onClick={() => {
                    history.commit(history.zones.filter((row) => row.key !== zone.key))
                    if (selectedKey === zone.key) {
                      setSelectedKey(null)
                    }
                  }}
                >
                  <Trash2 size={14} />
                </button>
              </li>
            ))}
          </ul>
          <button
            type="button"
            className="button-secondary w-full"
            onClick={() => {
              const key = crypto.randomUUID()
              history.commit([
                ...history.zones,
                {
                  key,
                  zone_name_en: `Zone ${history.zones.length + 1}`,
                  zone_name_ar: `منطقة ${history.zones.length + 1}`,
                  type: zoneTypes[0] ?? 'hall',
                  capacity: null,
                  shape_type: null,
                  polygon_coordinates: null,
                  shape_radius: null,
                  label: `Zone ${history.zones.length + 1}`,
                  google_maps_url: null,
                  lat: venueLatitude,
                  lng: venueLongitude,
                  fill_color: defaultFillForType(zoneTypes[0] ?? 'hall'),
                  stroke_color: '#111827',
                  opacity: 45,
                  stroke_width: 2,
                },
              ])
              setSelectedKey(key)
            }}
          >
            {t('eventZonesAdd')}
          </button>
        </div>

        {selected ? (
          <div className="venue-map-editor__settings space-y-3">
            <h3>{t('venueMapSelectedSettings')}</h3>
            <TextInput
              label={t('eventZoneNameEn')}
              name="zone_name_en"
              value={selected.zone_name_en}
              onChange={(e) => updateSelected({ zone_name_en: e.target.value })}
              required
            />
            <TextInput
              label={t('eventZoneNameAr')}
              name="zone_name_ar"
              value={selected.zone_name_ar}
              onChange={(e) => updateSelected({ zone_name_ar: e.target.value })}
              required
            />
            <SelectInput
              label={t('eventZoneType')}
              name="zone_type"
              value={selected.type}
              onChange={(e) => updateSelected({
                type: e.target.value,
                fill_color: defaultFillForType(e.target.value),
              })}
              options={typeOptions}
            />
            <TextInput
              label={t('venueMapLabel')}
              name="label"
              value={selected.label ?? ''}
              onChange={(e) => updateSelected({ label: e.target.value || null })}
            />
            <TextInput
              label={t('venueMapFillColor')}
              name="fill_color"
              type="color"
              value={selected.fill_color ?? defaultFillForType(selected.type)}
              onChange={(e) => updateSelected({ fill_color: e.target.value })}
            />
            <TextInput
              label={t('venueMapOpacity')}
              name="opacity"
              type="number"
              min={0}
              max={100}
              value={String(selected.opacity ?? 45)}
              onChange={(e) => updateSelected({ opacity: Number(e.target.value) })}
            />
            <TextInput
              label={t('venueMapLat')}
              name="lat"
              value={selected.lat === null ? '' : String(selected.lat)}
              onChange={(e) => updateSelected({
                lat: e.target.value.trim() === '' ? null : Number(e.target.value),
              })}
            />
            <TextInput
              label={t('venueMapLng')}
              name="lng"
              value={selected.lng === null ? '' : String(selected.lng)}
              onChange={(e) => updateSelected({
                lng: e.target.value.trim() === '' ? null : Number(e.target.value),
              })}
            />
            <TextInput
              label={t('venueMapGoogleUrl')}
              name="google_maps_url"
              value={selected.google_maps_url ?? ''}
              onChange={(e) => updateSelected({ google_maps_url: e.target.value || null })}
            />
            <p className="text-xs text-[var(--muted)]">{t('venueMapNavHint')}</p>
            <button
              type="button"
              className="button-secondary w-full text-[var(--danger)]"
              onClick={() => {
                history.commit(history.zones.filter((zone) => zone.key !== selected.key))
                setSelectedKey(null)
              }}
            >
              {t('delete')}
            </button>
          </div>
        ) : null}
      </aside>
    </form>
  )
}
