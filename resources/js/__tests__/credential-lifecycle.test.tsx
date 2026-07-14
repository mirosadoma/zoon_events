import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import axe from 'axe-core'
import Credentials from '@/pages/tenant/events/Credentials'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { get: vi.fn() },
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'ar',
    direction: 'rtl',
    t: (key: string) => ({
      credentials: 'بيانات الدخول',
      overview: 'نظرة عامة',
      search: 'بحث',
      allStatuses: 'كل الحالات',
      noCredentials: 'لا توجد بيانات دخول',
      noCredentialsDetail: 'ستظهر بيانات الدخول بعد الإصدار.',
      attendees: 'الحضور',
      previousPage: 'السابق',
      nextPage: 'التالي',
      pageOf: 'صفحة :page من :total',
    } as Record<string, string>)[key] ?? key,
  }),
}))

vi.mock('@/hooks/useLocalizedRouter', () => ({
  useLocalizedRouter: () => ({ get: vi.fn() }),
}))

describe('credential lifecycle UI', () => {
  it('renders credentials list empty state in Arabic', () => {
    render(
      <Credentials
        event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }}
        credentials={[]}
      />,
    )

    expect(screen.getByRole('heading', { name: 'بيانات الدخول' })).toBeInTheDocument()
    expect(screen.getByText('لا توجد بيانات دخول')).toBeInTheDocument()
  })

  it('renders the Arabic credentials list accessibly', async () => {
    const { container } = render(
      <Credentials
        event={{ id: 'evt_1', name: { en: 'Summit', ar: 'القمة' } }}
        credentials={[
          {
            id: 'cred_1',
            code: 'ABCD1234',
            attendee_id: 'attendee_1',
            status: 'active',
            issued_at: '2026-07-01T10:00:00Z',
            expires_at: '2026-08-01T10:00:00Z',
          },
        ]}
      />,
    )

    expect(screen.getByRole('link', { name: 'ABCD1234' })).toBeInTheDocument()
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
  })
})
