import { describe, expect, it } from 'vitest'
import { routeToDestination } from '@/components/venue-map/indoorRouting'

const zones = [
  {
    id: 'gate-1',
    type: 'gate',
    shape_type: 'circle' as const,
    coordinate_space: 'geo' as const,
    polygon_coordinates: [{ lat: 24, lng: 46.0005 }],
    shape_radius: 4,
  },
  {
    id: 'hall-1',
    type: 'hall',
    shape_type: 'polygon' as const,
    coordinate_space: 'geo' as const,
    polygon_coordinates: [
      { lat: 24, lng: 46.0015 },
      { lat: 24.0002, lng: 46.0015 },
      { lat: 24.0002, lng: 46.0018 },
    ],
    shape_radius: null,
  },
]

const paths = [
  {
    id: 'path-1',
    coordinate_space: 'geo' as const,
    polyline_coordinates: [
      { lat: 24, lng: 46 },
      { lat: 24, lng: 46.001 },
    ],
  },
  {
    id: 'path-2',
    coordinate_space: 'geo' as const,
    polyline_coordinates: [
      { lat: 24, lng: 46.001 },
      { lat: 24, lng: 46.0005 },
    ],
  },
  {
    id: 'path-3',
    coordinate_space: 'geo' as const,
    polyline_coordinates: [
      { lat: 24, lng: 46.001 },
      { lat: 24, lng: 46.0017 },
    ],
  },
]

describe('indoorRouting', () => {
  it('routes from outside point via nearest gate', () => {
    const result = routeToDestination({
      origin: { lat: 24.001, lng: 45.9995 },
      destinationZoneId: 'hall-1',
      zones,
      paths,
      overlayBounds: {
        north: 24.0004,
        south: 23.9996,
        east: 46.0019,
        west: 45.9998,
      },
    })

    expect(result).not.toBeNull()
    expect(result?.usedGateZoneId).toBe('gate-1')
    expect(result?.approachRoute.length).toBe(2)
    expect(result?.indoorRoute.length).toBeGreaterThanOrEqual(2)
    expect(result?.distanceMeters).toBeGreaterThan(0)
  })

  it('supports manual start zone without geolocation', () => {
    const result = routeToDestination({
      origin: null,
      startZoneId: 'gate-1',
      destinationZoneId: 'hall-1',
      zones,
      paths,
      overlayBounds: {
        north: 24.0004,
        south: 23.9996,
        east: 46.0019,
        west: 45.9998,
      },
    })

    expect(result).not.toBeNull()
    expect(result?.usedGateZoneId).toBeNull()
    expect(result?.approachRoute).toEqual([])
    expect(result?.indoorRoute.length).toBeGreaterThanOrEqual(2)
  })
})
