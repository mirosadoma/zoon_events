import type { CSSProperties } from 'react'

export type RegistrationThemeBackground = {
  primary_color?: string
  accent_color?: string
  background_color?: string
  background_mode?: 'solid' | 'gradient' | 'image'
  background_gradient?: {
    type?: 'linear'
    angle?: number
    stops?: Array<{ color: string; position: number }>
  } | null
  background_image_path?: string
  background_image_url?: string
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

export function registrationFontFamily(font?: string | null): string | undefined {
  if (!font || font.trim() === '') return undefined
  return FONT_STACKS[font] ?? `"${font}", system-ui, sans-serif`
}

export function registrationThemeCssVars(theme?: RegistrationThemeBackground | null): CSSProperties | undefined {
  if (!theme) return undefined

  const vars: Record<string, string> = {}
  if (theme.primary_color) vars['--reg-primary'] = theme.primary_color
  if (theme.accent_color) vars['--reg-accent'] = theme.accent_color
  if (theme.background_color) vars['--reg-bg'] = theme.background_color
  const fontStack = registrationFontFamily(theme.font_family)
  if (fontStack) vars['--reg-font'] = fontStack

  return Object.keys(vars).length > 0 ? vars : undefined
}

export function registrationCardBackgroundStyle(
  theme?: RegistrationThemeBackground | null,
): CSSProperties | undefined {
  if (!theme) return undefined

  const mode = theme.background_mode
    ?? (theme.background_image_url || theme.background_image_path
      ? 'image'
      : theme.background_color
        ? 'solid'
        : undefined)

  if (mode === 'image' && theme.background_image_url) {
    return {
      backgroundImage: `url(${theme.background_image_url})`,
      backgroundSize: 'cover',
      backgroundPosition: 'center',
      backgroundRepeat: 'no-repeat',
      backgroundColor: theme.background_color || 'transparent',
    }
  }

  if (mode === 'gradient' && theme.background_gradient?.stops?.length) {
    const stops = [...theme.background_gradient.stops]
      .sort((a, b) => a.position - b.position)
      .map((stop) => `${stop.color} ${stop.position}%`)
      .join(', ')

    return {
      backgroundImage: `linear-gradient(${theme.background_gradient.angle ?? 160}deg, ${stops})`,
      backgroundColor: 'transparent',
    }
  }

  if (mode === 'solid' && theme.background_color) {
    return {
      background: theme.background_color,
    }
  }

  return undefined
}

export function hasRegistrationCardBackground(theme?: RegistrationThemeBackground | null): boolean {
  return registrationCardBackgroundStyle(theme) !== undefined
}
