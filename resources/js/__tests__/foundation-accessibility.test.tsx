import { render, screen } from '@testing-library/react'
import axe from 'axe-core'
import { ConflictState, EmptyState, ErrorState, ForbiddenState, LoadingState, QueuedState } from '@/components/feedback/States'
import { platformNavigation } from '@/lib/navigation'
import en from '@/locales/en'
import ar from '@/locales/ar'

describe('foundation dashboard system states', () => {
  it('renders required accessible states without serious axe violations', async () => {
    const { container } = render(
      <main>
        <LoadingState />
        <EmptyState title="Empty" />
        <ErrorState title="Error" />
        <ForbiddenState />
        <ConflictState />
        <QueuedState />
      </main>,
    )

    expect(screen.getAllByRole('status')).toHaveLength(2)
    expect(screen.getAllByRole('alert')).toHaveLength(3)
    const result = await axe.run(container, { rules: { 'color-contrast': { enabled: false } } })
    expect(result.violations.filter((violation) => ['critical', 'serious'].includes(violation.impact || ''))).toEqual([])
  })

  it('keeps navigation foundation-only and Arabic/English catalogs equivalent', () => {
    expect(platformNavigation.map((item) => item.href).join(' ')).not.toMatch(/ticket|payment|wallet|kiosk|scanner/)
    expect(Object.keys(ar)).toEqual(Object.keys(en))
    expect(ar.overview).not.toBe(en.overview)
  })
})
