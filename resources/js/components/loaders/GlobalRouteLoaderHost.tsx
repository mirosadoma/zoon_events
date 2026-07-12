import GlobalRouteLoader from '@/components/loaders/GlobalRouteLoader'
import { useNavigationLoading } from '@/contexts/NavigationLoadingContext'

export default function GlobalRouteLoaderHost() {
  const loading = useNavigationLoading()

  return <GlobalRouteLoader active={loading} />
}
