import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AcsLanes from '@/pages/tenant/acs/Lanes'
import AcsRules from '@/pages/tenant/acs/Rules'
import AcsZones from '@/pages/tenant/acs/Zones'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  usePage: () => ({
    props: {
      can: {},
      siteSettings: { app_name_en: 'Zoon', app_name_ar: 'زون' },
    },
  }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => key,
  }),
}))

vi.mock('@/components/routing/LocalizedLink', () => ({
  default: ({ href, children, className }: { href: string; children: React.ReactNode; className?: string }) => (
    <a href={href} className={className}>{children}</a>
  ),
}))

const event = { id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }

describe('ACS config flow', () => {
  beforeEach(() => {
    document.cookie = 'XSRF-TOKEN=test-token'
    vi.stubGlobal('fetch', vi.fn())
    vi.stubGlobal('crypto', { randomUUID: () => 'test-idempotency-key' })
  })

  afterEach(() => {
    document.cookie = 'XSRF-TOKEN=; Max-Age=0'
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('creates a zone via the zones form', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: true,
      json: async () => ({
        data: {
          id: 'zone_1',
          name: 'Main Hall',
          external_acs_zone_id: 'EXT-ZONE-1',
          anti_passback_enabled: false,
          unavailability_mode: 'fail_closed',
          emergency_egress_mode: 'fail_open',
          status: 'active',
        },
      }),
    } as Response)

    render(<AcsZones event={event} tenantId="ten_1" zones={[]} />)

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Main Hall' } })
    fireEvent.change(screen.getByLabelText(/^External zone ID/), { target: { value: 'EXT-ZONE-1' } })
    fireEvent.submit(screen.getByRole('button', { name: 'Create zone' }).closest('form')!)

    await waitFor(() => expect(vi.mocked(fetch)).toHaveBeenCalled())
    expect(await screen.findByText(/Main Hall/)).toBeInTheDocument()
    expect(vi.mocked(fetch).mock.calls[0]?.[1]?.headers).toEqual(
      expect.any(Headers),
    )
    expect(new Headers(vi.mocked(fetch).mock.calls[0]?.[1]?.headers).get('X-XSRF-TOKEN')).toBe('test-token')
  })

  it('creates a lane assigned to a zone', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: true,
      json: async () => ({
        data: {
          id: 'lane_1',
          zone_id: 'zone_1',
          name: 'Entry A',
          external_acs_lane_id: 'EXT-LANE-1',
          gate_type: 'turnstile',
          access_direction: 'entry',
          is_admission_lane: false,
          status: 'active',
          health_status: 'offline',
          last_seen_at: null,
        },
      }),
    } as Response)

    render(
      <AcsLanes
        event={event}
        tenantId="ten_1"
        zones={[{ id: 'zone_1', name: 'Main Hall', external_acs_zone_id: 'EXT-ZONE-1', anti_passback_enabled: false, unavailability_mode: 'fail_closed', emergency_egress_mode: 'fail_open', status: 'active' }]}
        lanes={[]}
      />,
    )

    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Entry A' } })
    fireEvent.change(screen.getByLabelText(/^External lane ID/), { target: { value: 'EXT-LANE-1' } })
    fireEvent.submit(screen.getByRole('button', { name: 'Create lane' }).closest('form')!)

    await waitFor(() => expect(vi.mocked(fetch)).toHaveBeenCalled())
    expect(await screen.findByText(/Entry A/)).toBeInTheDocument()
  })

  it('creates a rule for a zone', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: true,
      json: async () => ({
        data: {
          id: 'rule_1',
          ticket_type_id: null,
          attendee_type: null,
          zone_id: 'zone_1',
          lane_id: null,
          access_direction: 'entry',
          anti_passback_exempt: false,
          valid_from: null,
          valid_until: null,
          status: 'active',
        },
      }),
    } as Response)

    render(
      <AcsRules
        event={event}
        tenantId="ten_1"
        zones={[{ id: 'zone_1', name: 'Main Hall', external_acs_zone_id: 'EXT-ZONE-1', anti_passback_enabled: false, unavailability_mode: 'fail_closed', emergency_egress_mode: 'fail_open', status: 'active' }]}
        lanes={[]}
        rules={[]}
        ticketTypes={[]}
      />,
    )

    fireEvent.submit(screen.getByRole('button', { name: 'Create rule' }).closest('form')!)

    await waitFor(() => {
      expect(fetch).toHaveBeenCalledWith(
        '/api/v1/tenant/events/evt_1/acs/rules',
        expect.objectContaining({ method: 'POST' }),
      )
    })
    expect(await screen.findByText('entry')).toBeInTheDocument()
    expect(new Headers(vi.mocked(fetch).mock.calls[0]?.[1]?.headers).get('X-XSRF-TOKEN')).toBe('test-token')
  })
})
