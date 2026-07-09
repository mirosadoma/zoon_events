import { MoreHorizontal } from 'lucide-react'
import { useEffect, useId, useRef, useState } from 'react'
import { clsx } from 'clsx'

export type ActionItem = {
  key: string
  label: string
  onSelect: () => void
  tone?: 'default' | 'danger'
  disabled?: boolean
}

type ActionDropdownProps = {
  items: ActionItem[]
  label?: string
}

export default function ActionDropdown({ items, label = 'Actions' }: ActionDropdownProps) {
  const [open, setOpen] = useState(false)
  const menuId = useId()
  const rootRef = useRef<HTMLDivElement>(null)

  useEffect(() => {
    if (!open) {
      return
    }

    const onPointerDown = (event: MouseEvent) => {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false)
      }
    }

    const onKeyDown = (event: KeyboardEvent) => {
      if (event.key === 'Escape') {
        setOpen(false)
      }
    }

    document.addEventListener('mousedown', onPointerDown)
    document.addEventListener('keydown', onKeyDown)

    return () => {
      document.removeEventListener('mousedown', onPointerDown)
      document.removeEventListener('keydown', onKeyDown)
    }
  }, [open])

  if (items.length === 0) {
    return null
  }

  return (
    <div ref={rootRef} className="relative inline-flex">
      <button
        type="button"
        className="button-secondary p-2"
        aria-haspopup="menu"
        aria-expanded={open}
        aria-controls={menuId}
        onClick={() => setOpen((value) => !value)}
      >
        <MoreHorizontal className="h-4 w-4" aria-hidden />
        <span className="sr-only">{label}</span>
      </button>
      {open && (
        <div
          id={menuId}
          role="menu"
          className="absolute end-0 z-20 mt-1 min-w-[10rem] rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)] py-1 shadow-lg"
        >
          {items.map((item) => (
            <button
              key={item.key}
              type="button"
              role="menuitem"
              disabled={item.disabled}
              className={clsx(
                'block w-full px-3 py-2 text-start text-sm hover:bg-[var(--brand-soft)] disabled:opacity-50',
                item.tone === 'danger' && 'text-red-700 hover:bg-red-50',
              )}
              onClick={() => {
                setOpen(false)
                item.onSelect()
              }}
            >
              {item.label}
            </button>
          ))}
        </div>
      )}
    </div>
  )
}
