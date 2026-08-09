import type { CSSProperties } from 'react'
import { backgroundOverlayStyle, backgroundStyle, type SiteBackground } from '@/lib/siteBackgroundStyle'

export type BlockStyleOptions = {
  heading_color?: string
  text_color?: string
  accent_color?: string
  link_color?: string
  heading_size?: string
  body_size?: string
  heading_weight?: string
  padding_y?: string
  padding_x?: string
  margin_top?: string
  margin_bottom?: string
  margin_left?: string
  margin_right?: string
  border_radius?: string
  border_width?: string
  border_color?: string
  border_style?: string
  shadow?: string
  opacity?: number
  max_width?: string
  width?: string
  content_align?: string
  background?: SiteBackground
}

const HEADING_SIZE: Record<string, string> = {
  sm: 'text-xl sm:text-2xl',
  md: 'text-2xl sm:text-3xl',
  lg: 'text-3xl sm:text-4xl',
  xl: 'text-4xl sm:text-5xl',
  '2xl': 'text-5xl sm:text-6xl',
}

const BODY_SIZE: Record<string, string> = {
  xs: 'text-xs',
  sm: 'text-sm',
  base: 'text-base',
  lg: 'text-lg',
  xl: 'text-xl',
}

const HEADING_WEIGHT: Record<string, string> = {
  normal: 'font-normal',
  medium: 'font-medium',
  semibold: 'font-semibold',
  bold: 'font-bold',
  extrabold: 'font-extrabold',
}

const PADDING_Y: Record<string, string> = {
  none: 'py-0',
  sm: 'py-6',
  md: 'py-10',
  lg: 'py-16',
  xl: 'py-24',
}

const PADDING_X: Record<string, string> = {
  none: 'px-0',
  sm: 'px-3',
  md: 'px-4 sm:px-6',
  lg: 'px-6 sm:px-10',
  xl: 'px-8 sm:px-12',
}

const MARGIN: Record<string, string> = {
  none: '',
  sm: 'mt-4 mb-4',
  md: 'mt-8 mb-8',
  lg: 'mt-12 mb-12',
}

const MARGIN_VALUE: Record<string, string> = {
  none: '0',
  xs: '0.25rem',
  sm: '0.5rem',
  md: '1rem',
  lg: '1.5rem',
  xl: '2rem',
  '2xl': '3rem',
}

const RADIUS: Record<string, string> = {
  none: 'rounded-none',
  sm: 'rounded-md',
  md: 'rounded-lg',
  lg: 'rounded-xl',
  xl: 'rounded-2xl',
  full: 'rounded-full',
}

const SHADOW: Record<string, string> = {
  none: '',
  sm: 'shadow-sm',
  md: 'shadow-md',
  lg: 'shadow-lg',
  xl: 'shadow-xl',
}

const MAX_WIDTH: Record<string, string> = {
  sm: 'max-w-sm',
  md: 'max-w-md',
  lg: 'max-w-lg',
  xl: 'max-w-xl',
  '2xl': 'max-w-2xl',
  '3xl': 'max-w-3xl',
  '4xl': 'max-w-4xl',
  '5xl': 'max-w-5xl',
  '6xl': 'max-w-6xl',
  '7xl': 'max-w-7xl',
  full: 'max-w-full',
}

const BORDER_WIDTH: Record<string, string> = {
  '0': 'border-0',
  '1': 'border',
  '2': 'border-2',
  '4': 'border-4',
}

function str(value: unknown): string | undefined {
  return typeof value === 'string' && value !== '' ? value : undefined
}

function num(value: unknown): number | undefined {
  return typeof value === 'number' ? value : undefined
}

export function readBlockStyle(options: Record<string, unknown>): BlockStyleOptions {
  const bg = options.background
  return {
    heading_color: str(options.heading_color),
    text_color: str(options.text_color),
    accent_color: str(options.accent_color),
    link_color: str(options.link_color),
    heading_size: str(options.heading_size),
    body_size: str(options.body_size),
    heading_weight: str(options.heading_weight),
    padding_y: str(options.padding_y),
    padding_x: str(options.padding_x),
    margin_top: str(options.margin_top),
    margin_bottom: str(options.margin_bottom),
    margin_left: str(options.margin_left),
    margin_right: str(options.margin_right),
    border_radius: str(options.border_radius),
    border_width: str(options.border_width),
    border_color: str(options.border_color),
    border_style: str(options.border_style),
    shadow: str(options.shadow),
    opacity: num(options.opacity),
    max_width: str(options.max_width),
    width: str(options.width),
    content_align: str(options.content_align),
    background: bg && typeof bg === 'object' && !Array.isArray(bg) ? (bg as SiteBackground) : undefined,
  }
}

export function blockShellClasses(options: Record<string, unknown>): string {
  const s = readBlockStyle(options)
  const parts = [
    s.padding_y ? PADDING_Y[s.padding_y] : '',
    s.padding_x ? PADDING_X[s.padding_x] : '',
    s.margin_top === 'sm' ? 'mt-4' : s.margin_top === 'md' ? 'mt-8' : s.margin_top === 'lg' ? 'mt-12' : '',
    s.margin_bottom === 'sm' ? 'mb-4' : s.margin_bottom === 'md' ? 'mb-8' : s.margin_bottom === 'lg' ? 'mb-12' : '',
    s.border_radius ? RADIUS[s.border_radius] : '',
    s.shadow ? SHADOW[s.shadow] : '',
    s.border_width ? BORDER_WIDTH[s.border_width] : '',
  ]
  return parts.filter(Boolean).join(' ')
}

export function blockShellStyle(options: Record<string, unknown>): CSSProperties {
  const s = readBlockStyle(options)
  const style: CSSProperties = {}

  if (s.opacity !== undefined && s.opacity >= 0 && s.opacity <= 100) {
    style.opacity = s.opacity / 100
  }

  if (s.border_color && s.border_width && s.border_width !== '0') {
    style.borderColor = s.border_color
  }

  if (s.border_style && s.border_width && s.border_width !== '0') {
    style.borderStyle = s.border_style as CSSProperties['borderStyle']
  }

  const bg = s.background
  if (bg && bg.type && bg.type !== 'none') {
    Object.assign(style, backgroundStyle(bg))
  }

  return style
}

export function blockShellOverlay(options: Record<string, unknown>): CSSProperties | null {
  const s = readBlockStyle(options)
  return backgroundOverlayStyle(s.background)
}

export function containerMaxWidthClass(options: Record<string, unknown>, fallback = 'max-w-6xl'): string {
  const width = typeof options.width === 'string' ? options.width : ''
  // Percentage-first width keeps left/right gutters equal on any viewport.
  if (width === 'full') return 'mx-auto w-[min(100%,96rem)] max-w-none'
  if (width === 'narrow') return 'mx-auto w-[min(92%,48rem)]'
  const s = readBlockStyle(options)
  if (s.max_width && MAX_WIDTH[s.max_width]) {
    return `mx-auto w-[min(92%,100%)] ${MAX_WIDTH[s.max_width]}`
  }
  if (width === 'boxed') return 'mx-auto w-[min(92%,72rem)]'
  // Map common Tailwind max-width fallbacks to % + cap
  if (fallback === 'max-w-3xl') return 'mx-auto w-[min(92%,48rem)]'
  if (fallback === 'max-w-4xl') return 'mx-auto w-[min(92%,56rem)]'
  if (fallback === 'max-w-5xl') return 'mx-auto w-[min(94%,64rem)]'
  if (fallback === 'max-w-6xl') return 'mx-auto w-[min(94%,72rem)]'
  if (fallback === 'max-w-7xl') return 'mx-auto w-[min(96%,80rem)]'
  return `mx-auto w-[min(92%,100%)] ${fallback}`
}

/** Horizontal section padding — percentage gutters so sides stay balanced. */
export function sectionPadXClass(options: Record<string, unknown>, fallback = 'px-[4%] sm:px-[5%]'): string {
  return options.width === 'full' ? 'px-[3%] sm:px-[4%]' : fallback
}

export function headingClasses(options: Record<string, unknown>): string {
  const s = readBlockStyle(options)
  return [
    s.heading_size ? HEADING_SIZE[s.heading_size] : '',
    s.heading_weight ? HEADING_WEIGHT[s.heading_weight] : 'font-bold',
  ].filter(Boolean).join(' ')
}

export function bodyClasses(options: Record<string, unknown>): string {
  const s = readBlockStyle(options)
  return s.body_size ? BODY_SIZE[s.body_size] : 'text-base'
}

export function headingStyle(options: Record<string, unknown>): CSSProperties {
  const color = str(options.heading_color)
  return color ? { color } : {}
}

export function textStyle(options: Record<string, unknown>): CSSProperties {
  const color = str(options.text_color)
  return color ? { color } : {}
}

export function accentStyle(options: Record<string, unknown>): CSSProperties {
  const color = str(options.accent_color)
  return color ? { backgroundColor: color } : {}
}

export function buttonStyle(options: Record<string, unknown>): CSSProperties {
  const accent = str(options.accent_color)
  const text = str(options.text_color)
  const style: CSSProperties = {}
  if (accent) style.backgroundColor = accent
  if (text && accent) style.color = text
  return style
}

export function elementStyleClasses(style?: Record<string, unknown>): string {
  if (!style) return ''
  const s = readBlockStyle(style)
  return [
    s.body_size ? BODY_SIZE[s.body_size] : '',
    s.heading_weight && s.heading_weight !== 'bold' ? HEADING_WEIGHT[s.heading_weight] : '',
    s.border_radius ? RADIUS[s.border_radius] : '',
    s.shadow ? SHADOW[s.shadow] : '',
    s.border_width ? BORDER_WIDTH[s.border_width] : '',
  ].filter(Boolean).join(' ')
}

export function elementInlineStyle(style?: Record<string, unknown>): CSSProperties {
  if (!style) return {}
  const s = readBlockStyle(style)
  const out: CSSProperties = {}

  if (s.text_color) out.color = s.text_color
  if (s.heading_color) out.color = s.heading_color
  if (s.accent_color) out.backgroundColor = s.accent_color
  if (s.border_color && s.border_width && s.border_width !== '0') out.borderColor = s.border_color
  if (s.border_style) out.borderStyle = s.border_style as CSSProperties['borderStyle']
  if (s.opacity !== undefined) out.opacity = s.opacity / 100

  const mt = s.margin_top ? MARGIN_VALUE[s.margin_top] : undefined
  const mb = s.margin_bottom ? MARGIN_VALUE[s.margin_bottom] : undefined
  const ml = s.margin_left ? MARGIN_VALUE[s.margin_left] : undefined
  const mr = s.margin_right ? MARGIN_VALUE[s.margin_right] : undefined
  if (mt) out.marginTop = mt
  if (mb) out.marginBottom = mb
  if (ml) out.marginInlineStart = ml
  if (mr) out.marginInlineEnd = mr

  const bg = s.background
  if (bg && bg.type && bg.type !== 'none') {
    Object.assign(out, backgroundStyle(bg))
  }

  return out
}

export const STYLE_OPTION_KEYS = [
  'heading_color', 'text_color', 'accent_color', 'link_color',
  'heading_size', 'body_size', 'heading_weight',
  'padding_y', 'padding_x', 'margin_top', 'margin_bottom', 'margin_left', 'margin_right',
  'border_radius', 'border_width', 'border_color', 'border_style',
  'shadow', 'opacity', 'max_width', 'background',
  'custom_class', 'custom_css',
] as const

export function blockScopeClass(blockId: string): string {
  const safe = blockId.replace(/[^a-zA-Z0-9_-]/g, '')
  return `site-block-${safe || 'x'}`
}

export function elementScopeClass(elementId: string): string {
  const safe = elementId.replace(/[^a-zA-Z0-9_-]/g, '')
  return `site-el-${safe || 'x'}`
}
