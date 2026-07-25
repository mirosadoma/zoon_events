import type { VenueFormRow } from '@/components/forms/VenueRepeater'

export function buildVenuePayload(venues: VenueFormRow[]) {
  return venues
    .filter((venue) => venue.name_en.trim() !== '' && venue.name_ar.trim() !== '')
    .map((venue) => ({
      id: venue.id ? Number(venue.id) : undefined,
      country_id: venue.country_id ? Number(venue.country_id) : null,
      city_id: venue.city_id ? Number(venue.city_id) : null,
      name: { en: venue.name_en, ar: venue.name_ar },
      location_address: venue.location_address || null,
      latitude: venue.latitude === '' ? null : Number(venue.latitude),
      longitude: venue.longitude === '' ? null : Number(venue.longitude),
      start_at: venue.start_at || null,
      end_at: venue.end_at || null,
      registration_opens_at: venue.registration_opens_at || null,
      registration_closes_at: venue.registration_closes_at || null,
    }))
}
