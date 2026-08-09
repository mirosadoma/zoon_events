import type { CSSProperties } from 'react'

export type RegistrationBackgroundGradient = {
  type?: 'linear'
  angle?: number
  stops?: Array<{ color: string; position: number }>
}

export type RegistrationThemeModeColors = {
  primary_color: string
  accent_color: string
  background_color: string
  background_mode: 'solid' | 'gradient' | 'image'
  background_gradient: RegistrationBackgroundGradient | null
  background_image_path: string | null
  background_image_url?: string | null
}

export type RegistrationThemeConfig = {
  light: RegistrationThemeModeColors
  dark: RegistrationThemeModeColors
  font_family_en: string
  font_family_ar: string
  /** @deprecated legacy flat alias of light + font_family_en */
  primary_color?: string
  accent_color?: string
  background_color?: string
  background_mode?: 'solid' | 'gradient' | 'image'
  background_gradient?: RegistrationBackgroundGradient | null
  background_image_path?: string | null
  background_image_url?: string | null
  font_family?: string
  logo_path?: string
  logo_url?: string
  header_image_path?: string
  text_color?: string
}

/** @deprecated use RegistrationThemeConfig */
export type RegistrationThemeBackground = RegistrationThemeConfig | RegistrationThemeModeColors & {
  font_family?: string
  logo_path?: string
  logo_url?: string
  header_image_path?: string
}

/** Fonts available in the registration form builder (loaded via Google Fonts + system). */
export const REGISTRATION_FONT_OPTIONS = [
  'Inter',
  'Poppins',
  'Nunito',
  'Rubik',
  'DM Sans',
  'Manrope',
  'Outfit',
  'Plus Jakarta Sans',
  'Sora',
  'Space Grotesk',
  'Cairo',
  'Tajawal',
  'Almarai',
  'Noto Sans Arabic',
  'IBM Plex Sans Arabic',
  'Changa',
  'El Messiri',
  'Readex Pro',
  'Amiri',
  'Lateef',
  'system-ui',
] as const

export const REGISTRATION_FONT_OPTIONS_EN = [
  'Inter',
  'Poppins',
  'Nunito',
  'Rubik',
  'DM Sans',
  'Manrope',
  'Outfit',
  'Plus Jakarta Sans',
  'Sora',
  'Space Grotesk',
  'system-ui',
] as const

export const REGISTRATION_FONT_OPTIONS_AR = [
  'Cairo',
  'Tajawal',
  'Almarai',
  'Noto Sans Arabic',
  'IBM Plex Sans Arabic',
  'Changa',
  'El Messiri',
  'Readex Pro',
  'Amiri',
  'Lateef',
  'Rubik',
  'system-ui',
] as const

export type RegistrationFontOption = (typeof REGISTRATION_FONT_OPTIONS)[number]

const FONT_STACKS: Record<string, string> = {
  Inter: '"Inter", system-ui, -apple-system, "Segoe UI", sans-serif',
  Poppins: '"Poppins", system-ui, -apple-system, "Segoe UI", sans-serif',
  Nunito: '"Nunito", system-ui, -apple-system, "Segoe UI", sans-serif',
  Rubik: '"Rubik", system-ui, -apple-system, "Segoe UI", sans-serif',
  'DM Sans': '"DM Sans", system-ui, -apple-system, "Segoe UI", sans-serif',
  Manrope: '"Manrope", system-ui, -apple-system, "Segoe UI", sans-serif',
  Outfit: '"Outfit", system-ui, -apple-system, "Segoe UI", sans-serif',
  'Plus Jakarta Sans': '"Plus Jakarta Sans", system-ui, -apple-system, "Segoe UI", sans-serif',
  Sora: '"Sora", system-ui, -apple-system, "Segoe UI", sans-serif',
  'Space Grotesk': '"Space Grotesk", system-ui, -apple-system, "Segoe UI", sans-serif',
  Cairo: '"Cairo", "Segoe UI", Tahoma, sans-serif',
  Tajawal: '"Tajawal", "Segoe UI", Tahoma, sans-serif',
  Almarai: '"Almarai", "Segoe UI", Tahoma, sans-serif',
  'Noto Sans Arabic': '"Noto Sans Arabic", "Segoe UI", Tahoma, sans-serif',
  'IBM Plex Sans Arabic': '"IBM Plex Sans Arabic", "Segoe UI", Tahoma, sans-serif',
  Changa: '"Changa", "Segoe UI", Tahoma, sans-serif',
  'El Messiri': '"El Messiri", "Segoe UI", Tahoma, sans-serif',
  'Readex Pro': '"Readex Pro", "Segoe UI", Tahoma, sans-serif',
  Amiri: '"Amiri", "Times New Roman", serif',
  Lateef: '"Lateef", "Times New Roman", serif',
  'system-ui': 'system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif',
}

const DEFAULT_LIGHT: RegistrationThemeModeColors = {
  primary_color: '#3b82f6',
  accent_color: '#8b5cf6',
  background_color: '#ffffff',
  background_mode: 'solid',
  background_gradient: {
    type: 'linear',
    angle: 160,
    stops: [
      { color: '#ffffff', position: 0 },
      { color: '#e2e8f0', position: 100 },
    ],
  },
  background_image_path: null,
  background_image_url: null,
}

const DEFAULT_DARK: RegistrationThemeModeColors = {
  primary_color: '#60a5fa',
  accent_color: '#a78bfa',
  background_color: '#0f172a',
  background_mode: 'solid',
  background_gradient: {
    type: 'linear',
    angle: 160,
    stops: [
      { color: '#0f172a', position: 0 },
      { color: '#1e293b', position: 100 },
    ],
  },
  background_image_path: null,
  background_image_url: null,
}

function isModeObject(value: unknown): value is Partial<RegistrationThemeModeColors> {
  return typeof value === 'object' && value !== null && !Array.isArray(value)
}

function softFromPrimary(primary: string): string {
  if (/^#[0-9A-Fa-f]{6}$/.test(primary)) {
    return `${primary}22`
  }

  return 'color-mix(in srgb, var(--brand) 16%, transparent)'
}

function normalizeMode(
  raw: Partial<RegistrationThemeModeColors> | null | undefined,
  fallback: RegistrationThemeModeColors,
): RegistrationThemeModeColors {
  const mode = raw ?? {}
  const backgroundMode = mode.background_mode
    ?? (mode.background_image_url || mode.background_image_path ? 'image' : fallback.background_mode)

  return {
    primary_color: mode.primary_color || fallback.primary_color,
    accent_color: mode.accent_color || fallback.accent_color,
    background_color: mode.background_color || fallback.background_color,
    background_mode: backgroundMode,
    background_gradient: mode.background_gradient ?? fallback.background_gradient,
    background_image_path: mode.background_image_path ?? fallback.background_image_path,
    background_image_url: mode.background_image_url ?? fallback.background_image_url,
  }
}

/** Normalize legacy flat theme_config into light/dark + bilingual fonts. */
export function normalizeRegistrationTheme(raw?: Partial<RegistrationThemeConfig> | null): RegistrationThemeConfig {
  const source = raw ?? {}
  const hasNested = isModeObject(source.light) || isModeObject(source.dark)

  const legacyFlat: Partial<RegistrationThemeModeColors> = {
    primary_color: source.primary_color,
    accent_color: source.accent_color,
    background_color: source.background_color,
    background_mode: source.background_mode,
    background_gradient: source.background_gradient ?? null,
    background_image_path: source.background_image_path ?? null,
    background_image_url: source.background_image_url ?? null,
  }

  const light = normalizeMode(
    hasNested ? source.light : { ...DEFAULT_LIGHT, ...legacyFlat },
    DEFAULT_LIGHT,
  )
  const dark = normalizeMode(
    hasNested ? source.dark : {
      ...DEFAULT_DARK,
      // Keep shared image from legacy flat theme for dark until configured.
      background_image_path: legacyFlat.background_image_path ?? DEFAULT_DARK.background_image_path,
      background_image_url: legacyFlat.background_image_url ?? DEFAULT_DARK.background_image_url,
      background_mode: legacyFlat.background_image_path || legacyFlat.background_image_url
        ? 'image'
        : DEFAULT_DARK.background_mode,
    },
    DEFAULT_DARK,
  )

  const fontEn = source.font_family_en || source.font_family || 'Inter'
  const fontAr = source.font_family_ar || source.font_family || 'Cairo'

  return {
    light,
    dark,
    font_family_en: fontEn,
    font_family_ar: fontAr,
    // Keep legacy flat mirrors for older readers / branding merges.
    primary_color: light.primary_color,
    accent_color: light.accent_color,
    background_color: light.background_color,
    background_mode: light.background_mode,
    background_gradient: light.background_gradient,
    background_image_path: light.background_image_path,
    background_image_url: light.background_image_url,
    font_family: fontEn,
    logo_path: source.logo_path,
    logo_url: source.logo_url,
    header_image_path: source.header_image_path,
    text_color: source.text_color,
  }
}

export function resolveRegistrationThemeMode(
  theme: RegistrationThemeConfig | null | undefined,
  isDark: boolean,
): RegistrationThemeModeColors {
  const normalized = normalizeRegistrationTheme(theme)

  return isDark ? normalized.dark : normalized.light
}

export function resolveRegistrationFontFamily(
  theme: RegistrationThemeConfig | null | undefined,
  locale: 'en' | 'ar',
): string | undefined {
  const normalized = normalizeRegistrationTheme(theme)
  const font = locale === 'ar' ? normalized.font_family_ar : normalized.font_family_en

  return registrationFontFamily(font)
}

/** Payload shape persisted to EventBranding.theme_config (no derived URLs). */
export function toPersistedRegistrationTheme(theme: RegistrationThemeConfig): Record<string, unknown> {
  const normalized = normalizeRegistrationTheme(theme)

  return {
    light: {
      primary_color: normalized.light.primary_color,
      accent_color: normalized.light.accent_color,
      background_color: normalized.light.background_color,
      background_mode: normalized.light.background_mode,
      background_gradient: normalized.light.background_mode === 'gradient' ? normalized.light.background_gradient : null,
      background_image_path: normalized.light.background_image_path,
    },
    dark: {
      primary_color: normalized.dark.primary_color,
      accent_color: normalized.dark.accent_color,
      background_color: normalized.dark.background_color,
      background_mode: normalized.dark.background_mode,
      background_gradient: normalized.dark.background_mode === 'gradient' ? normalized.dark.background_gradient : null,
      background_image_path: normalized.dark.background_image_path,
    },
    font_family_en: normalized.font_family_en,
    font_family_ar: normalized.font_family_ar,
    // Legacy mirrors (light + EN font)
    primary_color: normalized.light.primary_color,
    accent_color: normalized.light.accent_color,
    background_color: normalized.light.background_color,
    background_mode: normalized.light.background_mode,
    background_gradient: normalized.light.background_mode === 'gradient' ? normalized.light.background_gradient : null,
    background_image_path: normalized.light.background_image_path,
    font_family: normalized.font_family_en,
    logo_path: normalized.logo_path ?? null,
    header_image_path: normalized.header_image_path ?? null,
    text_color: normalized.text_color ?? null,
  }
}

export function registrationFontFamily(font?: string | null): string | undefined {
  if (!font || font.trim() === '') return undefined
  return FONT_STACKS[font] ?? `"${font}", system-ui, sans-serif`
}

export function registrationThemeCssVars(
  theme?: RegistrationThemeConfig | null,
  options?: { isDark?: boolean; locale?: 'en' | 'ar' },
): CSSProperties | undefined {
  if (!theme) return undefined

  const normalized = normalizeRegistrationTheme(theme)
  const mode = resolveRegistrationThemeMode(normalized, Boolean(options?.isDark))
  const fontStack = resolveRegistrationFontFamily(normalized, options?.locale ?? 'en')

  const vars: Record<string, string> = {}
  if (mode.primary_color) {
    vars['--reg-primary'] = mode.primary_color
    vars['--brand'] = mode.primary_color
    vars['--brand-soft'] = softFromPrimary(mode.primary_color)
  }
  if (mode.accent_color) vars['--reg-accent'] = mode.accent_color
  if (mode.background_color) vars['--reg-bg'] = mode.background_color
  if (fontStack) vars['--reg-font'] = fontStack

  return Object.keys(vars).length > 0 ? vars : undefined
}

export function registrationCardBackgroundStyle(
  theme?: RegistrationThemeConfig | null,
  options?: { isDark?: boolean },
): CSSProperties | undefined {
  if (!theme) return undefined

  const mode = resolveRegistrationThemeMode(theme, Boolean(options?.isDark))
  const backgroundMode = mode.background_mode
    ?? (mode.background_image_url || mode.background_image_path
      ? 'image'
      : mode.background_color
        ? 'solid'
        : undefined)

  if (backgroundMode === 'image' && mode.background_image_url) {
    return {
      backgroundImage: `url(${mode.background_image_url})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
      backgroundRepeat: 'no-repeat',
      backgroundColor: mode.background_color || 'transparent',
    }
  }

  if (backgroundMode === 'gradient' && mode.background_gradient?.stops?.length) {
    const stops = [...mode.background_gradient.stops]
      .sort((a, b) => a.position - b.position)
      .map((stop) => `${stop.color} ${stop.position}%`)
      .join(', ')

    return {
      backgroundImage: `linear-gradient(${mode.background_gradient.angle ?? 160}deg, ${stops})`,
      backgroundColor: 'transparent',
    }
  }

  if (backgroundMode === 'solid' && mode.background_color) {
    return {
      background: mode.background_color,
    }
  }

  return undefined
}

export function hasRegistrationCardBackground(
  theme?: RegistrationThemeConfig | null,
  options?: { isDark?: boolean },
): boolean {
  return registrationCardBackgroundStyle(theme, options) !== undefined
}

export function isDocumentDark(): boolean {
  if (typeof document === 'undefined') return false
  return document.documentElement.classList.contains('dark')
}
