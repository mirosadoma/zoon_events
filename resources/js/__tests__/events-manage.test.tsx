import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import EventList from '@/pages/tenant/events/List'
import EventSetup from '@/pages/tenant/events/EventSetup'
import EventDetail from '@/pages/tenant/events/Detail'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { reload: vi.fn(), visit: vi.fn() },
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => key,
  }),
}))

const toastMock = vi.fn()

vi.mock('@/hooks/useToast', () => ({
  useToast: () => ({ toast: toastMock }),
}))

vi.mock('@/components/layout/PermissionGate', () => ({
  default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

describe('events manage flow', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
    vi.stubGlobal('crypto', { randomUUID: () => 'event-idempotency-key' })
    toastMock.mockReset()
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('renders events list with create action', () => {
    render(
      <EventList
        events={[
          {
            id: 'evt_1',
            name: { en: 'Summit', ar: 'القمة' },
            status: 'published',
            tier: 'public',
            timezone: 'Africa/Cairo',
            capacity: 100,
            registration_url: 'https://zoon.test/en/events/summit/agenda',
          },
        ]}
      />,
    )

    expect(screen.getByRole('heading', { name: 'Events' })).toBeInTheDocument()
    expect(screen.getByRole('link', { name: 'New event' })).toHaveAttribute('href', '/en/tenant/events/create')
    expect(screen.getByRole('link', { name: 'Summit' })).toHaveAttribute('href', '/en/tenant/events/evt_1')
  })

  it('copies registration link and shows toast', async () => {
    const writeText = vi.fn().mockResolvedValue(undefined)
    Object.assign(navigator, { clipboard: { writeText } })

    render(
      <EventList
        events={[
          {
            id: 'evt_1',
            name: { en: 'Summit', ar: 'القمة' },
            status: 'published',
            tier: 'public',
            timezone: 'Africa/Cairo',
            capacity: 100,
            registration_url: 'https://zoon.test/en/events/summit/agenda',
          },
        ]}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Copy registration link' }))

    await waitFor(() => {
      expect(writeText).toHaveBeenCalledWith('https://zoon.test/en/events/summit/agenda')
      expect(toastMock).toHaveBeenCalledWith('Copied', 'success')
    })
  })

  it('renders create-event setup shell', () => {
    render(
      <EventSetup
        tenantId="1"
        event={{
          id: null,
          slug: '',
          name: { en: '', ar: '' },
          description: { en: '', ar: '' },
          status: 'draft',
          tier: 'public',
          event_type: 'seminar',
          registration_mode: 'free_registration',
          timezone: 'Africa/Cairo',
          start_at: null,
          end_at: null,
          registration_opens_at: null,
          registration_closes_at: null,
          capacity: null,
          location_name: { en: '', ar: '' },
          location_address: { en: '', ar: '' },
          brand_reference: null,
          domain_reference: null,
          readiness: ['Save the event before publishing.'],
        }}
        eventPermissions={{ manage: true, publish: false }}
      />,
    )

    expect(screen.getByRole('heading', { name: 'New event' })).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Continue' })).toBeInTheDocument()
  })

  it('calls publish endpoint with expected request metadata', async () => {
    document.cookie = 'XSRF-TOKEN=test-token'
    vi.mocked(fetch).mockResolvedValue({
      ok: true,
      json: async () => ({ data: { id: 'evt_1', status: 'published' } }),
    } as Response)

    render(
      <EventDetail
        tenantId="ten_1"
        event={{
          id: 'evt_1',
          name: { en: 'Summit', ar: 'القمة' },
          status: 'draft',
          tier: 'public',
          timezone: 'Africa/Cairo',
          capacity: 100,
        }}
        setupTabs={[]}
        operationsTabs={[]}
      />,
    )

    expect(screen.getByRole('link', { name: 'Preview' })).toHaveAttribute('href', '/en/tenant/events/evt_1/agenda-preview')
    fireEvent.click(screen.getByRole('button', { name: 'Publish' }))
    fireEvent.click(screen.getAllByRole('button', { name: 'Publish' }).at(-1)!)

    await waitFor(() => {
      const publishCall = vi.mocked(fetch).mock.calls.find(
        ([input]) => input === '/api/v1/tenant/events/evt_1/publish',
      )
      expect(publishCall).toBeDefined()
    })

    const publishCall = vi.mocked(fetch).mock.calls.find(
      ([input]) => input === '/api/v1/tenant/events/evt_1/publish',
    )!
    const init = publishCall[1] as RequestInit
    expect(init.method).toBe('POST')
    expect(init.credentials).toBe('include')
    const headers = new Headers(init.headers)
    expect(headers.get('X-Tenant-ID')).toBe('ten_1')
    expect(headers.get('Idempotency-Key')).toBe('event-idempotency-key')
    expect(headers.get('X-XSRF-TOKEN')).toBe('test-token')

    expect(toastMock).toHaveBeenCalledWith('Event published.', 'success')
  })

  it('shows error toast and avoids success when cancel fails', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: false,
      json: async () => ({ detail: 'event_already_cancelled' }),
    } as Response)

    render(
      <EventDetail
        tenantId="ten_1"
        event={{
          id: 'evt_1',
          name: { en: 'Summit', ar: 'القمة' },
          status: 'published',
          tier: 'public',
          timezone: 'Africa/Cairo',
          capacity: 100,
        }}
        setupTabs={[]}
        operationsTabs={[]}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Cancel' }))
    fireEvent.click(screen.getByRole('button', { name: 'Confirm cancel' }))

    await waitFor(() => expect(toastMock).toHaveBeenCalledWith('event_already_cancelled', 'error'))
    expect(toastMock).not.toHaveBeenCalledWith('Event cancelled.', 'success')
  })
})
