import '../css/app.css'

import { createInertiaApp } from '@inertiajs/react'
import type { ComponentType } from 'react'
import { createRoot } from 'react-dom/client'
import { Toaster } from '@/components/feedback'
import GlobalRouteLoaderHost from '@/components/loaders/GlobalRouteLoaderHost'
import LocaleDocumentSync from '@/components/routing/LocaleDocumentSync'
import { NavigationLoadingProvider } from '@/contexts/NavigationLoadingContext'
import { ToastProvider } from '@/contexts/ToastContext'
import { installReloadOnBrowserHistory } from '@/lib/reloadOnBrowserHistory'

const pages = import.meta.glob<{ default: ComponentType }>(
  ['./pages/**/*.tsx', '!./pages/**/__tests__/**', '!./pages/**/*.test.tsx'],
  { eager: true },
)

const el = document.getElementById('app')

if (!el) {
  throw new Error('App element not found')
}

function initTheme(): void {
  const stored = localStorage.getItem('theme')
  const theme = stored === 'light' || stored === 'dark' || stored === 'system' ? stored : 'system'
  const dark = theme === 'dark' || (theme === 'system' && window.matchMedia('(prefers-color-scheme: dark)').matches)
  document.documentElement.classList.toggle('dark', dark)
}

initTheme()

const initialPage = JSON.parse(el.getAttribute('data-page')!)

createInertiaApp({
  page: initialPage,

  resolve: (name) => {
    const page = pages[`./pages/${name}.tsx`]

    if (!page) {
      throw new Error(`Unknown Inertia page: ${name}`)
    }

    const Page = page.default

    return function ResolvedPage(pageProps: Record<string, unknown>) {
      return (
        <>
          <LocaleDocumentSync />
          <GlobalRouteLoaderHost />
          <Page {...pageProps} />
        </>
      )
    }
  },

  setup({ el, App, props }) {
    installReloadOnBrowserHistory()

    createRoot(el).render(
      <ToastProvider>
        <NavigationLoadingProvider>
          <App {...props} />
        </NavigationLoadingProvider>
        <Toaster />
      </ToastProvider>,
    )
  },
})
