import { LocalizedEventContent, type LocalizedText } from '@/components/registration/LocalizedEventContent'
import { RegistrationField, type PublicFormField } from '@/components/registration/RegistrationField'

type Props = {
  locale: 'en' | 'ar'
  event: {
    name: LocalizedText
    description: LocalizedText
    branding: { brand_reference: string | null }
  }
  form: {
    fields: PublicFormField[]
    privacy_notice_version: string
    terms_version: string
  }
}

export default function PublicRegistrationEvent({ locale, event, form }: Props) {
  const rtl = locale === 'ar'

  return (
    <main className="public-registration" lang={locale} dir={rtl ? 'rtl' : 'ltr'}>
      <header aria-label={rtl ? 'هوية الفعالية' : 'Event identity'}>
        <p className="brand-reference">{event.branding.brand_reference}</p>
        <h1><LocalizedEventContent value={event.name} locale={locale} /></h1>
        <p><LocalizedEventContent value={event.description} locale={locale} /></p>
      </header>
      <form aria-label={rtl ? 'نموذج التسجيل' : 'Registration form'}>
        {form.fields.map((field) => <RegistrationField key={field.key} field={field} locale={locale} />)}
        <p className="consent-evidence">
          {rtl ? 'إشعار الخصوصية' : 'Privacy notice'} {form.privacy_notice_version}
          {' · '}
          {rtl ? 'الشروط' : 'Terms'} {form.terms_version}
        </p>
        <button type="submit">{rtl ? 'متابعة' : 'Continue'}</button>
      </form>
    </main>
  )
}
