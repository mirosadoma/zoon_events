import { Component, type ErrorInfo, type PropsWithChildren } from 'react'
import { Head } from '@inertiajs/react'
import { Sidebar, Topbar } from '@/components/layout'
import ProductTour from '@/components/tour/ProductTour'
import { ShellLayoutProvider, useShellLayout } from '@/contexts/ShellLayoutContext'
import { useLocale } from '@/hooks/useLocale'
import { useSiteBranding } from '@/hooks/useSiteBranding'
import en from '@/locales/en'
import ar from '@/locales/ar'

type ErrorBoundaryState = { hasError: boolean }

type RouteErrorBoundaryProps = PropsWithChildren<{
  title: string
  detail: string
}>

class RouteErrorBoundary extends Component<RouteErrorBoundaryProps, ErrorBoundaryState> {
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
          <h2 className="text-lg font-semibold">{this.props.title}</h2>
          <p className="mt-2 text-slate-600 dark:text-slate-300">
            {this.props.detail}
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
  const { locale, direction, t } = useLocale()
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
      <a href="#main-content" className="skip-link">{t('skipToContent')}</a>
      <ProductTour />
      <Sidebar />
      <div className="ta-main-column">
        <Topbar />
        <main id="main-content" tabIndex={-1} className="p-4 sm:p-6 lg:p-8">
          <RouteErrorBoundary title={t('somethingWentWrong')} detail={t('pageRenderError')}>
            {children}
          </RouteErrorBoundary>
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
