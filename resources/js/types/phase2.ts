export type WalletPassProvider = 'apple' | 'google'

export type WalletPassStatus =
  | 'created'
  | 'active'
  | 'updated'
  | 'revoked'
  | 'expired'
  | 'failed'

export interface WalletPass {
  id: string
  provider: WalletPassProvider
  status: WalletPassStatus
  pass_url: string | null
  last_pushed_at: string | null
}

export type ScanResult =
  | 'accepted'
  | 'manual_override'
  | 'duplicate'
  | 'revoked'
  | 'expired'
  | 'rejected'

export interface ScanEvent {
  id: string
  result: ScanResult
  reason: string
  scanned_at: string
}

export interface CheckInSummary {
  registered_count: number
  checked_in_count: number
  rejected_count: number
  duplicate_count: number
  last_scan_at: string | null
}
