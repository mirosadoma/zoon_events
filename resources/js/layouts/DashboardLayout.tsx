import { Component, type ErrorInfo, type PropsWithChildren } from 'react'
import { Head } from '@inertiajs/react'
import { Sidebar, Topbar } from '@/components/layout'
import { Toaster } from '@/components/feedback'
import GlobalRouteLoader from '@/components/loaders/GlobalRouteLoader'
import { ToastProvider } from '@/hooks/useToast'
import { useLocale } from '@/hooks/useLocale'
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

export default function DashboardLayout({ children, title }: DashboardLayoutProps) {
  const { locale, direction } = useLocale()
  const messages = locale === 'ar' ? ar : en

  return (
    <ToastProvider>
      <div dir={direction} lang={locale} className="min-h-screen bg-slate-50 text-slate-950 dark:bg-slate-950 dark:text-slate-50">
        <Head title={title ?? messages.appName} />
        <a href="#main-content" className="skip-link">Skip to content</a>
        <GlobalRouteLoader />
        <div className="mx-auto grid min-h-screen lg:grid-cols-[17rem_1fr]">
          <Sidebar />
          <div>
            <Topbar />
            <main id="main-content" tabIndex={-1} className="p-4 sm:p-6 lg:p-8">
              <RouteErrorBoundary>{children}</RouteErrorBoundary>
            </main>
          </div>
        </div>
        <Toaster />
      </div>
    </ToastProvider>
  )
}
