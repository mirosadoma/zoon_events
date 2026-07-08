import '../css/app.css'

import { createInertiaApp } from '@inertiajs/react'
import type { ComponentType } from 'react'
import { createRoot } from 'react-dom/client'

const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx', {
  eager: true,
})

const el = document.getElementById('app')

if (!el) {
  throw new Error('App element not found')
}

// 👇 ده الحل الحقيقي
const initialPage = JSON.parse(el.getAttribute('data-page')!)

createInertiaApp({
  page: initialPage,

  resolve: (name) => {
    const page = pages[`./pages/${name}.tsx`]

    if (!page) {
      throw new Error(`Unknown Inertia page: ${name}`)
    }

    return page.default
  },

  setup({ el, App, props }) {
    createRoot(el).render(<App {...props} />)
  },
})
