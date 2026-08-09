import type { CSSProperties } from 'react'
import AssistantPanel from '@/components/ai/AssistantPanel'
import BlockCanvas, { type CanvasBlock } from '@/components/event-site/BlockCanvas'
import { backgroundStyle, backgroundOverlayStyle, type SiteBackground } from '@/lib/siteBackgroundStyle'
import { localeDirection } from '@/lib/localeDirection'
import { sitePageHref } from '@/lib/siteHref'

type LocalizedString = { en: string; ar: string }

type NavPage = {
  id: string
  slug: string
  title: string
  is_home: boolean
}

type Theme = {
  colors?: Record<string, string>
  logo_url?: string
}

type SiteLogo = {
  url?: string
  path?: string
  position?: 'left' | 'center' | 'right'
  size?: 'sm' | 'md' | 'lg'
}

type SiteData = {
  event: {
    slug: string
    name: LocalizedString
    start_at: string
    end_at: string
    timezone: string
  }
  theme: Theme | null
  blocks: CanvasBlock[]
  page_mode?: 'single' | 'multi'
  pages?: NavPage[]
  current_page?: {
    id: string
    slug: string
    title: string
    background?: SiteBackground
  }
  site_background?: SiteBackground
  logo?: SiteLogo
  site_logo?: string
  assistant: {
    enabled: boolean
    display_name: LocalizedString
    greeting: LocalizedString
  }
  register_url: string
  site_base_url?: string
}

type Props = {
  locale: 'en' | 'ar'
  site: SiteData
}

export default function EventSite({ locale, site }: Props) {
  const direction = localeDirection(locale)
  const brandColor = site.theme?.colors?.primary
  const pages = site.pages || []
  const pageMode = site.page_mode || 'single'
  const currentPageId = site.current_page?.id || 'home'
  const pageBackground = site.current_page?.background
  const siteBackground = site.site_background
  const effectiveBackground =
    pageBackground?.type && pageBackground.type !== 'none' ? pageBackground : siteBackground
  const hasPageBg = effectiveBackground?.type && effectiveBackground.type !== 'none'
  // Header block already provides site chrome — skip the auto multi-page bar to avoid double nav.
  const hasHeaderBlock = site.blocks.some((block) => block.type === 'header')
  const showPageNav = pageMode === 'multi' && pages.length > 1 && !hasHeaderBlock
  const siteBaseUrl =
    site.site_base_url ||
    `/${locale}/e/${site.event.slug}`

  const style: CSSProperties & Record<string, string> = {
    direction,
    ...backgroundStyle(effectiveBackground),
  }
  const overlayStyle = backgroundOverlayStyle(effectiveBackground)
  if (brandColor) {
    style['--brand'] = brandColor
  }

  return (
    <div
      dir={direction}
      lang={locale}
      style={style}
      className={`relative min-h-screen ${hasPageBg ? '' : 'bg-background'}`}
    >
      {overlayStyle && (
        <div className="pointer-events-none absolute inset-0 z-0" style={overlayStyle} />
      )}
      <div className="relative z-[1]">
      {showPageNav && (
        <nav className="border-b border-[var(--border)] bg-muted/40">
          <div className="mx-auto flex max-w-6xl gap-1 overflow-x-auto px-4 py-2">
            {pages.map((page) => {
              const href = sitePageHref(siteBaseUrl, page)
              const active = page.id === currentPageId

              return (
                <a
                  key={page.id}
                  href={href}
                  className={`whitespace-nowrap rounded-lg px-4 py-2 text-sm transition-colors ${
                    active ? 'bg-primary text-primary-foreground' : 'hover:bg-muted'
                  }`}
                >
                  {page.title}
                </a>
              )
            })}
          </div>
        </nav>
      )}

      <BlockCanvas
        blocks={site.blocks}
        locale={locale}
        registerUrl={site.register_url}
        siteBaseUrl={siteBaseUrl}
        eventSlug={site.event.slug}
        currentPageId={currentPageId}
      />

      {site.assistant.enabled && (
        <AssistantPanel
          eventSlug={site.event.slug}
          locale={locale}
          config={site.assistant}
          registerUrl={site.register_url}
        />
      )}
      </div>
    </div>
  )
}
