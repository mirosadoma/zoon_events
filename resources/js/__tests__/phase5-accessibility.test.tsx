import { render, screen } from '@testing-library/react'
import axe from 'axe-core'
import { afterEach, describe, expect, it, vi } from 'vitest'
import IdentityRequirementsPage from '@/pages/tenant/identity/Requirements'
import IdentityReviewQueuePage from '@/pages/tenant/identity/ReviewQueue'
import IdentityVerificationDetailPage from '@/pages/tenant/identity/VerificationDetail'
import IdentityVerifyPage from '@/pages/public/identity/Verify'

let currentLocale: 'en' | 'ar' = 'en'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children, title }: { children: React.ReactNode; title?: string }) => (
    <main dir={currentLocale === 'ar' ? 'rtl' : 'ltr'} lang={currentLocale}>
      {title ? <h1>{title}</h1> : null}
      {children}
    </main>
  ),
}))

vi.mock('@inertiajs/react', () => ({
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  router: { reload: vi.fn(), visit: vi.fn() },
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: currentLocale,
    direction: currentLocale === 'ar' ? 'rtl' : 'ltr',
    t: (key: string) => key,
  }),
}))

vi.mock('@/hooks/useToast', () => ({
  useToast: () => ({ toast: vi.fn() }),
}))

vi.mock('@/components/layout/PermissionGate', () => ({
  default: ({ children }: { children: React.ReactNode }) => <>{children}</>,
}))

const event = { id: 'evt_1', slug: 'summit', name: { en: 'Summit', ar: 'القمة' } }

const disclosures = {
  what: { en: 'Minimized identity attributes', ar: 'سمات هوية مختصرة' },
  why: { en: 'Gate and credential assurance', ar: 'ضمان الدخول وإصدار بيانات الدخول' },
  retention: { en: '30–365 days per policy', ar: '30–365 يومًا حسب السياسة' },
  who: { en: 'Authorized reviewers only', ar: 'المراجعون المصرح لهم فقط' },
  processing_mode: { en: 'On-premise by default', ar: 'محلي افتراضيًا' },
  deletion: { en: 'Request deletion via support', ar: 'اطلب الحذف عبر الدعم' },
}

describe('phase 5 identity accessibility and RTL sweep', () => {
  afterEach(() => {
    currentLocale = 'en'
    vi.restoreAllMocks()
  })

  it('renders tenant identity pages without serious axe violations', async () => {
    const { container } = render(
      <>
        <IdentityRequirementsPage
          tenantId="ten_1"
          event={event}
          ticketTypes={[{ id: 'ticket_1', code: 'VIP', name: { en: 'VIP', ar: 'كبار الشخصيات' } }]}
          requirements={[]}
          canManage
        />
        <IdentityReviewQueuePage
          tenantId="ten_1"
          event={event}
          items={[{
            id: 'ver_1',
            attendee_id: 'att_12345678',
            method: 'face_capture',
            status: 'pending',
            provider_reference: 'face-1',
            submitted_at: '2026-07-08T10:00:00Z',
          }]}
          canReview
        />
        <IdentityVerificationDetailPage
          tenantId="ten_1"
          event={event}
          verificationId="ver_1"
          attendeeId="att_12345678"
          detail={{
            verification: {
              id: 'ver_1',
              attendee_id: 'att_12345678',
              method: 'face_capture',
              status: 'pending',
            },
            artifacts: [],
            consent: {
              notice_version: 'identity-v1',
              residency_mode: 'on_premise',
              consented_at: '2026-07-08T09:00:00Z',
            },
            residency: { mode: 'on_premise', cross_border_transfer: false },
          }}
          canManage
        />
      </>,
    )

    expect(screen.getAllByRole('heading', { name: 'identityRequirements' }).length).toBeGreaterThan(0)
    expect(screen.getAllByRole('heading', { name: 'identityReviewQueue' }).length).toBeGreaterThan(0)
    expect(screen.getAllByRole('heading', { name: 'identityVerificationDetail' }).length).toBeGreaterThan(0)

    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
  })

  it('renders public verify page in Arabic RTL without serious axe violations', async () => {
    currentLocale = 'ar'

    const { container } = render(
      <IdentityVerifyPage
        locale="ar"
        event={event}
        attendeeId="att_1"
        accessToken="token"
        noticeVersion="identity-v1"
        residencyMode="on_premise"
        disclosures={disclosures}
        faceFallbackEnabled
      />,
    )

    expect(container.querySelector('[dir="rtl"]')).toBeInTheDocument()
    expect(screen.getByText('identityConsentTitle')).toBeInTheDocument()

    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
  })
})
