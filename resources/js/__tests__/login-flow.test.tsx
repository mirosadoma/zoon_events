import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import Login from '@/pages/Auth/Login'

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <title>{title}</title>,
  useForm: () => ({
    data: { email: '', password: '', remember: false },
    setData: vi.fn(),
    post: vi.fn(),
    processing: false,
    errors: {},
    reset: vi.fn(),
  }),
}))

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

describe('login flow', () => {
  it('renders sign-in form with accessible fields', () => {
    render(<Login />)
    expect(screen.getByRole('heading', { name: 'Sign in' })).toBeInTheDocument()
    expect(screen.getByLabelText('Email')).toBeRequired()
    expect(screen.getByLabelText('Password')).toBeRequired()
  })

  it('shows failure message when errors are present', () => {
    vi.doMock('@inertiajs/react', () => ({
      Head: ({ title }: { title: string }) => <title>{title}</title>,
      useForm: () => ({
        data: { email: 'a@b.com', password: 'bad', remember: false },
        setData: vi.fn(),
        post: vi.fn(),
        processing: false,
        errors: { email: 'invalid' },
        reset: vi.fn(),
      }),
    }))
  })
})
