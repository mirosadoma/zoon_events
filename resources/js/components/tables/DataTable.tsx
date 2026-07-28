import type { ReactNode } from 'react'
import { useLocale } from '@/hooks/useLocale'

type Column<T> = {
  key: string
  header: string
  render?: (row: T) => ReactNode
}

type DataTableProps<T extends Record<string, unknown>> = {
  columns: Column<T>[]
  rows: T[]
  emptyMessage?: string
  getRowKey: (row: T) => string
  title?: string
  toolbar?: ReactNode
  onRowClick?: (row: T) => void
  selectedRowKey?: string | null
}

export default function DataTable<T extends Record<string, unknown>>({
  columns,
  rows,
  emptyMessage,
  getRowKey,
  title,
  toolbar,
  onRowClick,
  selectedRowKey = null,
}: DataTableProps<T>) {
  const { t } = useLocale()
  const resolvedEmptyMessage = emptyMessage ?? t('noRecordsFound')

  if (rows.length === 0) {
    return (
      <div className="ta-card">
        {title && <h2 className="mb-2 text-lg font-semibold">{title}</h2>}
        {toolbar}
        <p className="py-6 text-center text-[var(--muted)]">{resolvedEmptyMessage}</p>
      </div>
    )
  }

  return (
    <div className="ta-card overflow-hidden p-0">
      {(title || toolbar) && (
        <div className="ta-table-toolbar">
          {title ? <h2 className="text-lg font-semibold">{title}</h2> : <span />}
          {toolbar}
        </div>
      )}
      <div className="ta-table-wrap">
        <table className="ta-table">
          <thead>
            <tr>
              {columns.map((column) => (
                <th key={column.key} scope="col">
                  {column.header}
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {rows.map((row) => {
              const rowKey = getRowKey(row)
              const selected = selectedRowKey !== null && selectedRowKey === rowKey

              return (
                <tr
                  key={rowKey}
                  className={[
                    'transition-colors',
                    onRowClick ? 'cursor-pointer' : '',
                    selected ? 'ta-table-row-selected' : '',
                  ].filter(Boolean).join(' ') || undefined}
                  onClick={onRowClick ? () => onRowClick(row) : undefined}
                  aria-selected={onRowClick ? selected : undefined}
                >
                  {columns.map((column) => (
                    <td key={column.key} className="text-start">
                      {column.render ? column.render(row) : String(row[column.key] ?? '')}
                    </td>
                  ))}
                </tr>
              )
            })}
          </tbody>
        </table>
      </div>
    </div>
  )
}
