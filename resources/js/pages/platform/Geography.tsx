import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { PageContent, PageHeader } from '@/components/layout'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import TextInput from '@/components/forms/TextInput'
import CheckboxInput from '@/components/forms/CheckboxInput'
import { useLocale } from '@/hooks/useLocale'
import { useLocalizedRouter } from '@/hooks/useLocalizedRouter'

type CityRow = {
  id: string
  name_en: string
  name_ar: string
  is_active: boolean
}

type CountryRow = {
  id: string
  code: string
  name_en: string
  name_ar: string
  is_active: boolean
  cities: CityRow[]
}

type Props = {
  countries: CountryRow[]
}

export default function Geography({ countries }: Props) {
  const { locale, t } = useLocale()
  const localizedRouter = useLocalizedRouter()
  const [countryForm, setCountryForm] = useState({
    code: '',
    name_en: '',
    name_ar: '',
    is_active: true,
  })
  const [cityForm, setCityForm] = useState({
    country_id: countries[0]?.id ?? '',
    name_en: '',
    name_ar: '',
    is_active: true,
  })

  function submitCountry(event: React.FormEvent) {
    event.preventDefault()
    localizedRouter.post('/platform/geography/countries', countryForm, {
      preserveScroll: true,
      onSuccess: () => setCountryForm({ code: '', name_en: '', name_ar: '', is_active: true }),
    })
  }

  function submitCity(event: React.FormEvent) {
    event.preventDefault()
    localizedRouter.post('/platform/geography/cities', cityForm, {
      preserveScroll: true,
      onSuccess: () => setCityForm((current) => ({ ...current, name_en: '', name_ar: '' })),
    })
  }

  return (
    <DashboardLayout title={t('geographyTitle')}>
      <PageHeader
        title={t('geographyTitle')}
        description={t('geographyDescription')}
      />
      <PageContent>
        <div className="grid gap-6 xl:grid-cols-2">
          <form className="state-panel grid gap-3" onSubmit={submitCountry}>
            <h2 className="text-lg font-semibold">{t('geographyAddCountry')}</h2>
            <TextInput label="Code" name="code" value={countryForm.code} onChange={(e) => setCountryForm({ ...countryForm, code: e.target.value.toUpperCase() })} required />
            <TextInput label={t('geographyNameEn')} name="name_en" value={countryForm.name_en} onChange={(e) => setCountryForm({ ...countryForm, name_en: e.target.value })} required />
            <TextInput label={t('geographyNameAr')} name="name_ar" value={countryForm.name_ar} onChange={(e) => setCountryForm({ ...countryForm, name_ar: e.target.value })} required />
            <CheckboxInput label={t('geographyActive')} checked={countryForm.is_active} onChange={(e) => setCountryForm({ ...countryForm, is_active: e.target.checked })} />
            <SubmitButtonWithLoader label={t('geographySaveCountry')} />
          </form>

          <form className="state-panel grid gap-3" onSubmit={submitCity}>
            <h2 className="text-lg font-semibold">{t('geographyAddCity')}</h2>
            <label className="grid gap-2 text-sm">
              <span>{t('geographyCountry')}</span>
              <select className="control" value={cityForm.country_id} onChange={(e) => setCityForm({ ...cityForm, country_id: e.target.value })} required>
                {countries.map((country) => (
                  <option key={country.id} value={country.id}>
                    {locale === 'ar' ? country.name_ar : country.name_en}
                  </option>
                ))}
              </select>
            </label>
            <TextInput label={t('geographyNameEn')} name="name_en" value={cityForm.name_en} onChange={(e) => setCityForm({ ...cityForm, name_en: e.target.value })} required />
            <TextInput label={t('geographyNameAr')} name="name_ar" value={cityForm.name_ar} onChange={(e) => setCityForm({ ...cityForm, name_ar: e.target.value })} required />
            <CheckboxInput label={t('geographyActive')} checked={cityForm.is_active} onChange={(e) => setCityForm({ ...cityForm, is_active: e.target.checked })} />
            <SubmitButtonWithLoader label={t('geographySaveCity')} />
          </form>
        </div>

        <div className="ta-table-wrap mt-8 rounded-[var(--radius-card)] border border-[var(--border)] bg-[var(--surface-elevated)]">
          <table className="ta-table">
            <thead>
              <tr>
                <th>{t('geographyCountry')}</th>
                <th>{t('geographyCode')}</th>
                <th>{t('geographyCities')}</th>
                <th>{t('geographyStatus')}</th>
                <th />
              </tr>
            </thead>
            <tbody>
              {countries.map((country) => (
                <tr key={country.id}>
                  <td>{locale === 'ar' ? country.name_ar : country.name_en}</td>
                  <td>{country.code}</td>
                  <td>
                    <ul className="space-y-1">
                      {country.cities.map((city) => (
                        <li key={city.id} className="flex items-center justify-between gap-2">
                          <span>{locale === 'ar' ? city.name_ar : city.name_en}</span>
                          <button
                            type="button"
                            className="text-xs text-red-600"
                            onClick={() => localizedRouter.delete(`/platform/geography/cities/${city.id}`, { preserveScroll: true })}
                          >
                            {t('delete')}
                          </button>
                        </li>
                      ))}
                    </ul>
                  </td>
                  <td>{country.is_active ? t('geographyActiveStatus') : t('geographyInactiveStatus')}</td>
                  <td className="ta-table-actions">
                    <button
                      type="button"
                      className="ta-table-action"
                      onClick={() => localizedRouter.delete(`/platform/geography/countries/${country.id}`, { preserveScroll: true })}
                    >
                      {t('delete')}
                    </button>
                  </td>
                </tr>
              ))}
            </tbody>
          </table>
        </div>
      </PageContent>
    </DashboardLayout>
  )
}
