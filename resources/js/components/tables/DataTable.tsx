import type { ReactNode } from 'react'

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
}

export default function DataTable<T extends Record<string, unknown>>({
  columns,
  rows,
  emptyMessage = 'No records found.',
  getRowKey,
}: DataTableProps<T>) {
  if (rows.length === 0) {
    return <p className="text-slate-600 dark:text-slate-300">{emptyMessage}</p>
  }

  return (
    <div className="overflow-x-auto rounded-xl border border-slate-200 dark:border-slate-800">
      <table className="min-w-full text-sm">
        <thead className="bg-slate-50 dark:bg-slate-900">
          <tr>
            {columns.map((column) => (
              <th key={column.key} scope="col" className="px-4 py-3 text-start font-semibold">
                {column.header}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row) => (
            <tr key={getRowKey(row)} className="border-t border-slate-200 dark:border-slate-800">
              {columns.map((column) => (
                <td key={column.key} className="px-4 py-3">
                  {column.render ? column.render(row) : String(row[column.key] ?? '')}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
