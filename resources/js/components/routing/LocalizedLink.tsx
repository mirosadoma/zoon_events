import { Link, type InertiaLinkProps } from '@inertiajs/react'
import { useLocale } from '@/hooks/useLocale'
import { localizedPath } from '@/lib/localePath'

export default function LocalizedLink({ href, ...props }: InertiaLinkProps) {
  const { locale } = useLocale()
  const resolvedHref = typeof href === 'string' ? localizedPath(locale, href) : href

  return <Link href={resolvedHref} {...props} />
}
