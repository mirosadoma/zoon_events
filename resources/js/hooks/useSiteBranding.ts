import { usePage } from '@inertiajs/react'
import { useLocale } from '@/hooks/useLocale'

export type SiteSettingsPublic = {
  app_name_en: string
  app_name_ar: string
  support_email?: string | null
  support_phone?: string | null
  about_en?: string | null
  about_ar?: string | null
  maintenance_enabled?: boolean
  maintenance_message_en?: string | null
  maintenance_message_ar?: string | null
  logo_url?: string | null
  favicon_url?: string | null
}

type PageProps = {
  siteSettings?: SiteSettingsPublic
}

export function useSiteBranding() {
  const { locale } = useLocale()
  const { siteSettings } = usePage<PageProps>().props
  const settings = siteSettings ?? {
    app_name_en: 'Zonetec',
    app_name_ar: 'زونتك',
  }

  return {
    appName: locale === 'ar' ? settings.app_name_ar : settings.app_name_en,
    appNameEn: settings.app_name_en,
    appNameAr: settings.app_name_ar,
    logoUrl: settings.logo_url ?? null,
    faviconUrl: settings.favicon_url ?? null,
    supportEmail: settings.support_email ?? null,
    supportPhone: settings.support_phone ?? null,
  }
}
