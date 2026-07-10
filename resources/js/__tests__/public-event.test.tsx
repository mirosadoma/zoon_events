import { render, screen } from '@testing-library/react'
import { describe, expect, it } from 'vitest'
import PublicRegistrationEvent from '@/pages/public/registration/Event'

const event = {
  name: { en: 'Synthetic Summit', ar: 'قمة تجريبية' },
  description: { en: 'Safe sample', ar: 'مثال آمن' },
  branding: { brand_reference: 'tenant-brand' },
}
const form = {
  fields: [{
    key: 'email',
    type: 'email' as const,
    label_en: 'Email',
    label_ar: 'البريد الإلكتروني',
    required: true,
  }],
  privacy_notice_version: 'privacy-v1',
  terms_version: 'terms-v1',
}

describe('public event registration shell', () => {
  it('renders accessible English LTR content and consent evidence', () => {
    const { container } = render(<PublicRegistrationEvent locale="en" event={event} form={form} />)
    expect(container.querySelector('main')).toHaveAttribute('dir', 'ltr')
    expect(screen.getByRole('heading', { name: 'Synthetic Summit' })).toBeInTheDocument()
    expect(screen.getByLabelText('Email')).toBeRequired()
    expect(screen.getByText(/privacy-v1/)).toBeInTheDocument()
  })

  it('renders Arabic RTL labels without changing stable field keys', () => {
    const { container } = render(<PublicRegistrationEvent locale="ar" event={event} form={form} />)
    expect(container.querySelector('main')).toHaveAttribute('dir', 'rtl')
    expect(screen.getByRole('heading', { name: 'قمة تجريبية' })).toBeInTheDocument()
    expect(screen.getByLabelText('البريد الإلكتروني')).toHaveAttribute('name', 'email')
  })
})
