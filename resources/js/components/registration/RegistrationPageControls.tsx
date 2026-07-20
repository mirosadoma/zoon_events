import { router } from '@inertiajs/react'
import { Globe, Moon, Sun } from 'lucide-react'
import { useTheme } from '@/hooks/useTheme'
import { swapLocaleInPath, type AppLocale } from '@/lib/localePath'
import en from '@/locales/en'
import ar from '@/locales/ar'

type Props = {
  locale: AppLocale
}

export default function RegistrationPageControls({ locale }: Props) {
  const { theme, setTheme } = useTheme()
  const messages = locale === 'ar' ? ar : en
  const rtl = locale === 'ar'

  function toggleLocale() {
    const next: AppLocale = locale === 'ar' ? 'en' : 'ar'
    const currentPath = `${window.location.pathname}${window.location.search}`
    document.cookie = `locale=${next};path=/;max-age=${60 * 60 * 24 * 365};SameSite=Lax`
    router.visit(swapLocaleInPath(currentPath, next), { preserveState: false })
  }

  function toggleTheme() {
    setTheme(theme === 'dark' ? 'light' : 'dark')
  }

  return (
    <div className="registration-page-controls" dir="ltr">
      <button
        type="button"
        className="registration-page-control"
        onClick={toggleLocale}
        aria-label={rtl ? messages.localeSwitchToEn : messages.localeSwitchToAr}
      >
        <Globe className="h-4 w-4" aria-hidden />
        <span>{rtl ? messages.localeSwitchToEn : messages.localeSwitchToAr}</span>
      </button>
      <button
        type="button"
        className="registration-page-control"
        onClick={toggleTheme}
        aria-label={theme === 'dark' ? messages.topbarLightMode : messages.topbarDarkMode}
      >
        {theme === 'dark' ? <Sun className="h-4 w-4" aria-hidden /> : <Moon className="h-4 w-4" aria-hidden />}
      </button>
    </div>
  )
}
