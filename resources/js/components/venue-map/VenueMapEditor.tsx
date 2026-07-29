import { FormEvent, useEffect, useMemo, useRef, useState } from 'react'
import {
  Circle as CircleIcon,
  CircleEllipsis,
  Columns3,
  Hexagon,
  ImagePlus,
  ImageOff,
  MousePointer2,
  Pentagon,
  PersonStanding,
  Redo2,
  RotateCcw,
  RotateCw,
  Route,
  Square,
  Triangle,
  Trash2,
  Undo2,
  Upload,
  ZoomIn,
  ZoomOut,
} from 'lucide-react'
import { type VenueBaseMapType } from '@/components/venue-map/VenueMapBaseLayer'
import VenueMapGeoCanvas from '@/components/venue-map/VenueMapGeoCanvas'
import VenueMapPlaceSearch from '@/components/venue-map/VenueMapPlaceSearch'
import {
  boundsFromCamera,
  centroidGeo,
  insetOverlayBounds,
  isGeoPoint,
  isRelativePoint,
  relativePointsToGeo,
  relativeRadiusToMeters,
  rotateGeoPointsAround,
  type GeoPoint,
  type OverlayBounds,
} from '@/components/venue-map/geoCoordinates'
import ResizableMapFrame from '@/components/venue-map/ResizableMapFrame'
import { normalizeDegrees } from '@/components/venue-map/coordinates'
import {
  DEFAULT_PATH_COLOR,
  defaultFillForType,
  type EditorTool,
  type MapPath,
  type MapZone,
  type RelativePoint,
  type VenueMapData,
} from '@/components/venue-map/types'
import { useMapHistory } from '@/components/venue-map/useMapHistory'
import SelectInput from '@/components/forms/SelectInput'
import TextInput from '@/components/forms/TextInput'
import TextareaInput from '@/components/forms/TextareaInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import MapLocationPickerModal from '@/components/modals/MapLocationPickerModal'
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
  initialPaths?: Array<Record<string, unknown>>
  zoneTypes: string[]
}

function toDraft(zones: Array<Record<string, unknown>>): MapZone[] {
  return zones.map((zone) => ({
    key: String(zone.id ?? crypto.randomUUID()),
    id: zone.id ? String(zone.id) : undefined,
    zone_name_en: String(zone.zone_name_en ?? ''),
    zone_name_ar: String(zone.zone_name_ar ?? ''),
    description_en: zone.description_en ? String(zone.description_en) : null,
    description_ar: zone.description_ar ? String(zone.description_ar) : null,
    type: String(zone.type ?? 'hall'),
    floor_type: zone.floor_type === 'basement' || zone.floor_type === 'floor'
      ? zone.floor_type
      : null,
    floor_number: zone.floor_number === null || zone.floor_number === undefined
      ? null
      : Number(zone.floor_number),
    capacity: zone.capacity === null || zone.capacity === undefined ? null : Number(zone.capacity),
    shape_type: (zone.shape_type as MapZone['shape_type']) ?? null,
    coordinate_space: (zone.coordinate_space as MapZone['coordinate_space']) ?? undefined,
    polygon_coordinates: (zone.polygon_coordinates as MapZone['polygon_coordinates']) ?? null,
    shape_radius: zone.shape_radius === null || zone.shape_radius === undefined
      ? null
      : Number(zone.shape_radius),
    shape_rotation: zone.shape_rotation === null || zone.shape_rotation === undefined
      ? 0
      : Number(zone.shape_rotation),
    shape_radius_y: zone.shape_radius_y === null || zone.shape_radius_y === undefined
      ? null
      : Number(zone.shape_radius_y),
    label: zone.label ? String(zone.label) : null,
    google_maps_url: zone.google_maps_url ? String(zone.google_maps_url) : null,
    lat: zone.lat === null || zone.lat === undefined ? null : Number(zone.lat),
    lng: zone.lng === null || zone.lng === undefined ? null : Number(zone.lng),
    fill_color: zone.fill_color ? String(zone.fill_color) : null,
    fill_image_path: zone.fill_image_path ? String(zone.fill_image_path) : null,
    fill_image_url: zone.fill_image_url ? String(zone.fill_image_url) : null,
    stroke_color: zone.stroke_color ? String(zone.stroke_color) : null,
    opacity: zone.opacity === null || zone.opacity === undefined ? null : Number(zone.opacity),
    stroke_width: zone.stroke_width === null || zone.stroke_width === undefined
      ? null
      : Number(zone.stroke_width),
  }))
}

function toPathDraft(
  paths: Array<Record<string, unknown>>,
  zones: MapZone[],
): MapPath[] {
  const idToKey = new Map(zones.filter((zone) => zone.id).map((zone) => [zone.id!, zone.key]))

  return paths.map((path, index) => {
    const fromId = path.from_zone_id ? String(path.from_zone_id) : null
    const toId = path.to_zone_id ? String(path.to_zone_id) : null
    const nestedName = path.name && typeof path.name === 'object'
      ? path.name as { en?: string; ar?: string }
      : null

    return {
      key: String(path.id ?? crypto.randomUUID()),
      id: path.id ? String(path.id) : undefined,
      name_en: String(path.name_en ?? nestedName?.en ?? `Path ${index + 1}`),
      name_ar: String(path.name_ar ?? nestedName?.ar ?? `مسار ${index + 1}`),
      coordinate_space: (path.coordinate_space as MapPath['coordinate_space']) ?? undefined,
      polyline_coordinates: (path.polyline_coordinates as MapPath['polyline_coordinates']) ?? [],
      from_zone_key: fromId ? (idToKey.get(fromId) ?? null) : null,
      to_zone_key: toId ? (idToKey.get(toId) ?? null) : null,
      stroke_color: path.stroke_color ? String(path.stroke_color) : DEFAULT_PATH_COLOR,
      stroke_width: path.stroke_width === null || path.stroke_width === undefined
        ? 3
        : Number(path.stroke_width),
      opacity: path.opacity === null || path.opacity === undefined ? 85 : Number(path.opacity),
    }
  })
}

function convertDraftToGeo(
  zones: MapZone[],
  paths: MapPath[],
  bounds: OverlayBounds,
): { zones: MapZone[]; paths: MapPath[] } {
  return {
    zones: zones.map((zone) => {
      const points = zone.polygon_coordinates
      if (!points?.length || zone.coordinate_space === 'geo') {
        return { ...zone, coordinate_space: zone.coordinate_space ?? 'geo' }
      }
      if (!points.every(isRelativePoint)) {
        return { ...zone, coordinate_space: 'geo' }
      }
      const geoPoints = relativePointsToGeo(points, bounds)
      const isRadiusShape = zone.shape_type === 'circle'
        || zone.shape_type === 'ellipse'
        || zone.shape_type === 'pillar'
        || zone.shape_type === 'person'
      return {
        ...zone,
        coordinate_space: 'geo',
        polygon_coordinates: geoPoints,
        shape_radius: isRadiusShape && zone.shape_radius != null
          ? relativeRadiusToMeters(zone.shape_radius, bounds)
          : zone.shape_radius,
        shape_radius_y: isRadiusShape && zone.shape_radius_y != null
          ? relativeRadiusToMeters(zone.shape_radius_y, bounds)
          : zone.shape_radius_y,
      }
    }),
    paths: paths.map((path) => {
      if (path.coordinate_space === 'geo' || !path.polyline_coordinates.every(isRelativePoint)) {
        return { ...path, coordinate_space: path.coordinate_space ?? 'geo' }
      }
      return {
        ...path,
        coordinate_space: 'geo',
        polyline_coordinates: relativePointsToGeo(path.polyline_coordinates, bounds),
      }
    }),
  }
}

function buildZoneKeyToIdMap(before: MapZone[], after: Array<Record<string, unknown>>): Record<string, string> {
  const map: Record<string, string> = {}
  const usedIds = new Set<string>()

  for (const zone of before) {
    if (zone.id) {
      map[zone.key] = zone.id
      usedIds.add(zone.id)
    }
  }

  const unmatched = after.filter((zone) => zone.id && !usedIds.has(String(zone.id)))
  for (const zone of before) {
    if (map[zone.key]) continue
    const matchIndex = unmatched.findIndex((row) => (
      String(row.zone_name_en ?? '') === zone.zone_name_en
      && String(row.zone_name_ar ?? '') === zone.zone_name_ar
    ))
    if (matchIndex >= 0) {
      map[zone.key] = String(unmatched[matchIndex].id)
      unmatched.splice(matchIndex, 1)
    }
  }

  return map
}

export default function VenueMapEditor({
  eventId,
  tenantId,
  venueId,
  venueLatitude,
  venueLongitude,
  initialMap,
  initialZones,
  initialPaths = [],
  zoneTypes,
}: Props) {
  type Snapshot = { zones: MapZone[]; paths: MapPath[] }
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const fileRef = useRef<HTMLInputElement | null>(null)
  const fillImageFileRef = useRef<HTMLInputElement | null>(null)
  const [map, setMap] = useState<VenueMapData | null>(initialMap)
  const [tool, setTool] = useState<EditorTool>('select')
  const [selectedKey, setSelectedKey] = useState<string | null>(null)
  const [selectedPathKey, setSelectedPathKey] = useState<string | null>(null)
  const [draftPoint, setDraftPoint] = useState<GeoPoint | RelativePoint | null>(null)
  const [saving, setSaving] = useState(false)
  const [uploading, setUploading] = useState(false)
  const [uploadingFillImage, setUploadingFillImage] = useState(false)
  const [removingFillImage, setRemovingFillImage] = useState(false)
  const [fillMode, setFillMode] = useState<'color' | 'image'>('color')
  const [deletingImage, setDeletingImage] = useState(false)
  const [savingOverlay, setSavingOverlay] = useState(false)
  const [locationPickerOpen, setLocationPickerOpen] = useState(false)
  const [overlayOpacity, setOverlayOpacity] = useState(
    () => Math.round(((initialMap?.overlay_opacity ?? 0.85) * 100)),
  )
  const [removeBackground, setRemoveBackground] = useState(
    () => Boolean(initialMap?.remove_background ?? false),
  )
  const [showBaseMap, setShowBaseMap] = useState(
    () => Boolean(initialMap?.show_base_map ?? true),
  )
  const [baseMapType, setBaseMapType] = useState<VenueBaseMapType>(
    () => ((initialMap?.map_type as VenueBaseMapType | undefined) ?? 'hybrid'),
  )
  const [baseMapCenter, setBaseMapCenter] = useState<{ lat: number; lng: number } | null>(() => {
    if (initialMap?.map_center_lat != null && initialMap?.map_center_lng != null) {
      return { lat: Number(initialMap.map_center_lat), lng: Number(initialMap.map_center_lng) }
    }
    return venueLatitude != null && venueLongitude != null
      ? { lat: venueLatitude, lng: venueLongitude }
      : null
  })
  const [baseMapZoom, setBaseMapZoom] = useState(() => Number(initialMap?.map_zoom ?? 18))
  const [baseMapHeading, setBaseMapHeading] = useState(() => Number(initialMap?.map_heading ?? 0))
  const [overlayBounds, setOverlayBounds] = useState<OverlayBounds | null>(() => {
    if (
      initialMap?.overlay_north != null
      && initialMap?.overlay_south != null
      && initialMap?.overlay_east != null
      && initialMap?.overlay_west != null
    ) {
      return {
        north: Number(initialMap.overlay_north),
        south: Number(initialMap.overlay_south),
        east: Number(initialMap.overlay_east),
        west: Number(initialMap.overlay_west),
      }
    }
    return null
  })
  const [overlayRotation, setOverlayRotation] = useState(
    () => Number(initialMap?.overlay_rotation ?? 0),
  )
  const overlaySaveTimer = useRef<number | null>(null)
  const overlayReadyRef = useRef(false)
  const overlayBoundsSeededRef = useRef(
    initialMap?.overlay_north != null
    && initialMap?.overlay_south != null
    && initialMap?.overlay_east != null
    && initialMap?.overlay_west != null,
  )
  // Live camera for saves only — do not push every pan into React state (that re-centers the map).
  const mapCameraRef = useRef({
    center: baseMapCenter
      ?? (venueLatitude != null && venueLongitude != null
        ? { lat: venueLatitude, lng: venueLongitude }
        : null),
    zoom: Number(initialMap?.map_zoom ?? 18),
    heading: Number(initialMap?.map_heading ?? 0),
    mapType: ((initialMap?.map_type as VenueBaseMapType | undefined) ?? 'hybrid') as VenueBaseMapType,
  })
  const history = useMapHistory(toDraft(initialZones as unknown as Array<Record<string, unknown>>))
  const [paths, setPaths] = useState<MapPath[]>(() => (
    toPathDraft(initialPaths, initialZones)
  ))
  const [undoPast, setUndoPast] = useState<Snapshot[]>([])
  const [undoFuture, setUndoFuture] = useState<Snapshot[]>([])
  const zonesRef = useRef(history.zones)
  const pathsRef = useRef(paths)
  zonesRef.current = history.zones
  pathsRef.current = paths

  function commitSnapshot(next: Snapshot) {
    const current: Snapshot = { zones: zonesRef.current, paths: pathsRef.current }
    setUndoPast((stack) => [...stack.slice(-49), current])
    setUndoFuture([])
    history.replace(next.zones)
    setPaths(next.paths)
  }

  function commitZones(next: MapZone[]) {
    commitSnapshot({ zones: next, paths: pathsRef.current })
  }

  function commitPaths(next: MapPath[]) {
    commitSnapshot({ zones: zonesRef.current, paths: next })
  }

  function resetTimeline(next: Snapshot) {
    history.replace(next.zones)
    setPaths(next.paths)
    setUndoPast([])
    setUndoFuture([])
  }

  function undoAll() {
    setUndoPast((stack) => {
      if (stack.length === 0) return stack
      const previous = stack[stack.length - 1]
      const current: Snapshot = { zones: zonesRef.current, paths: pathsRef.current }
      setUndoFuture((future) => [current, ...future])
      history.replace(previous.zones)
      setPaths(previous.paths)
      setSelectedKey(null)
      setSelectedPathKey(null)
      return stack.slice(0, -1)
    })
  }

  function redoAll() {
    setUndoFuture((stack) => {
      if (stack.length === 0) return stack
      const [next, ...rest] = stack
      const current: Snapshot = { zones: zonesRef.current, paths: pathsRef.current }
      setUndoPast((past) => [...past, current])
      history.replace(next.zones)
      setPaths(next.paths)
      setSelectedKey(null)
      setSelectedPathKey(null)
      return rest
    })
  }

  const selected = history.zones.find((zone) => zone.key === selectedKey) ?? null
  const selectedPath = paths.find((path) => path.key === selectedPathKey) ?? null
  const hasFloorPlanImage = Boolean(map?.image_url && map.image_url.trim() !== '')
  const canShowBaseMap = venueLatitude != null && venueLongitude != null
  const resolvedBaseCenter = baseMapCenter
    ?? (canShowBaseMap
      ? { lat: venueLatitude as number, lng: venueLongitude as number }
      : null)
  const useGeoEditor = Boolean(resolvedBaseCenter)
  const migratedRef = useRef(false)

  useEffect(() => {
    if (!selected) {
      setFillMode('color')
      return
    }
    setFillMode(selected.fill_image_url ? 'image' : 'color')
    // Only reset the tab when switching zones — mode changes are handled by the toggle/upload handlers.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selected?.key])

  useEffect(() => {
    if (!useGeoEditor || migratedRef.current) return
    const bounds = overlayBounds
      ?? (resolvedBaseCenter
        ? boundsFromCamera(resolvedBaseCenter.lat, resolvedBaseCenter.lng, baseMapZoom)
        : null)
    if (!bounds) return

    const needsZoneConvert = history.zones.some((zone) => (
      Boolean(zone.polygon_coordinates?.length)
      && zone.coordinate_space !== 'geo'
      && (zone.polygon_coordinates?.every(isRelativePoint) ?? false)
    ))
    const needsPathConvert = paths.some((path) => (
      path.polyline_coordinates.length > 0
      && path.coordinate_space !== 'geo'
      && path.polyline_coordinates.every(isRelativePoint)
    ))

    if (!needsZoneConvert && !needsPathConvert) {
      migratedRef.current = true
      return
    }

    const converted = convertDraftToGeo(history.zones, paths, bounds)
    if (needsZoneConvert || needsPathConvert) {
      // eslint-disable-next-line react-hooks/set-state-in-effect
      resetTimeline({
        zones: needsZoneConvert ? converted.zones : history.zones,
        paths: needsPathConvert ? converted.paths : paths,
      })
    }
    if (!overlayBounds) {
      setOverlayBounds(insetOverlayBounds(bounds, 0.4))
      overlayBoundsSeededRef.current = true
    }
    migratedRef.current = true
    // One-time relative → geo conversion when camera/bounds are available.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [useGeoEditor, overlayBounds, resolvedBaseCenter, baseMapZoom])

  function scheduleOverlaySave() {
    if (overlaySaveTimer.current !== null) {
      window.clearTimeout(overlaySaveTimer.current)
    }
    overlaySaveTimer.current = window.setTimeout(() => {
      void saveOverlaySettings()
    }, 600)
  }

  useEffect(() => {
    if (!overlayReadyRef.current) {
      overlayReadyRef.current = true
      return
    }

    scheduleOverlaySave()

    return () => {
      if (overlaySaveTimer.current !== null) {
        window.clearTimeout(overlaySaveTimer.current)
      }
    }
  }, [
    overlayOpacity,
    removeBackground,
    showBaseMap,
    baseMapType,
    overlayBounds,
    overlayRotation,
  ])

  async function saveOverlaySettings() {
    setSavingOverlay(true)
    try {
      const camera = mapCameraRef.current
      const center = camera.center ?? resolvedBaseCenter
      const result = await apiFetch<{
        map: VenueMapData | null
      }>(`/api/v1/tenant/events/${eventId}/venues/${venueId}/map/settings`, {
        method: 'PATCH',
        tenantId,
        idempotency: true,
        body: {
          overlay_opacity: Math.min(1, Math.max(0, overlayOpacity / 100)),
          remove_background: removeBackground,
          show_base_map: showBaseMap,
          map_center_lat: center?.lat ?? null,
          map_center_lng: center?.lng ?? null,
          map_zoom: camera.zoom,
          map_heading: camera.heading,
          map_type: camera.mapType,
          overlay_north: overlayBounds?.north ?? null,
          overlay_south: overlayBounds?.south ?? null,
          overlay_east: overlayBounds?.east ?? null,
          overlay_west: overlayBounds?.west ?? null,
          overlay_rotation: overlayRotation,
        },
      })
      if (result.map) {
        const nextMap = result.map
        setMap((current) => ({
          ...(current ?? nextMap),
          ...nextMap,
          image_url: nextMap.image_url ?? current?.image_url ?? null,
        }))
      }
    } catch (caught) {
      if (import.meta.env.DEV) {
        console.warn('Venue map overlay settings save failed', caught)
      }
    } finally {
      setSavingOverlay(false)
    }
  }

  const typeOptions = useMemo(
    () => zoneTypes.map((type) => ({
      value: type,
      label: t(`eventZoneType_${type}` as 'eventZoneType_hall'),
    })),
    [zoneTypes, t],
  )

  const zoneLinkOptions = useMemo(
    () => [
      { value: '', label: t('venueMapPathNoZone') },
      ...history.zones.map((zone) => ({
        value: zone.key,
        label: locale === 'ar'
          ? (zone.zone_name_ar || zone.zone_name_en || zone.key)
          : (zone.zone_name_en || zone.zone_name_ar || zone.key),
      })),
    ],
    [history.zones, locale, t],
  )

  function updateSelected(patch: Partial<MapZone>) {
    if (!selected) return
    commitZones(history.zones.map((zone) => (
      zone.key === selected.key ? { ...zone, ...patch } : zone
    )))
  }

  async function uploadZoneFillImage(file: File) {
    if (!selected?.id) {
      toast(t('venueMapFillImageSaveFirst'), 'info')
      return
    }

    setUploadingFillImage(true)
    try {
      const body = new FormData()
      body.append('image', file)
      const result = await apiFetch<{ zone: Record<string, unknown> }>(
        `/api/v1/tenant/events/${eventId}/venues/${venueId}/zones/${selected.id}/fill-image`,
        { method: 'POST', tenantId, body },
      )
      updateSelected({
        fill_image_url: result.zone.fill_image_url
          ? String(result.zone.fill_image_url)
          : null,
        fill_image_path: result.zone.fill_image_path
          ? String(result.zone.fill_image_path)
          : null,
        fill_color: null,
      })
      setFillMode('image')
      toast(t('saved'), 'success')
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('requestFailed'), 'error')
    } finally {
      setUploadingFillImage(false)
    }
  }

  async function removeZoneFillImage() {
    if (!selected) return

    if (!selected.id || !selected.fill_image_url) {
      updateSelected({
        fill_image_url: null,
        fill_image_path: null,
        fill_color: selected.fill_color ?? defaultFillForType(selected.type),
      })
      setFillMode('color')
      return
    }

    setRemovingFillImage(true)
    try {
      const result = await apiFetch<{ zone: Record<string, unknown> }>(
        `/api/v1/tenant/events/${eventId}/venues/${venueId}/zones/${selected.id}/fill-image`,
        { method: 'DELETE', tenantId, idempotency: true },
      )
      updateSelected({
        fill_image_url: null,
        fill_image_path: null,
        fill_color: result.zone.fill_color
          ? String(result.zone.fill_color)
          : defaultFillForType(selected.type),
      })
      setFillMode('color')
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('requestFailed'), 'error')
    } finally {
      setRemovingFillImage(false)
    }
  }

  function switchFillMode(mode: 'color' | 'image') {
    if (!selected || mode === fillMode) return

    if (mode === 'image') {
      setFillMode('image')
      return
    }

    setFillMode('color')
    if (selected.fill_image_url) {
      void removeZoneFillImage()
    }
  }

  function openFillImagePicker() {
    if (!selected) {
      toast(t('venueMapFillImageSelectZone'), 'info')
      return
    }
    if (!selected.id) {
      toast(t('venueMapFillImageSaveFirst'), 'info')
      return
    }
    setFillMode('image')
    fillImageFileRef.current?.click()
  }

  function updateSelectedPath(patch: Partial<MapPath>) {
    if (!selectedPath) return
    commitPaths(pathsRef.current.map((path) => (
      path.key === selectedPath.key ? { ...path, ...patch } : path
    )))
  }

  function selectZone(key: string | null) {
    setSelectedKey(key)
    if (key !== null) {
      setSelectedPathKey(null)
    }
  }

  function toggleSelectZone(key: string) {
    setSelectedKey((current) => (current === key ? null : key))
    setSelectedPathKey(null)
  }

  function selectPath(key: string | null) {
    setSelectedPathKey(key)
    if (key !== null) {
      setSelectedKey(null)
      if (tool === 'path') setTool('select')
    }
  }

  async function uploadMap(file: File) {
    setUploading(true)
    try {
      const body = new FormData()
      body.append('image', file)

      const image = await createImageBitmap(file).catch(() => null)
      let aspect = 1.6
      if (image) {
        body.append('width', String(image.width))
        body.append('height', String(image.height))
        aspect = image.width / Math.max(1, image.height)
        image.close()
      }

      const camera = mapCameraRef.current
      const center = camera.center ?? resolvedBaseCenter
      const zoom = camera.zoom

      // Always attach the uploaded image to the position you're currently viewing.
      // If the overlay bounds were modified/resolved earlier, we preserve overlay size
      // but translate it so its center matches the current camera center.
      let bounds: OverlayBounds | null = null
      if (center) {
        if (overlayBounds) {
          const oldCenterLat = (overlayBounds.north + overlayBounds.south) / 2
          const oldCenterLng = (overlayBounds.east + overlayBounds.west) / 2
          const halfLat = overlayBounds.north - oldCenterLat
          const halfLng = overlayBounds.east - oldCenterLng

          bounds = {
            north: center.lat + halfLat,
            south: center.lat - halfLat,
            east: center.lng + halfLng,
            west: center.lng - halfLng,
          }
        } else {
          bounds = insetOverlayBounds(boundsFromCamera(center.lat, center.lng, zoom, aspect), 0.4)
        }
      }

      if (center) {
        body.append('map_center_lat', String(center.lat))
        body.append('map_center_lng', String(center.lng))
        body.append('map_zoom', String(zoom))
        body.append('map_heading', String(camera.heading))
        body.append('map_type', camera.mapType)
      }
      if (bounds) {
        body.append('overlay_north', String(bounds.north))
        body.append('overlay_south', String(bounds.south))
        body.append('overlay_east', String(bounds.east))
        body.append('overlay_west', String(bounds.west))
      }
      body.append('overlay_opacity', String(Math.min(1, Math.max(0, overlayOpacity / 100))))
      body.append('remove_background', removeBackground ? '1' : '0')
      body.append('show_base_map', showBaseMap ? '1' : '0')

      const result = await apiFetch<{
        map: VenueMapData | null
        zones: Array<Record<string, unknown>>
      }>(`/api/v1/tenant/events/${eventId}/venues/${venueId}/map`, {
        method: 'POST',
        tenantId,
        body,
      })

      setMap(result.map)
      if (result.map) {
        setOverlayOpacity(Math.round((result.map.overlay_opacity ?? 0.85) * 100))
        setRemoveBackground(Boolean(result.map.remove_background ?? false))
        setShowBaseMap(Boolean(result.map.show_base_map ?? true))
        if (result.map.map_center_lat != null && result.map.map_center_lng != null) {
          setBaseMapCenter({
            lat: Number(result.map.map_center_lat),
            lng: Number(result.map.map_center_lng),
          })
        }
        if (result.map.map_zoom != null) setBaseMapZoom(Number(result.map.map_zoom))
        if (result.map.map_heading != null) setBaseMapHeading(Number(result.map.map_heading))
        if (result.map.map_type) setBaseMapType(result.map.map_type as VenueBaseMapType)
        if (result.map.overlay_rotation != null) {
          setOverlayRotation(Number(result.map.overlay_rotation))
        }
        if (
          result.map.overlay_north != null
          && result.map.overlay_south != null
          && result.map.overlay_east != null
          && result.map.overlay_west != null
        ) {
          setOverlayBounds({
            north: Number(result.map.overlay_north),
            south: Number(result.map.overlay_south),
            east: Number(result.map.overlay_east),
            west: Number(result.map.overlay_west),
          })
          overlayBoundsSeededRef.current = true
        } else if (bounds) {
          setOverlayBounds(bounds)
          overlayBoundsSeededRef.current = true
        }
      }
      // Keep existing drawn shapes; upload only adds the floor-plan overlay.
      if (history.zones.length === 0 && result.zones.length > 0) {
        resetTimeline({
          zones: toDraft(result.zones),
          paths: pathsRef.current,
        })
      }
      toast(t('venueMapUploaded'), 'success')
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('requestFailed'), 'error')
    } finally {
      setUploading(false)
    }
  }

  function recenterToUploadedMap() {
    // Use the actual overlay bounds center (where the image sits on the map),
    // not map_center_lat/lng which is the camera position at upload time.
    let center: { lat: number; lng: number } | null = null

    if (overlayBounds) {
      center = {
        lat: (overlayBounds.north + overlayBounds.south) / 2,
        lng: (overlayBounds.east + overlayBounds.west) / 2,
      }
    } else if (map?.map_center_lat != null && map?.map_center_lng != null) {
      center = { lat: Number(map.map_center_lat), lng: Number(map.map_center_lng) }
    }

    if (!center) return

    // Calculate zoom level that fits the overlay bounds nicely
    let nextZoom = map?.map_zoom != null ? Number(map.map_zoom) : baseMapZoom
    if (overlayBounds) {
      const latSpan = overlayBounds.north - overlayBounds.south
      if (latSpan > 0) {
        const fitZoom = Math.log2(180 / latSpan) + 1
        nextZoom = Math.min(Math.max(Math.round(fitZoom), 14), 21)
      }
    }

    const nextHeading = map?.map_heading != null ? Number(map.map_heading) : baseMapHeading
    const nextType = (map?.map_type != null ? map.map_type : baseMapType) as VenueBaseMapType

    mapCameraRef.current = {
      ...mapCameraRef.current,
      center,
      zoom: nextZoom,
      heading: nextHeading,
      mapType: nextType,
    }

    // Force React to re-render even if the target center is the same as current state
    // by briefly nulling the center, then restoring in the next frame.
    setBaseMapCenter(null)
    requestAnimationFrame(() => {
      setBaseMapCenter(center)
      setBaseMapZoom(nextZoom)
      setBaseMapHeading(nextHeading)
      setBaseMapType(nextType)
    })
  }

  async function deleteFloorPlanImage() {
    if (!hasFloorPlanImage || deletingImage) return
    if (!window.confirm(t('venueMapDeleteImageConfirm'))) return

    setDeletingImage(true)
    try {
      const result = await apiFetch<{
        map: VenueMapData | null
      }>(`/api/v1/tenant/events/${eventId}/venues/${venueId}/map`, {
        method: 'DELETE',
        tenantId,
        idempotency: true,
      })

      setMap(result.map)
      setRemoveBackground(false)
      setOverlayOpacity(Math.round(((result.map?.overlay_opacity ?? 0.85) * 100)))
      toast(t('venueMapImageDeleted'), 'success')
    } catch (caught) {
      toast(caught instanceof ApiFetchError ? caught.message : t('requestFailed'), 'error')
    } finally {
      setDeletingImage(false)
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

      const incompletePaths = paths.find((path) => path.polyline_coordinates.length < 2)
      if (incompletePaths) {
        toast(t('venueMapPathIncomplete'), 'error')
        return
      }

      await saveOverlaySettings()

      const zonesBeforeSave = history.zones
      const result = await apiFetch<{ zones: Array<Record<string, unknown>> }>(
        `/api/v1/tenant/events/${eventId}/zones`,
        {
          method: 'PUT',
          tenantId,
          idempotency: true,
          body: {
            venue_id: Number(venueId),
            zones: history.zones.map((zone) => {
              const rawPoints = (zone.polygon_coordinates ?? [])
                .filter((point): point is GeoPoint => 'lat' in point && 'lng' in point)
                .map((point) => ({ lat: Number(point.lat), lng: Number(point.lng) }))
              const polygon_coordinates = rawPoints.length >= 2
                && Math.abs(rawPoints[0].lat - rawPoints[rawPoints.length - 1].lat) < 1e-9
                && Math.abs(rawPoints[0].lng - rawPoints[rawPoints.length - 1].lng) < 1e-9
                ? rawPoints.slice(0, -1)
                : (zone.polygon_coordinates ?? null)
              const pointCount = Array.isArray(polygon_coordinates) ? polygon_coordinates.length : 0
              const expected: Record<string, number> = {
                rectangle: 4,
                triangle: 3,
                hexagon: 6,
                circle: 1,
                ellipse: 1,
                pillar: 1,
                person: 1,
              }
              const need = zone.shape_type ? expected[zone.shape_type] : undefined
              const shape_type = need != null && pointCount !== need && pointCount >= 3
                ? 'polygon'
                : zone.shape_type

              return {
                id: zone.id ? Number(zone.id) : undefined,
                zone_name_en: zone.zone_name_en.trim(),
                zone_name_ar: zone.zone_name_ar.trim(),
                description_en: zone.description_en?.trim() || null,
                description_ar: zone.description_ar?.trim() || null,
                type: zone.type,
                floor_type: zone.floor_type,
                floor_number: zone.floor_type === 'floor' ? zone.floor_number : null,
                capacity: zone.capacity,
                shape_type,
                coordinate_space: zone.coordinate_space ?? 'geo',
                polygon_coordinates,
                shape_radius: zone.shape_radius,
                shape_rotation: zone.shape_rotation ?? 0,
                shape_radius_y: zone.shape_radius_y,
                label: zone.label,
                google_maps_url: zone.google_maps_url,
                lat: zone.lat,
                lng: zone.lng,
                fill_color: zone.fill_image_url
                  ? null
                  : (zone.fill_color ?? defaultFillForType(zone.type)),
                stroke_color: zone.stroke_color ?? '#111827',
                opacity: zone.opacity ?? 45,
                stroke_width: zone.stroke_width ?? 2,
              }
            }),
          },
        },
      )

      const savedZones = toDraft(result.zones)
      const keyToId = buildZoneKeyToIdMap(zonesBeforeSave, result.zones)
      commitZones(savedZones)

      const pathResult = await apiFetch<{ paths: Array<Record<string, unknown>> }>(
        `/api/v1/tenant/events/${eventId}/paths`,
        {
          method: 'PUT',
          tenantId,
          idempotency: true,
          body: {
            venue_id: Number(venueId),
            paths: paths.map((path, index) => ({
              id: path.id ? Number(path.id) : undefined,
              name_en: path.name_en.trim() || null,
              name_ar: path.name_ar.trim() || null,
              coordinate_space: path.coordinate_space ?? 'geo',
              polyline_coordinates: path.polyline_coordinates,
              from_zone_id: path.from_zone_key && keyToId[path.from_zone_key]
                ? Number(keyToId[path.from_zone_key])
                : null,
              to_zone_id: path.to_zone_key && keyToId[path.to_zone_key]
                ? Number(keyToId[path.to_zone_key])
                : null,
              stroke_color: path.stroke_color ?? DEFAULT_PATH_COLOR,
              stroke_width: path.stroke_width ?? 3,
              opacity: path.opacity ?? 85,
              sort_order: index,
            })),
          },
        },
      )

      commitPaths(toPathDraft(pathResult.paths, savedZones))
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
      paths,
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
      const parsed = JSON.parse(await file.text()) as {
        zones?: Array<Record<string, unknown>>
        paths?: Array<Record<string, unknown>>
      }
      if (!Array.isArray(parsed.zones)) {
        toast(t('venueMapImportInvalid'), 'error')
        return
      }
      const nextZones = toDraft(parsed.zones)
      commitSnapshot({
        zones: nextZones,
        paths: toPathDraft(Array.isArray(parsed.paths) ? parsed.paths : [], nextZones),
      })
      toast(t('venueMapImported'), 'success')
    } catch {
      toast(t('venueMapImportInvalid'), 'error')
    }
  }

  const tools: Array<{ id: EditorTool; icon: typeof MousePointer2; label: string }> = [
    { id: 'select', icon: MousePointer2, label: t('venueMapToolSelect') },
    { id: 'polygon', icon: Pentagon, label: t('venueMapToolPolygon') },
    { id: 'rectangle', icon: Square, label: t('venueMapToolRectangle') },
    { id: 'triangle', icon: Triangle, label: t('venueMapToolTriangle') },
    { id: 'hexagon', icon: Hexagon, label: t('venueMapToolHexagon') },
    { id: 'circle', icon: CircleIcon, label: t('venueMapToolCircle') },
    { id: 'ellipse', icon: CircleEllipsis, label: t('venueMapToolEllipse') },
    { id: 'pillar', icon: Columns3, label: t('venueMapToolPillar') },
    { id: 'person', icon: PersonStanding, label: t('venueMapToolPerson') },
    { id: 'path', icon: Route, label: t('venueMapToolPath') },
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
          <button type="button" className="button-secondary" disabled={undoPast.length === 0} onClick={undoAll}>
            <Undo2 size={16} />
            {t('venueMapUndo')}
          </button>
          <button type="button" className="button-secondary" disabled={undoFuture.length === 0} onClick={redoAll}>
            <Redo2 size={16} />
            {t('venueMapRedo')}
          </button>
          <button
            type="button"
            className="button-secondary text-[var(--danger)]"
            title={t('venueMapDeleteFillImage')}
            disabled={!selected?.fill_image_url || removingFillImage || uploadingFillImage}
            onClick={() => void removeZoneFillImage()}
          >
            <ImageOff size={16} />
            {removingFillImage ? t('venueMapRemovingFillImage') : t('venueMapDeleteFillImage')}
          </button>
          <button
            type="button"
            className="button-secondary"
            title={selected?.id ? t('venueMapUploadFillImage') : t('venueMapFillImageSaveFirst')}
            disabled={!selected || uploadingFillImage}
            onClick={openFillImagePicker}
          >
            <ImagePlus size={16} />
            {uploadingFillImage ? t('venueMapUploadingFillImage') : t('venueMapUploadFillImage')}
          </button>
          <input
            ref={fillImageFileRef}
            type="file"
            accept="image/*"
            hidden
            onChange={(changeEvent) => {
              const file = changeEvent.target.files?.[0]
              changeEvent.target.value = ''
              if (file) void uploadZoneFillImage(file)
            }}
          />
          {selected?.shape_type ? (
            <button
              type="button"
              className="button-secondary text-[var(--danger)]"
              title={t('venueMapClearShape')}
              onClick={() => {
                updateSelected({
                  shape_type: null,
                  polygon_coordinates: null,
                  shape_radius: null,
                  shape_radius_y: null,
                  shape_rotation: 0,
                })
              }}
            >
              <Trash2 size={16} />
              {t('venueMapDeleteShape')}
            </button>
          ) : null}
          {selectedPath ? (
            <button
              type="button"
              className="button-secondary text-[var(--danger)]"
              title={t('venueMapDeletePath')}
              onClick={() => {
                commitPaths(pathsRef.current.filter((path) => path.key !== selectedPath.key))
                setSelectedPathKey(null)
              }}
            >
              <Trash2 size={16} />
              {t('venueMapDeletePath')}
            </button>
          ) : null}
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
          {draftPoint && isGeoPoint(draftPoint) ? (
            <span className="venue-map-editor__coords">
              lat: {draftPoint.lat.toFixed(5)}, lng: {draftPoint.lng.toFixed(5)}
            </span>
          ) : draftPoint && 'x' in draftPoint ? (
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

        {useGeoEditor && resolvedBaseCenter ? (
          <VenueMapPlaceSearch
            onPlaceResolved={(place) => {
              const next = { lat: place.latitude, lng: place.longitude }
              mapCameraRef.current = {
                ...mapCameraRef.current,
                center: next,
                zoom: 18,
              }
              setBaseMapCenter(next)
              setBaseMapZoom(18)
            }}
          />
        ) : null}

        {useGeoEditor && resolvedBaseCenter ? (
          <ResizableMapFrame
            storageKey={`venue-map-frame:${eventId}:${venueId}`}
            hint={t('venueMapResizeHint')}
          >
            <VenueMapGeoCanvas
              latitude={resolvedBaseCenter.lat}
              longitude={resolvedBaseCenter.lng}
              zoom={baseMapZoom}
              heading={baseMapHeading}
              mapTypeId={baseMapType}
              imageUrl={map?.image_url ?? null}
              overlayBounds={overlayBounds}
              overlayOpacity={overlayOpacity / 100}
              overlayRotation={overlayRotation}
              removeBackground={removeBackground}
              zones={history.zones}
              paths={paths}
              selectedKey={selectedKey}
              selectedPathKey={selectedPathKey}
              tool={tool}
              onSelect={selectZone}
              onSelectPath={selectPath}
              onZonesChange={commitZones}
              onPathsChange={commitPaths}
              onOverlayRotationChange={setOverlayRotation}
              onOverlayBoundsChange={(bounds) => {
                overlayBoundsSeededRef.current = true
                setOverlayBounds(bounds)
              }}
              onCameraChange={(camera) => {
                mapCameraRef.current = {
                  center: camera.center,
                  zoom: camera.zoom,
                  heading: camera.heading,
                  mapType: camera.mapTypeId,
                }
                // Map-type control lives in React options; avoid re-centering on every pan.
                if (camera.mapTypeId !== baseMapType) {
                  setBaseMapType(camera.mapTypeId)
                }
                // Seed geo-fixed overlay once — never retarget it to the viewport while panning.
                if (!overlayBoundsSeededRef.current) {
                  overlayBoundsSeededRef.current = true
                  setOverlayBounds(insetOverlayBounds(camera.bounds, 0.4))
                }
                scheduleOverlaySave()
              }}
              onDraftPoint={setDraftPoint}
            />
          </ResizableMapFrame>
        ) : (
          <ResizableMapFrame
            storageKey={`venue-map-frame:${eventId}:${venueId}`}
            hint={t('venueMapResizeHint')}
          >
            <div className="venue-map-editor__empty">
              <div className="venue-map-editor__empty-content">
                <p>{t('venueMapEmpty')}</p>
                <p className="text-sm text-[var(--muted)]">{t('venueMapBaseMapNeedsCoords')}</p>
              </div>
            </div>
          </ResizableMapFrame>
        )}
      </section>

      <aside className="venue-map-editor__sidebar">
        <div className="venue-map-editor__overlay-settings">
          <h2>{t('venueMapOverlayTitle')}</h2>

          <label className="venue-map-editor__check">
            <input
              type="checkbox"
              checked={showBaseMap}
              disabled={!canShowBaseMap}
              onChange={(event) => setShowBaseMap(event.target.checked)}
            />
            <span>{t('venueMapShowBaseMap')}</span>
          </label>
          {!canShowBaseMap ? (
            <p className="text-xs text-[var(--muted)]">{t('venueMapBaseMapNeedsCoords')}</p>
          ) : null}

          {hasFloorPlanImage ? (
            <>
              <label className="venue-map-editor__range">
                <span>
                  {t('venueMapFloorOpacity')}
                  {savingOverlay ? ` · ${t('venueMapOverlaySaving')}` : ''}
                </span>
                <input
                  type="range"
                  min={10}
                  max={100}
                  step={1}
                  value={overlayOpacity}
                  onChange={(event) => setOverlayOpacity(Number(event.target.value))}
                />
                <em>{overlayOpacity}%</em>
              </label>

              <label className="venue-map-editor__check">
                <input
                  type="checkbox"
                  checked={removeBackground}
                  onChange={(event) => setRemoveBackground(event.target.checked)}
                />
                <span>{t('venueMapRemoveBackground')}</span>
              </label>
              <button
                type="button"
                className="button-secondary inline-flex w-full items-center justify-center gap-2"
                disabled={!overlayBounds && (map?.map_center_lat == null || map?.map_center_lng == null)}
                onClick={() => recenterToUploadedMap()}
              >
                <Route size={16} />
                {t('venueMapCurrentImage')}
              </button>
              <button
                type="button"
                className="button-secondary inline-flex w-full items-center justify-center gap-2"
                disabled={deletingImage}
                onClick={() => void deleteFloorPlanImage()}
              >
                <Trash2 size={16} />
                {deletingImage ? t('venueMapDeletingImage') : t('venueMapDeleteImage')}
              </button>
            </>
          ) : null}
        </div>

        <div className="venue-map-editor__sidebar-scroll">
        <div>
          <h2>{t('venueMapZones')}</h2>
          <p className="text-sm text-[var(--muted)]">{t('venueMapZonesHint')}</p>
          <ul className="venue-map-editor__zone-list">
            {history.zones.map((zone) => (
              <li key={zone.key} className="venue-map-editor__zone-row">
                <button
                  type="button"
                  className={zone.key === selectedKey ? 'is-active' : undefined}
                  onClick={() => toggleSelectZone(zone.key)}
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
                    commitZones(history.zones.filter((row) => row.key !== zone.key))
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
              commitZones([
                ...history.zones,
                {
                  key,
                  zone_name_en: `Zone ${history.zones.length + 1}`,
                  zone_name_ar: `منطقة ${history.zones.length + 1}`,
                  description_en: null,
                  description_ar: null,
                  type: zoneTypes[0] ?? 'hall',
                  floor_type: null,
                  floor_number: null,
                  capacity: null,
                  coordinate_space: 'geo',
                  shape_type: null,
                  polygon_coordinates: null,
                  shape_radius: null,
                  shape_rotation: 0,
                  shape_radius_y: null,
                  label: `Zone ${history.zones.length + 1}`,
                  google_maps_url: null,
                  lat: venueLatitude,
                  lng: venueLongitude,
                  fill_color: defaultFillForType(zoneTypes[0] ?? 'hall'),
                  fill_image_path: null,
                  fill_image_url: null,
                  stroke_color: '#111827',
                  opacity: 45,
                  stroke_width: 2,
                },
              ])
              setSelectedKey(key)
              setSelectedPathKey(null)
            }}
          >
            {t('eventZonesAdd')}
          </button>
        </div>
        {
            paths.length > 0 ? (
                <>
                    <h2>{t('venueMapPaths')}</h2>
                    <p className="text-sm text-[var(--muted)]">{t('venueMapPathsHint')}</p>
                    {paths.map((path) => (
                        <div key={path.key}>
                            <ul className="venue-map-editor__zone-list" style={{ margin: '0' }}>
                                <li className="venue-map-editor__zone-row">
                                    <button
                                    type="button"
                                    className={path.key === selectedPathKey ? 'is-active' : undefined}
                                    onClick={() => {
                                        setSelectedPathKey(path.key)
                                        setSelectedKey(null)
                                    }}
                                    >
                                    <span
                                        className="venue-map-editor__swatch"
                                        style={{ background: path.stroke_color ?? DEFAULT_PATH_COLOR }}
                                    />
                                    <span className="venue-map-editor__zone-name">
                                        {locale === 'ar'
                                        ? (path.name_ar || path.name_en)
                                        : (path.name_en || path.name_ar)}
                                    </span>
                                    </button>
                                    <button
                                    type="button"
                                    className="venue-map-editor__zone-delete"
                                    title={t('delete')}
                                    aria-label={t('delete')}
                                    onClick={() => {
                                        commitPaths(pathsRef.current.filter((row) => row.key !== path.key))
                                        if (selectedPathKey === path.key) setSelectedPathKey(null)
                                    }}
                                    >
                                    <Trash2 size={14} />
                                    </button>
                                </li>
                            </ul>
                        </div>
                    ))}
                </>
            ) : null
        }

        {selectedPath ? (
          <div className="venue-map-editor__settings space-y-3">
            <h3>{t('venueMapSelectedPath')}</h3>
            <TextInput
              label={t('venueMapPathNameEn')}
              name="path_name_en"
              value={selectedPath.name_en}
              onChange={(e) => updateSelectedPath({ name_en: e.target.value })}
            />
            <TextInput
              label={t('venueMapPathNameAr')}
              name="path_name_ar"
              value={selectedPath.name_ar}
              onChange={(e) => updateSelectedPath({ name_ar: e.target.value })}
            />
            <SelectInput
              label={t('venueMapPathFromZone')}
              name="from_zone_key"
              value={selectedPath.from_zone_key ?? ''}
              onChange={(e) => updateSelectedPath({ from_zone_key: e.target.value || null })}
              options={zoneLinkOptions}
            />
            <SelectInput
              label={t('venueMapPathToZone')}
              name="to_zone_key"
              value={selectedPath.to_zone_key ?? ''}
              onChange={(e) => updateSelectedPath({ to_zone_key: e.target.value || null })}
              options={zoneLinkOptions}
            />
            <TextInput
              label={t('venueMapFillColor')}
              name="path_stroke_color"
              type="color"
              value={selectedPath.stroke_color ?? DEFAULT_PATH_COLOR}
              onChange={(e) => updateSelectedPath({ stroke_color: e.target.value })}
            />
            <TextInput
              label={t('venueMapOpacity')}
              name="path_opacity"
              type="number"
              min={0}
              max={100}
              value={String(selectedPath.opacity ?? 85)}
              onChange={(e) => updateSelectedPath({ opacity: Number(e.target.value) })}
            />
            <button
              type="button"
              className="button-secondary w-full text-[var(--danger)]"
              onClick={() => {
                commitPaths(pathsRef.current.filter((path) => path.key !== selectedPath.key))
                setSelectedPathKey(null)
              }}
            >
              {t('delete')}
            </button>
          </div>
        ) : null}

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
            <TextareaInput
              label={t('eventZoneDescriptionEn')}
              name="description_en"
              rows={3}
              className="min-h-20"
              value={selected.description_en ?? ''}
              onChange={(e) => updateSelected({ description_en: e.target.value || null })}
            />
            <TextareaInput
              label={t('eventZoneDescriptionAr')}
              name="description_ar"
              rows={3}
              className="min-h-20"
              value={selected.description_ar ?? ''}
              onChange={(e) => updateSelected({ description_ar: e.target.value || null })}
            />
            <SelectInput
              label={t('eventZoneType')}
              name="zone_type"
              value={selected.type}
              onChange={(e) => updateSelected({
                type: e.target.value,
                fill_color: selected.fill_image_url
                  ? selected.fill_color
                  : defaultFillForType(e.target.value),
              })}
              options={typeOptions}
            />
            <fieldset className="space-y-2">
              <legend className="text-sm font-medium text-[var(--ink)]">{t('eventZoneFloorType')}</legend>
              <label className="venue-map-editor__check">
                <input
                  type="radio"
                  name="floor_type"
                  checked={selected.floor_type === 'basement'}
                  onChange={() => updateSelected({ floor_type: 'basement', floor_number: null })}
                />
                <span>{t('eventZoneFloorType_basement')}</span>
              </label>
              <label className="venue-map-editor__check">
                <input
                  type="radio"
                  name="floor_type"
                  checked={selected.floor_type === 'floor'}
                  onChange={() => updateSelected({
                    floor_type: 'floor',
                    floor_number: selected.floor_number ?? 1,
                  })}
                />
                <span>{t('eventZoneFloorType_floor')}</span>
              </label>
            </fieldset>
            {selected.floor_type === 'floor' ? (
              <TextInput
                label={t('eventZoneFloorNumber')}
                name="floor_number"
                type="number"
                min={0}
                max={500}
                value={String(selected.floor_number ?? 1)}
                onChange={(e) => updateSelected({
                  floor_number: e.target.value.trim() === '' ? null : Number(e.target.value),
                })}
              />
            ) : null}
            <TextInput
              label={t('venueMapLabel')}
              name="label"
              value={selected.label ?? ''}
              onChange={(e) => updateSelected({ label: e.target.value || null })}
            />
            <fieldset className="venue-map-editor__fill-panel">
              <legend className="venue-map-editor__fill-legend">{t('venueMapFillMode')}</legend>
              <div className="venue-map-editor__fill-tabs" role="tablist" aria-label={t('venueMapFillMode')}>
                <button
                  type="button"
                  role="tab"
                  aria-selected={fillMode === 'color'}
                  className={fillMode === 'color' ? 'is-active' : undefined}
                  disabled={removingFillImage || uploadingFillImage}
                  onClick={() => switchFillMode('color')}
                >
                  {t('venueMapFillModeColor')}
                </button>
                <button
                  type="button"
                  role="tab"
                  aria-selected={fillMode === 'image'}
                  className={fillMode === 'image' ? 'is-active' : undefined}
                  disabled={removingFillImage || uploadingFillImage}
                  onClick={() => switchFillMode('image')}
                >
                  {t('venueMapFillModeImage')}
                </button>
              </div>

              {fillMode === 'color' ? (
                <div className="venue-map-editor__fill-color">
                  <TextInput
                    label={t('venueMapFillColor')}
                    name="fill_color"
                    type="color"
                    value={selected.fill_color ?? defaultFillForType(selected.type)}
                    onChange={(e) => updateSelected({
                      fill_color: e.target.value,
                      fill_image_url: null,
                      fill_image_path: null,
                    })}
                  />
                </div>
              ) : selected.fill_image_url ? (
                <div className="venue-map-editor__fill-preview">
                  <div className="venue-map-editor__fill-preview-frame">
                    <img src={selected.fill_image_url} alt="" />
                  </div>
                  <div className="venue-map-editor__fill-preview-actions">
                    <button
                      type="button"
                      className="button-secondary"
                      disabled={uploadingFillImage || removingFillImage}
                      onClick={openFillImagePicker}
                    >
                      <ImagePlus size={14} />
                      {t('venueMapReplaceFillImage')}
                    </button>
                    <button
                      type="button"
                      className="button-secondary text-[var(--danger)]"
                      disabled={uploadingFillImage || removingFillImage}
                      onClick={() => void removeZoneFillImage()}
                    >
                      <Trash2 size={14} />
                      {removingFillImage ? t('venueMapRemovingFillImage') : t('venueMapRemoveFillImage')}
                    </button>
                  </div>
                </div>
              ) : (
                <button
                  type="button"
                  className="venue-map-editor__fill-dropzone"
                  disabled={!selected.id || uploadingFillImage}
                  onClick={openFillImagePicker}
                >
                  <span className="venue-map-editor__fill-dropzone-icon" aria-hidden>
                    <ImagePlus size={20} />
                  </span>
                  <span className="venue-map-editor__fill-dropzone-title">
                    {uploadingFillImage ? t('venueMapUploadingFillImage') : t('venueMapUploadFillImage')}
                  </span>
                  <span className="venue-map-editor__fill-dropzone-hint">
                    {selected.id ? t('venueMapFillImageHint') : t('venueMapFillImageSaveFirst')}
                  </span>
                </button>
              )}
            </fieldset>
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
              label={t('venueMapGoogleUrl')}
              name="google_maps_url"
              value={selected.google_maps_url ?? ''}
              onChange={(e) => updateSelected({ google_maps_url: e.target.value || null })}
            />
            <button
              type="button"
              className="button-secondary w-full"
              onClick={() => setLocationPickerOpen(true)}
            >
              {t('venueMapPickLocation')}
            </button>
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
            <MapLocationPickerModal
              open={locationPickerOpen}
              latitude={selected.lat}
              longitude={selected.lng}
              onCancel={() => setLocationPickerOpen(false)}
              onSave={(nextLat, nextLng) => {
                updateSelected({ lat: nextLat, lng: nextLng })
                setLocationPickerOpen(false)
              }}
            />
            <p className="text-xs text-[var(--muted)]">{t('venueMapNavHint')}</p>
            <p className="text-xs text-[var(--muted)]">{t('venueMapReplaceShapeHint')}</p>
            <p className="text-xs text-[var(--muted)]">{t('venueMapVertexHint')}</p>
            {selected.shape_type ? (
              <div className="flex gap-2">
                <button
                  type="button"
                  className="button-secondary flex-1 inline-flex items-center justify-center gap-2"
                  onClick={() => {
                    const delta = -15
                    const points = (selected.polygon_coordinates ?? [])
                      .filter((point): point is GeoPoint => isGeoPoint(point))
                      .map((point) => ({ lat: Number(point.lat), lng: Number(point.lng) }))
                    if (points.length >= 3) {
                      updateSelected({
                        polygon_coordinates: rotateGeoPointsAround(points, centroidGeo(points), delta),
                        shape_rotation: normalizeDegrees((selected.shape_rotation ?? 0) + delta),
                      })
                      return
                    }
                    updateSelected({
                      shape_rotation: normalizeDegrees((selected.shape_rotation ?? 0) + delta),
                    })
                  }}
                >
                  <RotateCcw size={14} />
                  {t('venueMapRotateLeft')}
                </button>
                <button
                  type="button"
                  className="button-secondary flex-1 inline-flex items-center justify-center gap-2"
                  onClick={() => {
                    const delta = 15
                    const points = (selected.polygon_coordinates ?? [])
                      .filter((point): point is GeoPoint => isGeoPoint(point))
                      .map((point) => ({ lat: Number(point.lat), lng: Number(point.lng) }))
                    if (points.length >= 3) {
                      updateSelected({
                        polygon_coordinates: rotateGeoPointsAround(points, centroidGeo(points), delta),
                        shape_rotation: normalizeDegrees((selected.shape_rotation ?? 0) + delta),
                      })
                      return
                    }
                    updateSelected({
                      shape_rotation: normalizeDegrees((selected.shape_rotation ?? 0) + delta),
                    })
                  }}
                >
                  <RotateCw size={14} />
                  {t('venueMapRotateRight')}
                </button>
              </div>
            ) : null}
            {selected.shape_type ? (
              <button
                type="button"
                className="button-secondary w-full"
                onClick={() => {
                  updateSelected({
                    shape_type: null,
                    polygon_coordinates: null,
                    shape_radius: null,
                    shape_radius_y: null,
                    shape_rotation: 0,
                  })
                  setTool('polygon')
                }}
              >
                {t('venueMapClearShape')}
              </button>
            ) : null}
            <button
              type="button"
              className="button-secondary w-full text-[var(--danger)]"
              onClick={() => {
                commitZones(history.zones.filter((zone) => zone.key !== selected.key))
                setSelectedKey(null)
              }}
            >
              {t('delete')}
            </button>
          </div>
        ) : null}
        </div>
      </aside>
    </form>
  )
}
