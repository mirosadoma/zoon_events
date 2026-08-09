import { useState } from 'react'
import { Menu, X } from 'lucide-react'
import {
  blockShellClasses,
  blockShellOverlay,
  blockShellStyle,
} from '@/lib/siteBlockStyle'
import type { SiteBackground } from '@/lib/siteBackgroundStyle'
import { formatCssLength, parseCssLength } from '@/lib/cssLength'
import { isExternalHref, resolveSiteHref } from '@/lib/siteHref'

type NavLink = {
  id?: string
  label?: string
  label_en?: string
  label_ar?: string
  href?: string
}

function linkLabel(link: NavLink, locale: 'en' | 'ar'): string {
  if (locale === 'ar') {
    return link.label_ar || link.label || ''
  }
  return link.label_en || link.label || ''
}

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs?: Record<string, unknown>
  locale: 'en' | 'ar'
  registerUrl?: string
  siteBaseUrl?: string
}

const SIZE_HEIGHTS: Record<string, number> = {
  sm: 32,
  md: 48,
  lg: 72,
}

function resolveHref(href: string, registerUrl?: string, siteBaseUrl?: string): string {
  return resolveSiteHref(href, { registerUrl, siteBaseUrl })
}

function hasSectionBackground(options: Record<string, unknown>): boolean {
  const bg = options.background
  return Boolean(
    bg &&
      typeof bg === 'object' &&
      !Array.isArray(bg) &&
      (bg as SiteBackground).type &&
      (bg as SiteBackground).type !== 'none',
  )
}

function headerStyleShell(style: string, customBg: boolean): string {
  if (style === 'transparent') {
    return 'bg-transparent absolute inset-x-0 top-0 z-30 text-white'
  }
  if (customBg) {
    if (style === 'minimal') return 'border-b border-[var(--border)] backdrop-blur-sm'
    if (style === 'centered') return 'border-b border-[var(--border)]'
    return 'border-b border-[var(--border)] shadow-sm'
  }
  if (style === 'minimal') return 'bg-background/80 backdrop-blur border-b border-[var(--border)]'
  if (style === 'centered') return 'bg-[var(--surface)] border-b border-[var(--border)]'
  return 'bg-[var(--surface)] border-b border-[var(--border)] shadow-sm'
}

function maxWidthClass(maxWidth: string): string {
  switch (maxWidth) {
    case '4xl':
      return 'max-w-4xl'
    case '5xl':
      return 'max-w-5xl'
    case '7xl':
      return 'max-w-7xl'
    case 'full':
      return 'max-w-none'
    case '6xl':
    default:
      return 'max-w-6xl'
  }
}

function zoneGapClass(gap: string): string {
  switch (gap) {
    case 'sm':
      return 'gap-2'
    case 'lg':
      return 'gap-6'
    case 'xl':
      return 'gap-8'
    case 'md':
    default:
      return 'gap-4'
  }
}

function navGapClass(gap: string): string {
  switch (gap) {
    case 'sm':
      return 'gap-3'
    case 'lg':
      return 'gap-8'
    case 'md':
    default:
      return 'gap-5'
  }
}

function navAlignClass(align: string): string {
  if (align === 'start') return 'justify-start'
  if (align === 'center') return 'justify-center'
  return 'justify-end'
}

export default function HeaderRenderer({ content, options, refs = {}, locale, registerUrl, siteBaseUrl }: Props) {
  const [open, setOpen] = useState(false)
  const brand = typeof content.brand === 'string' ? content.brand : ''
  const ctaLabel = typeof content.cta_label === 'string' ? content.cta_label : 'Register'
  const style = typeof options.style === 'string' ? options.style : 'solid'
  const sticky = options.sticky !== false
  const showCta = options.show_cta !== false
  const mobileMenu = options.mobile_menu !== false
  const showLogo = options.show_logo !== false
  const showBrandText = options.show_brand_text !== false
  const links = Array.isArray(content.links) ? (content.links as NavLink[]) : []

  const logoUrl = typeof refs.logo_url === 'string' ? refs.logo_url : (typeof options.logo_url === 'string' ? options.logo_url : '')
  const logoPosition = typeof options.logo_position === 'string' ? options.logo_position : 'left'
  const logoSize = typeof options.logo_size === 'string' ? options.logo_size : 'md'
  const logoMaxHeight = formatCssLength(
    parseCssLength(
      options.logo_max_height,
      options.logo_max_height_unit,
      { value: SIZE_HEIGHTS[logoSize] || 48, unit: 'px' },
    ),
  )

  // Layout: prefer explicit layout option; fall back to legacy logo_position / style=centered
  let layout = typeof options.layout === 'string' ? options.layout : ''
  if (!layout) {
    if (style === 'centered' || logoPosition === 'center') layout = 'stacked_center'
    else if (logoPosition === 'right' || logoPosition === 'end') layout = 'brand_end'
    else layout = 'brand_start'
  }

  const gridMaxWidth = typeof options.grid_max_width === 'string' ? options.grid_max_width : '6xl'
  const zoneGap = typeof options.zone_gap === 'string' ? options.zone_gap : 'md'
  const navGap = typeof options.nav_gap === 'string' ? options.nav_gap : 'md'
  const navAlign = typeof options.nav_align === 'string' ? options.nav_align : 'end'
  const ctaPlacement = typeof options.cta_placement === 'string' ? options.cta_placement : 'with_nav'

  const customBg = hasSectionBackground(options)
  const shellCls = blockShellClasses(options)
  const shellStyle = blockShellStyle(options)
  const shellOverlay = blockShellOverlay(options)
  const shell = headerStyleShell(style, customBg)

  const hrefFor = (href: string) => resolveHref(href, registerUrl, siteBaseUrl)

  const renderLogo = () => {
    if (!showLogo || !logoUrl) return null
    return (
      <img
        src={logoUrl}
        alt={brand || 'Logo'}
        className="object-contain"
        style={{ maxHeight: logoMaxHeight }}
      />
    )
  }

  const renderBrand = () => {
    const homeHref = siteBaseUrl ? resolveHref('/', registerUrl, siteBaseUrl) : '#top'
    const showText = showBrandText && Boolean(brand) && layout !== 'stacked_center'
    if (showLogo && logoUrl) {
      return (
        <a href={homeHref} className="inline-flex items-center gap-2 shrink-0">
          {renderLogo()}
          {showText && (
            <span className="text-lg font-semibold tracking-tight text-[var(--ink)]">{brand}</span>
          )}
        </a>
      )
    }
    return (
      <a href={homeHref} className="inline-flex shrink-0 text-lg font-semibold tracking-tight text-[var(--ink)]">
        {brand || 'Brand'}
      </a>
    )
  }

  const renderCta = (className = '') => {
    if (!showCta) return null
    return (
      <a
        href={hrefFor(typeof options.cta_href === 'string' ? options.cta_href : 'registration')}
        className={`shrink-0 rounded-md bg-[var(--brand)] px-3 py-2 text-sm font-semibold text-white ${className}`}
      >
        {ctaLabel}
      </a>
    )
  }

  const renderNavLinks = (className = '') => (
    <div className={`hidden items-center md:flex ${navGapClass(navGap)} ${className}`}>
      {links.map((link, index) => {
        const href = hrefFor(String(link.href ?? '#'))
        const external = isExternalHref(href)
        return (
          <a
            key={link.id ?? index}
            href={href}
            className="text-sm font-medium text-[var(--muted)] hover:text-[var(--ink)]"
            {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
          >
            {linkLabel(link, locale)}
          </a>
        )
      })}
      {ctaPlacement === 'with_nav' && renderCta()}
    </div>
  )

  const renderMobileToggle = (className = '') =>
    mobileMenu ? (
      <button
        type="button"
        className={`inline-flex rounded-md border border-[var(--border)] p-2 md:hidden ${className}`}
        aria-label="Menu"
        onClick={() => setOpen((value) => !value)}
      >
        {open ? <X className="h-5 w-5" /> : <Menu className="h-5 w-5" />}
      </button>
    ) : null

  const brandWithOptionalCta = (
    <div className="inline-flex items-center gap-3 shrink-0">
      {renderBrand()}
      {ctaPlacement === 'beside_brand' && (
        <span className="hidden md:inline-flex">{renderCta()}</span>
      )}
    </div>
  )

  const renderLinkRow = (justifyClass: string) => (
    <nav className={`hidden min-w-0 items-center md:flex ${navGapClass(navGap)} ${justifyClass}`}>
      {links.map((link, index) => {
        const href = hrefFor(String(link.href ?? '#'))
        const external = isExternalHref(href)
        return (
          <a
            key={link.id ?? index}
            href={href}
            className="whitespace-nowrap text-sm font-medium text-[var(--muted)] hover:text-[var(--ink)]"
            {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
          >
            {linkLabel(link, locale)}
          </a>
        )
      })}
      {ctaPlacement === 'with_nav' && renderCta()}
    </nav>
  )

  const renderBar = () => {
    const zGap = zoneGapClass(zoneGap)

    if (layout === 'stacked_center') {
      return (
        <div className={`flex w-full flex-col items-center ${zGap}`}>
          <div className="relative flex w-full items-center justify-center">
            {renderBrand()}
            {renderMobileToggle('absolute end-0 top-1/2 -translate-y-1/2')}
          </div>
          <div className={`hidden w-full items-center md:flex ${navGapClass(navGap)} justify-center`}>
            {links.map((link, index) => {
              const href = hrefFor(String(link.href ?? '#'))
              const external = isExternalHref(href)
              return (
                <a
                  key={link.id ?? index}
                  href={href}
                  className="text-sm font-medium text-[var(--muted)] hover:text-[var(--ink)]"
                  {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                >
                  {linkLabel(link, locale)}
                </a>
              )
            })}
            {(ctaPlacement === 'with_nav' || ctaPlacement === 'own_zone') && renderCta()}
          </div>
        </div>
      )
    }

    // three_zone / nav_center / spread → CSS grid: brand | nav | CTA
    if (layout === 'three_zone' || layout === 'nav_center' || layout === 'spread') {
      const midJustify =
        layout === 'nav_center' || layout === 'spread'
          ? 'justify-center'
          : navAlignClass(navAlign)

      return (
        <div className={`grid w-full grid-cols-[auto_minmax(0,1fr)_auto] items-center ${zGap}`}>
          <div className="flex items-center gap-2">
            {brandWithOptionalCta}
            {renderMobileToggle()}
          </div>
          {renderLinkRow(midJustify)}
          <div className="hidden min-w-[1px] justify-end md:flex">
            {ctaPlacement === 'own_zone' && renderCta()}
          </div>
        </div>
      )
    }

    if (layout === 'brand_end') {
      return (
        <div className={`flex w-full items-center justify-between ${zGap}`}>
          <div className={`flex min-w-0 items-center ${zGap}`}>
            {renderNavLinks(navAlignClass(navAlign))}
            {ctaPlacement === 'own_zone' && <span className="hidden md:inline-flex">{renderCta()}</span>}
            {renderMobileToggle()}
          </div>
          {brandWithOptionalCta}
        </div>
      )
    }

    // brand_start (default)
    return (
      <div className={`flex w-full items-center justify-between ${zGap}`}>
        {brandWithOptionalCta}
        <div className={`flex min-w-0 items-center ${zGap}`}>
          {renderNavLinks(navAlignClass(navAlign))}
          {ctaPlacement === 'own_zone' && <span className="hidden md:inline-flex">{renderCta()}</span>}
          {renderMobileToggle()}
        </div>
      </div>
    )
  }

  return (
    <header
      className={`relative ${shell} ${customBg ? shellCls : ''} ${sticky && style !== 'transparent' ? 'sticky top-0 z-40' : ''}`}
      style={customBg ? shellStyle : undefined}
    >
      {customBg && shellOverlay && (
        <div className="pointer-events-none absolute inset-0" style={shellOverlay} />
      )}
      <div className={`relative mx-auto flex w-full flex-col ${zoneGapClass(zoneGap)} px-4 py-3 sm:px-6 ${maxWidthClass(gridMaxWidth)}`}>
        {renderBar()}

        {open && mobileMenu && (
          <nav className="flex w-full flex-col gap-2 border-t border-[var(--border)] pt-3 md:hidden">
            {links.map((link, index) => {
              const href = hrefFor(String(link.href ?? '#'))
              const external = isExternalHref(href)
              return (
                <a
                  key={link.id ?? index}
                  href={href}
                  className="rounded-md px-2 py-2 text-sm font-medium hover:bg-muted"
                  onClick={() => setOpen(false)}
                  {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                >
                  {linkLabel(link, locale)}
                </a>
              )
            })}
            {showCta && (
              <a
                href={hrefFor(typeof options.cta_href === 'string' ? options.cta_href : 'registration')}
                className="rounded-md bg-[var(--brand)] px-3 py-2 text-center text-sm font-semibold text-white"
                onClick={() => setOpen(false)}
              >
                {ctaLabel}
              </a>
            )}
          </nav>
        )}
      </div>
    </header>
  )
}
