import PermissionGate from '@/components/layout/PermissionGate'
import { useLocale } from '@/hooks/useLocale'
import type { OperationalLink } from '@/types/phase6'

type Props = {
  links?: OperationalLink[]
  can?: Record<string, boolean>
}

export default function OperationalResourceLinks({ links = [], can = {} }: Props) {
  const { t } = useLocale()
  const visibleLinks = links.filter((link) => can[link.permission] === true)

  return (
    <section className="ta-card space-y-3" aria-label={t('operationalLinks')}>
      <h3 className="text-lg font-semibold text-[var(--ink)]">{t('operationalLinks')}</h3>
      {visibleLinks.length === 0 ? (
        <p className="text-sm text-[var(--muted)]">{t('noOperationalLinks')}</p>
      ) : (
        <ul className="space-y-2">
          {visibleLinks.map((link) => (
            <li key={link.key}>
              <PermissionGate permission={link.permission}>
                <a href={link.href} className="font-medium text-[var(--brand)] hover:underline">
                  {link.label}
                </a>
              </PermissionGate>
            </li>
          ))}
        </ul>
      )}
    </section>
  )
}
