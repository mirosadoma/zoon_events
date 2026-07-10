import { createInertiaApp } from '@inertiajs/react'
import createServer from '@inertiajs/react/server'
import type { ComponentType } from 'react'
import { renderToString } from 'react-dom/server'

const pages = import.meta.glob<{ default: ComponentType }>('./pages/**/*.tsx')

createServer((page) =>
  createInertiaApp({
    page,
    render: renderToString,
    resolve: async (name) => {
      const load = pages[`./pages/${name}.tsx`]
      if (!load) throw new Error(`Unknown Inertia page: ${name}`)
      return (await load()).default
    },
    setup: ({ App, props }) => <App {...props} />,
  }),
)
