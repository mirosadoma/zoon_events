import {
  blockShellClasses,
  blockShellOverlay,
  blockShellStyle,
} from '@/lib/siteBlockStyle'
import type { SiteBackground } from '@/lib/siteBackgroundStyle'
import { formatCssLength, parseCssLength } from '@/lib/cssLength'
import { isExternalHref, resolveSiteHref } from '@/lib/siteHref'

type FooterLink = {
  id?: string
  label?: string
  label_en?: string
  label_ar?: string
  href?: string
}
type FooterColumn = {
  id?: string
  title?: string
  title_en?: string
  title_ar?: string
  links?: FooterLink[]
}
type SocialLink = { id?: string; platform?: string; url?: string }

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs?: Record<string, unknown>
  locale: 'en' | 'ar'
  registerUrl?: string
  siteBaseUrl?: string
}

function footerLinkLabel(link: FooterLink, locale: 'en' | 'ar'): string {
  if (locale === 'ar') {
    return link.label_ar || link.label || link.label_en || ''
  }
  return link.label_en || link.label || link.label_ar || ''
}

function footerColumnTitle(column: FooterColumn, locale: 'en' | 'ar'): string {
  if (locale === 'ar') {
    return column.title_ar || column.title || column.title_en || ''
  }
  return column.title_en || column.title || column.title_ar || ''
}

const LOGO_HEIGHTS: Record<string, number> = {
  sm: 32,
  md: 48,
  lg: 72,
}

const TEXT_SIZE: Record<string, string> = {
  xs: 'text-xs',
  sm: 'text-sm',
  md: 'text-base',
  base: 'text-base',
  lg: 'text-lg',
  xl: 'text-xl',
  '2xl': 'text-2xl',
}

const SOCIAL_SIZE: Record<string, string> = {
  sm: 'h-4 w-4',
  md: 'h-5 w-5',
  lg: 'h-6 w-6',
  xl: 'h-7 w-7',
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

function footerStyleShell(design: string, customBg: boolean): string {
  if (customBg) {
    if (design === 'branded') return 'text-white'
    if (design === 'dark' || design === 'simple') return 'text-white'
    return 'border-t border-[var(--border)] text-[var(--ink)]'
  }
  if (design === 'branded') return 'bg-[var(--brand)] text-white'
  if (design === 'dark' || design === 'simple') return 'bg-[var(--ink)] text-white'
  return 'bg-[var(--surface)] border-t border-[var(--border)] text-[var(--ink)]'
}

function gridColsClass(cols: number): string {
  switch (cols) {
    case 2:
      return 'grid-cols-1 sm:grid-cols-2'
    case 3:
      return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-3'
    case 5:
      return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-5'
    case 6:
      return 'grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-6'
    case 4:
    default:
      return 'grid-cols-1 sm:grid-cols-2 lg:grid-cols-4'
  }
}

function gapClass(gap: string): string {
  switch (gap) {
    case 'sm':
      return 'gap-4'
    case 'lg':
      return 'gap-10'
    case 'xl':
      return 'gap-12'
    case 'md':
    default:
      return 'gap-8'
  }
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

function brandSpanClass(span: number, gridCols: number): string {
  const safe = Math.max(1, Math.min(span, gridCols))
  // Tailwind needs complete class names
  const map: Record<number, string> = {
    1: 'lg:col-span-1',
    2: 'sm:col-span-2 lg:col-span-2',
    3: 'sm:col-span-2 lg:col-span-3',
    4: 'sm:col-span-2 lg:col-span-4',
    5: 'sm:col-span-2 lg:col-span-5',
    6: 'sm:col-span-2 lg:col-span-6',
  }
  return map[safe] || 'lg:col-span-1'
}

function SocialSvg({ platform, className }: { platform: string; className?: string }) {
  const common = { viewBox: '0 0 24 24', fill: 'currentColor', className, 'aria-hidden': true as const }
  switch (platform) {
    case 'facebook':
      return (
        <svg {...common}>
          <path d="M14 13.5h2.5l1-4H14v-2c0-1.03 0-2 2-2h1.5V2.14c-.326-.043-1.557-.14-2.857-.14C11.928 2 10 3.657 10 6.7v2.8H7v4h3V22h4v-8.5z" />
        </svg>
      )
    case 'instagram':
      return (
        <svg {...common}>
          <path d="M7 2h10a5 5 0 0 1 5 5v10a5 5 0 0 1-5 5H7a5 5 0 0 1-5-5V7a5 5 0 0 1 5-5zm5 5a5 5 0 1 0 0 10 5 5 0 0 0 0-10zm6.5-.75a1.25 1.25 0 1 0 0 2.5 1.25 1.25 0 0 0 0-2.5zM12 9a3 3 0 1 1 0 6 3 3 0 0 1 0-6z" />
        </svg>
      )
    case 'linkedin':
      return (
        <svg {...common}>
          <path d="M6.94 5a1.94 1.94 0 1 1-3.88 0 1.94 1.94 0 0 1 3.88 0zM4 8.5h3.5V20H4V8.5zm6 0H13v1.57h.05c.42-.8 1.45-1.64 2.98-1.64C19.4 8.43 20 10.5 20 13.3V20h-3.5v-5.9c0-1.4-.03-3.2-1.95-3.2-1.95 0-2.25 1.52-2.25 3.1V20H9.3V8.5H10z" />
        </svg>
      )
    case 'youtube':
      return (
        <svg {...common}>
          <path d="M23.5 6.2a3 3 0 0 0-2.1-2.1C19.5 3.5 12 3.5 12 3.5s-7.5 0-9.4.6A3 3 0 0 0 .5 6.2 31.5 31.5 0 0 0 0 12a31.5 31.5 0 0 0 .5 5.8 3 3 0 0 0 2.1 2.1c1.9.6 9.4.6 9.4.6s7.5 0 9.4-.6a3 3 0 0 0 2.1-2.1A31.5 31.5 0 0 0 24 12a31.5 31.5 0 0 0-.5-5.8zM9.75 15.5v-7l6.5 3.5-6.5 3.5z" />
        </svg>
      )
    case 'whatsapp':
      return (
        <svg {...common}>
          <path d="M12.04 2C6.58 2 2.13 6.45 2.13 11.91c0 1.75.46 3.45 1.32 4.95L2 22l5.25-1.38c1.45.79 3.08 1.21 4.79 1.21 5.46 0 9.91-4.45 9.91-9.91S17.5 2 12.04 2zm5.79 14.18c-.24.68-1.4 1.25-1.94 1.33-.5.08-1.13.11-1.82-.11-.42-.14-.96-.31-1.65-.61-2.9-1.25-4.79-4.18-4.93-4.37-.14-.19-1.16-1.54-1.16-2.94 0-1.4.73-2.09 1-2.37.24-.28.54-.35.72-.35h.52c.17 0 .39-.06.61.47.24.54.81 1.98.88 2.12.07.14.12.31.02.5-.1.19-.14.31-.28.47-.14.17-.3.37-.43.5-.14.14-.29.29-.12.57.17.28.75 1.24 1.61 2.01 1.11.99 2.04 1.3 2.33 1.45.28.14.45.12.61-.07.17-.19.7-.81.88-1.09.19-.28.37-.23.61-.14.24.1 1.54.73 1.8.86.26.14.44.21.5.32.07.12.07.68-.17 1.36z" />
        </svg>
      )
    case 'x':
    case 'twitter':
      return (
        <svg {...common}>
          <path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-4.714-6.231-5.401 6.231H2.744l7.727-8.835L1.254 2.25H8.08l4.253 5.622L18.244 2.25zm-1.161 17.52h1.833L7.084 4.126H5.117L17.083 19.77z" />
        </svg>
      )
    case 'tiktok':
      return (
        <svg {...common}>
          <path d="M19.59 6.69a4.83 4.83 0 0 1-3.77-4.25V2h-3.45v13.67a2.89 2.89 0 0 1-2.88 2.5 2.89 2.89 0 0 1-2.89-2.89 2.89 2.89 0 0 1 2.89-2.89c.28 0 .54.04.79.1v-3.5a6.37 6.37 0 0 0-.79-.05A6.34 6.34 0 0 0 3.16 15.2a6.34 6.34 0 0 0 6.34 6.34 6.34 6.34 0 0 0 6.34-6.34V8.73a8.19 8.19 0 0 0 4.76 1.52V6.79a4.84 4.84 0 0 1-1.01-.1z" />
        </svg>
      )
    default:
      return (
        <svg {...common} fill="none" stroke="currentColor" strokeWidth="2" strokeLinecap="round" strokeLinejoin="round">
          <circle cx="12" cy="12" r="10" />
          <path d="M2 12h20M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z" />
        </svg>
      )
  }
}

function sizeClass(value: unknown, fallback: string): string {
  if (typeof value === 'string' && TEXT_SIZE[value]) return TEXT_SIZE[value]
  return fallback
}

export default function FooterRenderer({ content, options, refs = {}, locale, registerUrl, siteBaseUrl }: Props) {
  const design = typeof options.design === 'string' ? options.design : 'columns'
  const tagline = typeof content.tagline === 'string' ? content.tagline : ''
  const copyright = typeof content.copyright === 'string' ? content.copyright : ''
  const showBrand = options.show_brand !== false
  const showLogo = options.show_logo !== false
  const showCopyright = options.show_copyright !== false
  const showSocial = Boolean(options.show_social)
  const columns = Array.isArray(content.columns) ? (content.columns as FooterColumn[]) : []
  const socialLinks = Array.isArray(content.social_links)
    ? (content.social_links as SocialLink[]).filter((l) => typeof l.url === 'string' && l.url.trim() !== '')
    : []

  const gridCols = typeof options.grid_cols === 'number' && options.grid_cols >= 2 && options.grid_cols <= 6
    ? options.grid_cols
    : 4
  const brandSpan = typeof options.brand_span === 'number' && options.brand_span >= 1 ? options.brand_span : 1
  const brandOrder = options.brand_order === 'end' ? 'end' : 'start'
  const footerGap = typeof options.gap === 'string' ? options.gap : 'md'
  const gridMaxWidth = typeof options.grid_max_width === 'string' ? options.grid_max_width : '6xl'

  const logoUrl =
    typeof refs.logo_url === 'string'
      ? refs.logo_url
      : typeof options.logo_url === 'string'
        ? options.logo_url
        : ''
  const logoSize = typeof options.logo_size === 'string' ? options.logo_size : 'md'
  const logoMaxHeight = formatCssLength(
    parseCssLength(
      options.logo_max_height,
      options.logo_max_height_unit,
      { value: LOGO_HEIGHTS[logoSize] || 48, unit: 'px' },
    ),
  )

  const taglineColor =
    (typeof options.tagline_color === 'string' && options.tagline_color) ||
    (typeof options.heading_color === 'string' ? options.heading_color : undefined)
  const copyrightColor =
    (typeof options.copyright_color === 'string' && options.copyright_color) ||
    (typeof options.text_color === 'string' ? options.text_color : undefined)
  const columnTitleColor =
    (typeof options.column_title_color === 'string' && options.column_title_color) ||
    (typeof options.heading_color === 'string' ? options.heading_color : undefined)
  const linkColor = typeof options.link_color === 'string' ? options.link_color : undefined
  const socialColor =
    (typeof options.social_color === 'string' && options.social_color) ||
    (typeof options.accent_color === 'string' ? options.accent_color : undefined)

  const taglineSize = sizeClass(options.tagline_size ?? options.heading_size, 'text-lg')
  const copyrightSize = sizeClass(options.copyright_size ?? options.body_size, 'text-sm')
  const columnTitleSize = sizeClass(options.column_title_size ?? options.heading_size, 'text-sm')
  const linkSize = sizeClass(options.footer_link_size ?? options.body_size, 'text-sm')
  const socialIconSize =
    typeof options.social_size === 'string' && SOCIAL_SIZE[options.social_size]
      ? SOCIAL_SIZE[options.social_size]
      : SOCIAL_SIZE.md

  const customBg = hasSectionBackground(options)
  const shellCls = blockShellClasses(options)
  const shellStyle = blockShellStyle(options)
  const shellOverlay = blockShellOverlay(options)
  const shell = footerStyleShell(design, customBg)

  const footerShellProps = {
    className: `relative ${shell} ${customBg ? shellCls : ''} px-4 ${design === 'centered' || design === 'simple' ? 'py-10 sm:px-6' : 'py-12 sm:px-6'}`,
    style: customBg ? shellStyle : undefined,
  }

  const renderLogo = () => {
    if (!showLogo || !logoUrl) return null
    return (
      <img
        src={logoUrl}
        alt={tagline || 'Logo'}
        className="object-contain"
        style={{ maxHeight: logoMaxHeight }}
      />
    )
  }

  const renderSocial = (centered = false) => {
    if (!showSocial || socialLinks.length === 0) return null
    return (
      <div
        className={`flex flex-wrap items-center gap-2 ${centered ? 'justify-center' : ''}`}
        role="navigation"
        aria-label="Social links"
      >
        {socialLinks.map((link, index) => {
          const platform = String(link.platform ?? 'website')
          return (
            <a
              key={link.id ?? index}
              href={String(link.url ?? '#')}
              target="_blank"
              rel="noopener noreferrer"
              className="inline-flex h-9 w-9 items-center justify-center rounded-full border border-current/20 bg-current/5 transition hover:bg-current/15 hover:opacity-100"
              style={socialColor ? { color: socialColor } : { color: 'var(--muted)' }}
              aria-label={platform}
            >
              <SocialSvg platform={platform} className={socialIconSize} />
            </a>
          )
        })}
      </div>
    )
  }

  const renderBrandBlock = (centered = false) => {
    if (!showBrand && !(showLogo && logoUrl)) return null
    return (
      <div className={`space-y-3 ${centered ? 'flex flex-col items-center' : ''}`}>
        {renderLogo()}
        {showBrand && tagline && (
          <p
            className={`${taglineSize} font-semibold`}
            style={taglineColor ? { color: taglineColor } : undefined}
          >
            {tagline}
          </p>
        )}
      </div>
    )
  }

  const renderCopyright = (centered = false) => {
    if (!showCopyright || !copyright) return null
    return (
      <p
        className={`${copyrightSize} opacity-80 ${centered ? 'text-center' : ''}`}
        style={copyrightColor ? { color: copyrightColor } : undefined}
      >
        {copyright}
      </p>
    )
  }

  if (design === 'centered' || design === 'simple') {
    return (
      <footer {...footerShellProps}>
        {customBg && shellOverlay && (
          <div className="pointer-events-none absolute inset-0" style={shellOverlay} />
        )}
        <div className="relative mx-auto max-w-3xl space-y-4 text-center">
          {renderBrandBlock(true)}
          {renderSocial(true)}
          {renderCopyright(true)}
        </div>
      </footer>
    )
  }

  const showBrandColumn = showBrand || (showLogo && Boolean(logoUrl)) || showSocial
  const brandBlock = showBrandColumn ? (
    <div className={`space-y-4 ${brandSpanClass(brandSpan, gridCols)}`}>
      {(showBrand || (showLogo && logoUrl)) && renderBrandBlock()}
      {showSocial && renderSocial()}
    </div>
  ) : null

  return (
    <footer {...footerShellProps}>
      {customBg && shellOverlay && (
        <div className="pointer-events-none absolute inset-0" style={shellOverlay} />
      )}
      <div
        className={`relative mx-auto grid w-full ${maxWidthClass(gridMaxWidth)} ${gapClass(footerGap)} ${gridColsClass(gridCols)}`}
      >
        {brandOrder === 'start' && brandBlock}
        {columns.map((column, index) => {
          const colTitle = footerColumnTitle(column, locale)
          return (
          <div key={column.id ?? index}>
            {colTitle && (
              <p
                className={`mb-3 ${columnTitleSize} font-semibold uppercase tracking-wide opacity-80`}
                style={columnTitleColor ? { color: columnTitleColor } : undefined}
              >
                {colTitle}
              </p>
            )}
            <ul className="space-y-2">
              {(column.links ?? []).map((link, linkIndex) => {
                const href = resolveHref(String(link.href ?? '#'), registerUrl, siteBaseUrl)
                const external = isExternalHref(href)
                return (
                  <li key={link.id ?? linkIndex}>
                    <a
                      href={href}
                      className={`${linkSize} opacity-90 hover:opacity-100`}
                      style={linkColor ? { color: linkColor } : undefined}
                      {...(external ? { target: '_blank', rel: 'noopener noreferrer' } : {})}
                    >
                      {footerLinkLabel(link, locale)}
                    </a>
                  </li>
                )
              })}
            </ul>
          </div>
          )
        })}
        {brandOrder === 'end' && brandBlock}
      </div>
      {showCopyright && copyright && (
        <p
          className={`relative mx-auto mt-10 w-full border-t border-white/20 pt-6 ${maxWidthClass(gridMaxWidth)} ${copyrightSize} opacity-70`}
          style={copyrightColor ? { color: copyrightColor } : undefined}
        >
          {copyright}
        </p>
      )}
    </footer>
  )
}
