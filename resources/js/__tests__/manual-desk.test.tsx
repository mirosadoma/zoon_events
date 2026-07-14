import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import ManualDesk from '@/pages/tenant/manual-desk/Desk'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  usePage: () => ({ props: { can: { 'badge.print': true, 'badge.reprint': true, 'attendee.walkup.register': true } } }),
}))

vi.mock('@/components/routing/LocalizedLink', () => ({
  default: ({ href, children, className }: { href: string; children: React.ReactNode; className?: string }) => (
    <a href={href} className={className}>{children}</a>
  ),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    t: (key: string) => key,
  }),
}))

const event = { id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }

describe('manual desk flow', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('searches attendees and renders lookup matches', async () => {
    vi.mocked(fetch)
      .mockResolvedValueOnce({
        ok: true,
        json: async () => ({
          data: {
            too_many: false,
            matches: [{
              attendee_id: 'att_1',
              credential_id: 'cred_1',
              display_name: 'Synthetic Attendee',
              ticket_type_label: 'General',
              checkin_status: 'pending',
            }],
          },
        }),
      } as Response)

    render(<ManualDesk event={event} tenantId="ten_1" ticketTypes={[]} />)

    fireEvent.change(screen.getByLabelText(/^Search/), { target: { value: 'Synthetic' } })
    fireEvent.click(screen.getByRole('button', { name: 'Search' }))

    await waitFor(() => {
      expect(screen.getByRole('button', { name: /Synthetic Attendee/ })).toBeInTheDocument()
    })
  })

  it('requires a reason before override confirmation', async () => {
    vi.mocked(fetch).mockResolvedValueOnce({
      ok: true,
      json: async () => ({
        data: {
          too_many: false,
          matches: [{
            attendee_id: 'att_1',
            credential_id: 'cred_1',
            display_name: 'Rejected Attendee',
            ticket_type_label: 'General',
            checkin_status: 'rejected',
          }],
        },
      }),
    } as Response)

    render(<ManualDesk event={event} tenantId="ten_1" ticketTypes={[]} />)

    fireEvent.change(screen.getByLabelText(/^Search/), { target: { value: 'Rejected' } })
    fireEvent.click(screen.getByRole('button', { name: 'Search' }))

    await waitFor(() => {
      fireEvent.click(screen.getByRole('button', { name: /Rejected Attendee/ }))
    })

    expect(screen.getByRole('dialog')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Override' })).toBeDisabled()
  })
})
