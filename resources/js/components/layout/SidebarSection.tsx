import { useState } from 'react'
import { usePage } from '@inertiajs/react'
import { ChevronDown } from 'lucide-react'
import { clsx } from 'clsx'
import type { NavigationGroup } from '@/types/shell'
import { isNavItemActive, resolveLabel } from '@/lib/navigation'
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
  const { url } = usePage()
  const containsActiveItem = group.items.some((item) => isNavItemActive(url, item.href))
  const [override, setOverride] = useState<boolean | null>(null)
  const [lastUrl, setLastUrl] = useState(url)

  if (lastUrl !== url) {
    setLastUrl(url)
    setOverride(null)
  }

  const open = override ?? (defaultOpen || containsActiveItem)

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
    <div className="mt-1.5">
      <button
        type="button"
        className="ta-sidebar-section-toggle"
        onClick={() => setOverride(open ? false : true)}
        aria-expanded={open}
      >
        <span className={clsx('ta-nav-group-title mb-0 mt-0', eventContext && 'text-[var(--brand)]')}>
          {resolveLabel(messages, group.label)}
        </span>
        <ChevronDown className={clsx('h-3.5 w-3.5 text-[var(--muted)] transition', open && 'rotate-180')} />
      </button>
      {open ? (
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
      ) : null}
    </div>
  )
}
