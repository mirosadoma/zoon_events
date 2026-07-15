import type { AssetType } from '@/types/phase6'

export const ASSET_TYPES: AssetType[] = [
  'room',
  'hall',
  'gate_lane',
  'kiosk',
  'printer',
  'scanner',
  'camera',
  'other_infrastructure',
]

export function assetTypeLabelKey(type: AssetType): string {
  const map: Record<AssetType, string> = {
    room: 'assetTypeRoom',
    hall: 'assetTypeHall',
    gate_lane: 'assetTypeGateLane',
    kiosk: 'assetTypeKiosk',
    printer: 'assetTypePrinter',
    scanner: 'assetTypeScanner',
    camera: 'assetTypeCamera',
    other_infrastructure: 'assetTypeOtherInfrastructure',
  }

  return map[type]
}

export function formatMinorUnits(amount: number, currency: string, locale: string): string {
  const value = amount / 100
  return new Intl.NumberFormat(locale === 'ar' ? 'ar-EG' : 'en-GB', {
    style: 'currency',
    currency,
    minimumFractionDigits: 2,
  }).format(value)
}
