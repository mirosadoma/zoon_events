import { render, screen } from '@testing-library/react'
import { useForm } from '@inertiajs/react'
import { describe, expect, it, vi } from 'vitest'
import Login from '@/pages/Auth/Login'

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <title>{title}</title>,
  useForm: vi.fn(),
  Link: ({ children }: { children: React.ReactNode }) => <span>{children}</span>,
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({
    locale: 'en',
    direction: 'ltr',
    localizedPath: (path: string) => path,
  }),
}))

vi.mock('@/components/routing/LocalizedLink', () => ({
  default: ({ children }: { children: React.ReactNode }) => <span>{children}</span>,
}))

describe('login redesign', () => {
  it('renders fields and submit button', () => {
    vi.mocked(useForm).mockReturnValue({
      data: { email: '', password: '', remember: false },
      errors: {},
      processing: false,
      reset: vi.fn(),
      setData: vi.fn(),
      post: vi.fn(),
    } as unknown as ReturnType<typeof useForm>)

    render(<Login />)

    expect(screen.getByLabelText(/email/i)).toBeInTheDocument()
    expect(screen.getByLabelText(/password/i)).toBeInTheDocument()
    expect(screen.getByRole('button', { name: /sign in/i })).toBeInTheDocument()
  })
})
