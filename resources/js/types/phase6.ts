export type VenueStatus = 'draft' | 'active' | 'suspended' | 'archived'
export type AssetType = 'room' | 'hall' | 'gate_lane' | 'kiosk' | 'printer' | 'scanner' | 'camera' | 'other_infrastructure'
export type AssetStatus = 'draft' | 'active' | 'maintenance' | 'offline' | 'retired'
export type PublicationStatus = 'private' | 'published' | 'withdrawn'
export type RentalStatus = 'requested' | 'approved' | 'rejected' | 'active' | 'completed' | 'cancelled' | 'revoked'
export type DelegationStatus = 'pending' | 'active' | 'degraded' | 'revoked' | 'expired' | 'completed'
export type StatementStatus = 'issued' | 'superseded'
export type DisputeStatus = 'open' | 'under_review' | 'resolved' | 'rejected'

export type LocalizedText = { en: string; ar: string }

export type VenueRow = {
  id: string
  public_id: string
  name: LocalizedText
  city_code?: string | null
  country_code?: string | null
  status: VenueStatus
  active_asset_count?: number
  published_asset_count?: number
  future_reservation_warning?: boolean
  updated_at?: string | null
}

export type VenueDetail = VenueRow & {
  description?: LocalizedText
  address?: LocalizedText
  timezone?: string
  publish_contact?: boolean
  business_contact_name?: string | null
  business_contact_email?: string | null
  business_contact_phone?: string | null
  version?: number
  assets?: VenueAssetRow[]
  publication_readiness?: string[]
}

export type VenueAssetRow = {
  id: string
  asset_type: AssetType
  name: LocalizedText
  operational_status: AssetStatus
  publication_status?: PublicationStatus
  pricing_model?: string | null
  price_minor?: number | null
  currency?: string | null
  location?: LocalizedText
  capabilities?: string[]
  capacity_per_minute?: number | null
  has_binding?: boolean
  publication_readiness?: string[]
}

export type AvailabilityWindow = {
  id: string
  available_from: string
  available_until: string
  status: string
}

export type CatalogAsset = {
  id: string
  publication_id: string
  venue_id: string
  venue_name: LocalizedText
  city_code?: string | null
  country_code?: string | null
  asset_type: AssetType
  name: LocalizedText
  capabilities: string[]
  capacity_per_minute?: number | null
  pricing_model: string
  price_minor: number
  currency: string
  venue_timezone?: string
}

export type RentalRow = {
  id: string
  public_id: string
  viewer_role: 'owner' | 'organizer'
  event_name: LocalizedText
  venue_name: LocalizedText
  window_start: string
  window_end: string
  currency: string
  total_minor: number
  status: RentalStatus
  delegation_status?: DelegationStatus | null
  dispute_status?: string | null
}

export type RentalDetail = RentalRow & {
  version?: number
  owner_display_name?: string
  organizer_display_name?: string
  venue_timezone?: string
  quote_version?: string | null
  lines?: RentalLine[]
  timeline?: RentalTimelineEvent[]
  delegation?: DelegationInfo | null
  operational_links?: OperationalLink[]
}

export type RentalLine = {
  id: string
  asset_name: LocalizedText
  asset_type: AssetType
  pricing_model: string
  unit_price_minor: number
  billable_units: number
  line_total_minor: number
  currency: string
}

export type RentalTimelineEvent = {
  id: string
  kind: string
  occurred_at: string
  summary?: string
}

export type DelegationInfo = {
  status: DelegationStatus
  expires_at?: string | null
  server_timestamp?: string | null
}

export type OperationalLink = {
  key: string
  label: string
  href: string
  permission: string
}

export type StatementRow = {
  id: string
  rental_id: string
  revision: number
  status: StatementStatus
  issued_at: string
  currency: string
  total_minor: number
  dispute_status?: string | null
}

export type StatementDetail = StatementRow & {
  rental_outcome?: string
  window_start?: string
  window_end?: string
  lines?: RentalLine[]
  notice?: string
  revisions?: Array<{ id: string; revision: number; status: StatementStatus; issued_at: string }>
  dispute?: DisputeInfo | null
}

export type DisputeInfo = {
  id: string
  status: DisputeStatus
  reason_category?: string
  reason?: string
  timeline?: RentalTimelineEvent[]
}

export type PlatformDisputeDetail = DisputeInfo & {
  owner_display_name?: string
  organizer_display_name?: string
  venue_name?: LocalizedText
  event_name?: LocalizedText
  platform_notes?: Array<{ id: string; body: string; created_at: string }>
  statement_id?: string
}

export type PlatformMarketplaceRow = {
  id: string
  kind: string
  status: string
  owner_name?: string
  organizer_name?: string
  venue_name?: string
  event_name?: string
  opened_at?: string
}
