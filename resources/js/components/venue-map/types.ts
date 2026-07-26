export type RelativePoint = {
  x: number
  y: number
}

export type ZoneShapeType = 'polygon' | 'rectangle' | 'circle'

export type MapZone = {
  key: string
  id?: string
  zone_name_en: string
  zone_name_ar: string
  type: string
  capacity: number | null
  shape_type: ZoneShapeType | null
  polygon_coordinates: RelativePoint[] | null
  shape_radius: number | null
  label: string | null
  google_maps_url: string | null
  lat: number | null
  lng: number | null
  fill_color: string | null
  stroke_color: string | null
  opacity: number | null
  stroke_width: number | null
  name?: { en: string; ar: string }
}

export type VenueMapData = {
  id: string
  venue_id: string
  image_url: string | null
  image_path: string
  width: number | null
  height: number | null
}

export type EditorTool = 'select' | 'polygon' | 'rectangle' | 'circle' | 'delete'

export const DEFAULT_ZONE_COLORS: Record<string, string> = {
  hall: '#7c3aed',
  stage: '#2563eb',
  room: '#db2777',
  vip: '#9333ea',
  parking: '#ca8a04',
  outdoor: '#16a34a',
  other: '#64748b',
}

export function defaultFillForType(type: string): string {
  return DEFAULT_ZONE_COLORS[type] ?? DEFAULT_ZONE_COLORS.other
}
