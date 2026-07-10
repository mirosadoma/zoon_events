export type AcsZoneStatus = 'active' | 'inactive'
export type UnavailabilityMode = 'fail_open' | 'fail_closed'
export type EmergencyEgressMode = 'fail_open' | 'fail_closed'
export type AcsLaneStatus = 'active' | 'inactive'
export type GateType = 'turnstile' | 'door' | 'speedgate' | 'manual'
export type AccessDirection = 'entry' | 'exit' | 'bidirectional'
export type LaneHealthStatus = 'online' | 'degraded' | 'offline'
export type AcsRuleStatus = 'active' | 'inactive'
export type AccessEventType = 'decision' | 'entry' | 'exit' | 'emergency'
export type AccessDecision = 'allow' | 'deny' | 'n/a'

export const ACS_REASON_CODES = [
  'allowed',
  'credential_expired',
  'credential_revoked',
  'credential_unknown',
  'zone_not_permitted',
  'lane_not_permitted',
  'outside_time_window',
  'anti_passback_violation',
  'acs_unavailable_fail_open',
  'acs_unavailable_fail_closed',
  'emergency_fail_open',
] as const

export type AcsReasonCode = (typeof ACS_REASON_CODES)[number]

export interface AcsZone {
  id: string
  name: string
  external_acs_zone_id: string
  anti_passback_enabled: boolean
  unavailability_mode: UnavailabilityMode
  emergency_egress_mode: EmergencyEgressMode
  status: AcsZoneStatus
}

export interface AcsLane {
  id: string
  zone_id: string
  name: string
  external_acs_lane_id: string
  gate_type: GateType
  access_direction: AccessDirection
  is_admission_lane: boolean
  status: AcsLaneStatus
  health_status: LaneHealthStatus
  last_seen_at: string | null
}

export interface AcsRule {
  id: string
  ticket_type_id: string | null
  attendee_type: string | null
  zone_id: string
  lane_id: string | null
  access_direction: AccessDirection
  anti_passback_exempt: boolean
  valid_from: string | null
  valid_until: string | null
  status: AcsRuleStatus
}

export interface AccessEvent {
  id: string
  event_type: AccessEventType
  decision: AccessDecision
  reason_code: AcsReasonCode | string
  direction: string
  zone_id: string | null
  lane_id: string | null
  credential_id: string | null
  occurred_at: string
}
