import { Component, type ErrorInfo, type PropsWithChildren } from 'react'
import { Head } from '@inertiajs/react'
import { Sidebar, Topbar } from '@/components/layout'
import GlobalRouteLoader from '@/components/loaders/GlobalRouteLoader'
import ProductTour from '@/components/tour/ProductTour'
import { ShellLayoutProvider, useShellLayout } from '@/contexts/ShellLayoutContext'
import { useLocale } from '@/hooks/useLocale'
import { useSiteBranding } from '@/hooks/useSiteBranding'
import en from '@/locales/en'
import ar from '@/locales/ar'

type ErrorBoundaryState = { hasError: boolean }

class RouteErrorBoundary extends Component<PropsWithChildren, ErrorBoundaryState> {
  state: ErrorBoundaryState = { hasError: false }

  static getDerivedStateFromError(): ErrorBoundaryState {
    return { hasError: true }
  }

  componentDidCatch(error: Error, info: ErrorInfo) {
    console.error('Dashboard route error', error, info)
  }

  render() {
    if (this.state.hasError) {
      return (
        <section role="alert" className="state-panel m-6">
          <h2 className="text-lg font-semibold">Something went wrong</h2>
          <p className="mt-2 text-slate-600 dark:text-slate-300">
            This page could not be rendered. Try refreshing or return to the overview.
          </p>
        </section>
      )
    }

    return this.props.children
  }
}

type DashboardLayoutProps = PropsWithChildren<{
  title?: string
}>

function DashboardShell({ children, title }: DashboardLayoutProps) {
  const { locale, direction } = useLocale()
  const { sidebarCollapsed } = useShellLayout()
  const { appName } = useSiteBranding()
  const messages = locale === 'ar' ? ar : en

  return (
    <div
      dir={direction}
      lang={locale}
      className={`ta-shell ${sidebarCollapsed ? 'ta-shell-collapsed' : ''}`}
    >
      <Head title={title ?? appName ?? messages.appName} />
      <a href="#main-content" className="skip-link">Skip to content</a>
      <GlobalRouteLoader />
      <ProductTour />
      <Sidebar />
      <div className="ta-main-column">
        <Topbar />
        <main id="main-content" tabIndex={-1} className="p-4 sm:p-6 lg:p-8">
          <RouteErrorBoundary>{children}</RouteErrorBoundary>
        </main>
      </div>
    </div>
  )
}

export default function DashboardLayout({ children, title }: DashboardLayoutProps) {
  return (
    <ShellLayoutProvider>
      <DashboardShell title={title}>{children}</DashboardShell>
    </ShellLayoutProvider>
  )
}
