import { useState } from 'react'
import { ChevronDown } from 'lucide-react'
import { clsx } from 'clsx'
import type { NavigationGroup } from '@/types/shell'
import { resolveLabel } from '@/lib/navigation'
import SidebarNavLink from './SidebarNavLink'
import type { AppLocale } from '@/lib/localePath'

import type en from '@/locales/en'

export type LocaleMessages = typeof en

type SidebarSectionProps = {
  group: NavigationGroup
  messages: LocaleMessages
  defaultOpen?: boolean
  collapsed?: boolean
  locale: AppLocale
  eventContext?: boolean
}

export default function SidebarSection({
  group,
  messages,
  defaultOpen = true,
  collapsed = false,
  locale,
  eventContext = false,
}: SidebarSectionProps) {
  const [open, setOpen] = useState(defaultOpen)

  if (collapsed) {
    return (
      <nav className="mt-2 space-y-0.5" aria-label={resolveLabel(messages, group.label)}>
        {group.items.map((item) => (
          <SidebarNavLink
            key={item.key}
            item={item}
            label={resolveLabel(messages, item.label)}
            collapsed
            locale={locale}
            eventContext={eventContext}
          />
        ))}
      </nav>
    )
  }

  return (
    <div className="mt-2">
      <button
        type="button"
        className="flex w-full items-center justify-between px-3 py-1 text-start"
        onClick={() => setOpen((value) => !value)}
        aria-expanded={open}
      >
        <span className={clsx('ta-nav-group-title mb-0 mt-0', eventContext && 'text-[var(--brand)]')}>
          {resolveLabel(messages, group.label)}
        </span>
        <ChevronDown className={clsx('h-4 w-4 text-[var(--muted)] transition', open && 'rotate-180')} />
      </button>
      {open && (
        <nav className="mt-1 space-y-0.5" aria-label={resolveLabel(messages, group.label)}>
          {group.items.map((item) => (
            <SidebarNavLink
              key={item.key}
              item={item}
              label={resolveLabel(messages, item.label)}
              locale={locale}
              eventContext={eventContext}
            />
          ))}
        </nav>
      )}
    </div>
  )
}
