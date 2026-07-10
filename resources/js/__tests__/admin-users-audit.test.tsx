import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { router } from '@inertiajs/react'
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import AdminAuditLogs from '@/pages/admin/AuditLogs'
import AdminUsers from '@/pages/admin/Users'

vi.mock('@/layouts/DashboardLayout', () => ({
  default: ({ children }: { children: React.ReactNode }) => <div>{children}</div>,
}))

vi.mock('@inertiajs/react', () => ({
  router: { get: vi.fn() },
  Link: ({ href, children }: { href: string; children: React.ReactNode }) => <a href={href}>{children}</a>,
  usePage: () => ({ props: { can: { 'membership.manage': true } } }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    messages: {
      users: 'Users',
      overview: 'Overview',
      administration: 'Administration',
      adminUsersDescription: 'Manage tenant memberships and activation status.',
      allStatuses: 'All statuses',
      statusActive: 'Active',
      statusSuspended: 'Suspended',
      statusDeactivated: 'Deactivated',
      profileName: 'Name',
      profileEmail: 'Email',
      orderStatus: 'Status',
      adminAccountStatus: 'Account status',
      adminActions: 'Actions',
      adminActivateUser: 'Activate user',
      adminSuspendUser: 'Suspend user',
      adminStatusChangeReason: 'Provide a reason for this membership status change.',
      reasonRequired: 'A reason is required.',
      confirm: 'Confirm',
      cancel: 'Cancel',
      adminUserUpdated: 'Membership status updated.',
      adminAddUser: 'Add user',
      adminAddUserTitle: 'Invite team member',
      adminAddUserDescription: 'Create an account and add it to your tenant team.',
      adminAddUserSuccess: 'Team member added.',
      adminDefaultLocale: 'Default locale',
      adminNoUsers: 'No team members yet.',
      cancel: 'Cancel',
      errorState: 'Something went wrong.',
      audit: 'Audit',
      adminAuditDescription: 'Search tenant audit activity with bounded filters.',
      adminFilterFrom: 'From',
      adminFilterTo: 'To',
      adminFilterAction: 'Action',
      adminFilterOutcome: 'Outcome',
      adminFilterActor: 'Actor ID',
      adminOutcomeSucceeded: 'Succeeded',
      adminOutcomeFailed: 'Failed',
      search: 'Search',
      emptyAudit: 'No recent audit events.',
      adminOccurredAt: 'Occurred at',
      adminTargetType: 'Target type',
      adminTargetId: 'Target ID',
    },
  }),
}))

vi.mock('@/hooks/useToast', () => ({
  useToast: () => ({ toast: vi.fn() }),
}))

vi.mock('@/lib/apiFetch', () => ({
  apiFetch: vi.fn(),
  ApiFetchError: class ApiFetchError extends Error {
    status: number
    errors: Record<string, string>
    constructor(status: number, message: string, errors: Record<string, string> = {}) {
      super(message)
      this.status = status
      this.errors = errors
    }
  },
}))

const users = [
  {
    id: 'mem_1',
    name: 'Alpha User',
    email: 'alpha@example.test',
    status: 'suspended',
    user_status: 'active',
    created_at: '2026-07-01T00:00:00Z',
  },
]

describe('admin users and audit pages', () => {
  beforeEach(() => {
    vi.stubGlobal('fetch', vi.fn())
    vi.stubGlobal('crypto', { randomUUID: () => 'test-idempotency-key' })
  })

  afterEach(() => {
    vi.unstubAllGlobals()
    vi.restoreAllMocks()
  })

  it('shows add user button when membership.manage is granted', () => {
    render(<AdminUsers tenantId="ten_1" users={[]} roles={[]} />)

    expect(screen.getByRole('button', { name: 'Add user' })).toBeInTheDocument()
  })

  it('filters users and activates a suspended membership', async () => {
    vi.mocked(fetch).mockResolvedValue({
      ok: true,
      json: async () => ({ data: { id: 'mem_1', status: 'active' } }),
    } as Response)

    render(<AdminUsers tenantId="ten_1" users={users} />)

    expect(screen.getByText('Alpha User')).toBeInTheDocument()

    fireEvent.click(screen.getByRole('button', { name: 'Activate user' }))
    fireEvent.change(screen.getByLabelText('A reason is required'), { target: { value: 'Restored access' } })
    fireEvent.click(screen.getByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(vi.mocked(fetch)).toHaveBeenCalled())
    expect(vi.mocked(fetch)).toHaveBeenCalledWith(
      '/api/v1/tenant/memberships/mem_1',
      expect.objectContaining({ method: 'PATCH' }),
    )
  })

  it('renders audit logs and submits filter form', () => {
    render(
      <AdminAuditLogs
        tenantId="ten_1"
        filters={{}}
        auditLogs={[
          {
            id: 'log_1',
            actor_id: 'usr_1',
            action: 'membership.updated',
            target_type: 'membership',
            target_id: 'mem_1',
            outcome: 'succeeded',
            occurred_at: '2026-07-01T10:00:00Z',
          },
        ]}
      />,
    )

    expect(screen.getByText('membership.updated')).toBeInTheDocument()

    fireEvent.change(screen.getByLabelText('Action'), { target: { value: 'membership.updated' } })
    fireEvent.click(screen.getByRole('button', { name: 'Search' }))

    expect(router.get).toHaveBeenCalledWith('/admin/audit-logs', { action: 'membership.updated' }, { preserveState: true })
  })
})
