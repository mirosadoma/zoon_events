import { describe, expect, it } from 'vitest'
import { checkinStatusLabel, scanReasonLabel } from '@/lib/scanLabels'

describe('orders attendees credentials redesign', () => {
  it('translates linked attendee check-in status', () => {
    expect(checkinStatusLabel('checked_in', 'en')).toBe('Checked in')
    expect(checkinStatusLabel('checked_in', 'ar')).toBe('تم تسجيل الحضور')
  })

  it('translates scan rejection reasons', () => {
    expect(scanReasonLabel('zone_not_permitted', 'en')).toContain('zone')
    expect(scanReasonLabel('zone_not_permitted', 'ar')).toContain('المنطقة')
  })
})
