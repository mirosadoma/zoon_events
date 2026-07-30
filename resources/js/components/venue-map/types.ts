export type RelativePoint = {
  x: number
  y: number
}

export type GeoPoint = {
  lat: number
  lng: number
}

export type MapPoint = RelativePoint | GeoPoint

export type ZoneShapeType = 'polygon' | 'rectangle' | 'circle' | 'triangle' | 'hexagon' | 'ellipse' | 'pillar' | 'person'

export type MapZone = {
  key: string
  id?: string
  zone_name_en: string
  zone_name_ar: string
  description_en: string | null
  description_ar: string | null
  type: string
  floor_type: 'basement' | 'floor' | null
  floor_number: number | null
  capacity: number | null
  scanner_code: string | null
  shape_type: ZoneShapeType | null
  coordinate_space?: 'relative' | 'geo'
  polygon_coordinates: MapPoint[] | null
  shape_radius: number | null
  shape_rotation: number
  shape_radius_y: number | null
  label: string | null
  google_maps_url: string | null
  lat: number | null
  lng: number | null
  fill_color: string | null
  fill_image_path?: string | null
  fill_image_url?: string | null
  stroke_color: string | null
  opacity: number | null
  stroke_width: number | null
  name?: { en: string; ar: string }
}

export type MapPath = {
  key: string
  id?: string
  name_en: string
  name_ar: string
  coordinate_space?: 'relative' | 'geo'
  polyline_coordinates: MapPoint[]
  from_zone_key: string | null
  to_zone_key: string | null
  stroke_color: string | null
  stroke_width: number | null
  opacity: number | null
}

export type VenueMapData = {
  id: string
  venue_id: string
  image_url: string | null
  image_path: string
  width: number | null
  height: number | null
  overlay_opacity?: number
  remove_background?: boolean
  show_base_map?: boolean
  map_center_lat?: number | null
  map_center_lng?: number | null
  map_zoom?: number | null
  map_heading?: number | null
  map_type?: string | null
  overlay_north?: number | null
  overlay_south?: number | null
  overlay_east?: number | null
  overlay_west?: number | null
  overlay_rotation?: number | null
}

export type EditorTool =
  | 'select'
  | 'polygon'
  | 'rectangle'
  | 'circle'
  | 'triangle'
  | 'hexagon'
  | 'ellipse'
  | 'pillar'
  | 'person'
  | 'path'
  | 'delete'

export const DEFAULT_ZONE_COLORS: Record<string, string> = {
  gate: '#dc2626',
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

export const DEFAULT_PATH_COLOR = '#2563eb'
