import BrandedLoader from '@/components/loaders/BrandedLoader'
import { useLocale } from '@/hooks/useLocale'

type GlobalRouteLoaderProps = {
  active?: boolean
}

export default function GlobalRouteLoader({ active = false }: GlobalRouteLoaderProps) {
  const { t } = useLocale()

  if (!active) {
    return null
  }

  return (
    <div className="global-route-loader" role="status" aria-live="polite" aria-busy="true" aria-label={t('loadingPage')}>
      <BrandedLoader label={t('loadingPage')} />
    </div>
  )
}
