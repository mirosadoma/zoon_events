import { render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import HttpError from '@/pages/errors/HttpError'

vi.mock('@inertiajs/react', () => ({
  Head: ({ title }: { title: string }) => <title>{title}</title>,
  Link: ({ href, children, ...props }: { href: string; children: React.ReactNode }) => (
    <a href={href} {...props}>{children}</a>
  ),
  usePage: () => ({
    props: {
      locale: 'en',
      direction: 'ltr',
      siteSettings: { app_name_en: 'Zoon' },
    },
  }),
}))

describe('http error pages', () => {
  it.each([
    [401, 'Sign in required'],
    [403, 'Access denied'],
    [404, 'Page not found'],
    [405, 'Method not allowed'],
    [419, 'Session expired'],
    [429, 'Too many requests'],
    [500, 'Something went wrong'],
    [503, 'Service unavailable'],
  ])('renders branded copy for status %i', (statusCode, title) => {
    render(<HttpError statusCode={statusCode} />)

    expect(screen.getByRole('heading', { level: 1, name: title })).toBeInTheDocument()
    expect(screen.getAllByText(String(statusCode)).length).toBeGreaterThan(0)
  })
})
