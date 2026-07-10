import { fireEvent, render, screen } from '@testing-library/react'
import { describe, expect, it, vi } from 'vitest'
import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import ActionDropdown from '@/components/tables/ActionDropdown'

vi.mock('@/hooks/useLocale', () => ({
  useLocale: () => ({ locale: 'en', direction: 'ltr' }),
}))

describe('design system: StatusBadge', () => {
  it('renders known status labels', () => {
    render(<StatusBadge status="published" />)
    expect(screen.getByText(/published/i)).toBeInTheDocument()
  })

  it('renders failed status variant', () => {
    render(<StatusBadge status="failed" size="sm" />)
    expect(screen.getByText(/failed/i)).toBeInTheDocument()
  })
})

describe('design system: DataTable', () => {
  it('renders empty state when no rows', () => {
    render(
      <DataTable
        columns={[{ key: 'name', header: 'Name' }]}
        rows={[]}
        emptyMessage="No records"
        getRowKey={(row) => String((row as { id?: string }).id ?? 'row')}
      />,
    )
    expect(screen.getByText('No records')).toBeInTheDocument()
  })
})

describe('design system: ActionDropdown', () => {
  it('opens menu and triggers action', () => {
    const onEdit = vi.fn()

    render(
      <ActionDropdown
        label="Actions"
        items={[{ key: 'edit', label: 'Edit', onSelect: onEdit }]}
      />,
    )

    fireEvent.click(screen.getByRole('button', { name: 'Actions' }))
    fireEvent.click(screen.getByRole('menuitem', { name: 'Edit' }))
    expect(onEdit).toHaveBeenCalledOnce()
  })
})
