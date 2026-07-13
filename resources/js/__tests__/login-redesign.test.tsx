import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useForm } from '@inertiajs/react'
import { beforeEach, describe, expect, it, vi } from 'vitest'
import Login from '@/pages/Auth/Login'

const pageProps = {
  locale: 'en',
  direction: 'ltr',
  errors: {} as Record<string, string>,
}

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <title>{title}</title>,
  useForm: vi.fn(),
  usePage: () => ({ props: pageProps }),
  Link: ({ children }: { children: React.ReactNode }) => <span>{children}</span>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    localizedPath: (path: string) => path,
    t: (key: string) => key,
  }),
}))

vi.mock('@/components/routing/LocalizedLink', () => ({
  default: ({ children }: { children: React.ReactNode }) => <span>{children}</span>,
}))

describe('login redesign', () => {
  const post = vi.fn()

  beforeEach(() => {
    post.mockReset()
    pageProps.errors = {}
    HTMLElement.prototype.scrollIntoView = vi.fn()
  })

  it('renders fields and submit button', () => {
    vi.mocked(useForm).mockReturnValue({
      data: { email: '', password: '', remember: false },
      errors: {},
      processing: false,
      reset: vi.fn(),
      setData: vi.fn(),
      post,
    } as unknown as ReturnType<typeof useForm>)

    render(<Login />)

    expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
  })

  it('shows product-tour validation popover for empty fields', async () => {
    vi.mocked(useForm).mockReturnValue({
      data: { email: '', password: '', remember: false },
      errors: {},
      processing: false,
      reset: vi.fn(),
      setData: vi.fn(),
      post,
    } as unknown as ReturnType<typeof useForm>)

    render(<Login />)
    fireEvent.click(screen.getByRole('button', { name: /sign in/i }))

    await waitFor(() => {
      expect(screen.getByRole('alertdialog')).toBeInTheDocument()
    })
    expect(screen.getByText(/email: the email field is required/i)).toBeInTheDocument()
    expect(screen.getByText(/password: the password field is required/i)).toBeInTheDocument()
    expect(post).not.toHaveBeenCalled()
  })

  it('shows credential failure popover when server returns errors', async () => {
    vi.mocked(useForm).mockReturnValue({
      data: { email: 'user@example.com', password: 'wrong', remember: false },
      errors: { email: 'These credentials do not match our records.' },
      processing: false,
      reset: vi.fn(),
      setData: vi.fn(),
      post,
    } as unknown as ReturnType<typeof useForm>)

    render(<Login />)

    await waitFor(() => {
      expect(screen.getByRole('alertdialog')).toBeInTheDocument()
    })
    expect(screen.getByText(/email: these credentials do not match our records/i)).toBeInTheDocument()
  })

  it('shows credential failure popover from inertia page props when form errors are empty', async () => {
    pageProps.errors = { email: 'These credentials do not match our records.' }

    vi.mocked(useForm).mockReturnValue({
      data: { email: 'user@example.com', password: 'wrong', remember: false },
      errors: {},
      processing: false,
      reset: vi.fn(),
      setData: vi.fn(),
      post,
    } as unknown as ReturnType<typeof useForm>)

    render(<Login />)

    await waitFor(() => {
      expect(screen.getByRole('alertdialog')).toBeInTheDocument()
    })
    expect(screen.getByText(/email: these credentials do not match our records/i)).toBeInTheDocument()
  })
})
