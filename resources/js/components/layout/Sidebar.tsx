import { Link, usePage } from '@inertiajs/react'
import { filterNavigation, platformNavigation, resolveLabel } from '@/lib/navigation'
import { tenantRootNavigation } from '@/lib/tenant-navigation'
import { useLocale } from '@/hooks/useLocale'
import en from '@/locales/en'
import ar from '@/locales/ar'
import type { PermissionMap } from '@/types/shell'

export default function Sidebar() {
  const { locale } = useLocale()
  const messages = locale === 'ar' ? ar : en
  const can = (usePage().props.can ?? {}) as PermissionMap
  const items = filterNavigation([...platformNavigation, ...tenantRootNavigation], can)

  return (
    <aside className="border-b border-slate-200 bg-white p-5 dark:border-slate-800 dark:bg-slate-900 lg:border-b-0 lg:border-e">
      <strong className="text-lg">{messages.appName}</strong>
      <nav aria-label="Dashboard navigation" className="mt-6 space-y-1">
        {items.map((item) => (
          <div key={item.key}>
            <Link
              href={item.href}
              className="block rounded-lg px-3 py-2 hover:bg-slate-100 focus-visible:outline focus-visible:outline-2 dark:hover:bg-slate-800"
            >
              {resolveLabel(messages, item.label)}
            </Link>
            {item.children && item.children.length > 0 && (
              <div className="ms-3 mt-1 space-y-1 border-s border-slate-200 ps-3 dark:border-slate-700">
                {item.children.map((child) => (
                  <Link
                    key={child.key}
                    href={child.href}
                    className="block rounded-lg px-3 py-1.5 text-sm hover:bg-slate-100 dark:hover:bg-slate-800"
                  >
                    {resolveLabel(messages, child.label)}
                  </Link>
                ))}
              </div>
            )}
          </div>
        ))}
      </nav>
    </aside>
  )
}
