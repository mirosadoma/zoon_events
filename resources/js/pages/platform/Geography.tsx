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
  const { locale } = useLocale()
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
    <DashboardLayout title={locale === 'ar' ? 'الدول والمدن' : 'Countries & cities'}>
      <PageHeader
        title={locale === 'ar' ? 'الدول والمدن' : 'Countries & cities'}
        description={locale === 'ar' ? 'إدارة بيانات الجغرافيا المستخدمة في أماكن الفعاليات.' : 'Manage geography data used by event venues.'}
      />
      <PageContent>
        <div className="grid gap-6 xl:grid-cols-2">
          <form className="state-panel grid gap-3" onSubmit={submitCountry}>
            <h2 className="text-lg font-semibold">{locale === 'ar' ? 'إضافة دولة' : 'Add country'}</h2>
            <TextInput label="Code" name="code" value={countryForm.code} onChange={(e) => setCountryForm({ ...countryForm, code: e.target.value.toUpperCase() })} required />
            <TextInput label={locale === 'ar' ? 'الاسم (EN)' : 'Name (EN)'} name="name_en" value={countryForm.name_en} onChange={(e) => setCountryForm({ ...countryForm, name_en: e.target.value })} required />
            <TextInput label={locale === 'ar' ? 'الاسم (AR)' : 'Name (AR)'} name="name_ar" value={countryForm.name_ar} onChange={(e) => setCountryForm({ ...countryForm, name_ar: e.target.value })} required />
            <CheckboxInput label={locale === 'ar' ? 'نشط' : 'Active'} checked={countryForm.is_active} onChange={(e) => setCountryForm({ ...countryForm, is_active: e.target.checked })} />
            <SubmitButtonWithLoader label={locale === 'ar' ? 'حفظ الدولة' : 'Save country'} />
          </form>

          <form className="state-panel grid gap-3" onSubmit={submitCity}>
            <h2 className="text-lg font-semibold">{locale === 'ar' ? 'إضافة مدينة' : 'Add city'}</h2>
            <label className="grid gap-2 text-sm">
              <span>{locale === 'ar' ? 'الدولة' : 'Country'}</span>
              <select className="control" value={cityForm.country_id} onChange={(e) => setCityForm({ ...cityForm, country_id: e.target.value })} required>
                {countries.map((country) => (
                  <option key={country.id} value={country.id}>
                    {locale === 'ar' ? country.name_ar : country.name_en}
                  </option>
                ))}
              </select>
            </label>
            <TextInput label={locale === 'ar' ? 'الاسم (EN)' : 'Name (EN)'} name="name_en" value={cityForm.name_en} onChange={(e) => setCityForm({ ...cityForm, name_en: e.target.value })} required />
            <TextInput label={locale === 'ar' ? 'الاسم (AR)' : 'Name (AR)'} name="name_ar" value={cityForm.name_ar} onChange={(e) => setCityForm({ ...cityForm, name_ar: e.target.value })} required />
            <CheckboxInput label={locale === 'ar' ? 'نشط' : 'Active'} checked={cityForm.is_active} onChange={(e) => setCityForm({ ...cityForm, is_active: e.target.checked })} />
            <SubmitButtonWithLoader label={locale === 'ar' ? 'حفظ المدينة' : 'Save city'} />
          </form>
        </div>

        <div className="ta-table-wrap mt-8 rounded-[var(--radius-card)] border border-[var(--border)] bg-[var(--surface-elevated)]">
          <table className="ta-table">
            <thead>
              <tr>
                <th>{locale === 'ar' ? 'الدولة' : 'Country'}</th>
                <th>{locale === 'ar' ? 'الرمز' : 'Code'}</th>
                <th>{locale === 'ar' ? 'المدن' : 'Cities'}</th>
                <th>{locale === 'ar' ? 'الحالة' : 'Status'}</th>
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
                            {locale === 'ar' ? 'حذف' : 'Delete'}
                          </button>
                        </li>
                      ))}
                    </ul>
                  </td>
                  <td>{country.is_active ? (locale === 'ar' ? 'نشط' : 'Active') : (locale === 'ar' ? 'غير نشط' : 'Inactive')}</td>
                  <td className="ta-table-actions">
                    <button
                      type="button"
                      className="ta-table-action"
                      onClick={() => localizedRouter.delete(`/platform/geography/countries/${country.id}`, { preserveScroll: true })}
                    >
                      {locale === 'ar' ? 'حذف' : 'Delete'}
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
