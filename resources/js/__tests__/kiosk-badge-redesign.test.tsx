import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import { HealthTable } from '@/components/kiosk/HealthTable'

describe('kiosk badge redesign', () => {
  it('renders kiosk health loading state', () => {
    render(<HealthTable eventId="1" tenantId="1" pollIntervalMs={60000} />)
    expect(screen.getByText(/Loading kiosk health/i)).toBeInTheDocument()
  })
})
