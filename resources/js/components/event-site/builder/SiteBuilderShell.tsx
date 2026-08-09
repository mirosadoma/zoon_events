import { Head } from '@inertiajs/react'
import type { ReactNode } from 'react'

type Props = {
  title: string
  /** UI chrome direction (dashboard locale), not the canvas edit locale. */
  uiLocale?: 'en' | 'ar'
  topBar: ReactNode
  leftPanel: ReactNode
  canvas: ReactNode
  rightPanel: ReactNode
  overlays?: ReactNode
}

export default function SiteBuilderShell({
  title,
  uiLocale = 'en',
  topBar,
  leftPanel,
  canvas,
  rightPanel,
  overlays,
}: Props) {
  const uiDir = uiLocale === 'ar' ? 'rtl' : 'ltr'

  return (
    <div
      className="site-builder-root fixed inset-0 z-50 flex flex-col bg-[#1a1a2e] text-white"
      dir={uiDir}
      lang={uiLocale}
      style={{ direction: uiDir }}
    >
      <Head title={title} />
      {topBar}
      <div className="flex min-h-0 flex-1">
        <aside className="flex h-full min-h-0 w-[210px] shrink-0 flex-col border-e border-white/10 bg-[#16213e]">
          <div className="flex h-full min-h-0 flex-col">{leftPanel}</div>
        </aside>
        <main className="relative min-w-0 flex-1 overflow-hidden bg-[#0f0f1a]">
          {canvas}
        </main>
        <aside className="flex h-full min-h-0 w-[280px] shrink-0 flex-col border-s border-white/10 bg-[#16213e]">
          <div className="flex h-full min-h-0 flex-col">{rightPanel}</div>
        </aside>
      </div>
      {overlays}
    </div>
  )
}
