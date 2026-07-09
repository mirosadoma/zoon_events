import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { CheckInCounters } from '@/components/checkin/CheckInCounters'
import { scanReasonLabel } from '@/lib/scanLabels'

describe('scanning redesign', () => {
  it('renders check-in counters', () => {
    render(
      <CheckInCounters
        summary={{ registered_count: 10, checked_in_count: 4, rejected_count: 1, duplicate_count: 0, last_scan_at: null }}
        locale="en"
      />,
    )

    expect(screen.getByText('4')).toBeInTheDocument()
  })

  it('translates scan reasons by locale', () => {
    expect(scanReasonLabel('credential_unknown', 'en')).toContain('unknown')
    expect(scanReasonLabel('credential_unknown', 'ar')).toContain('غير معروفة')
  })
})
