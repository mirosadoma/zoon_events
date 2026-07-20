import { FormEvent, useState } from 'react'
import MarketingShell, { type MarketingBranding } from '@/layouts/MarketingShell'
import TextInput from '@/components/forms/TextInput'
import { useLocale } from '@/hooks/useLocale'
import { Mail, MapPin, Phone, Send } from 'lucide-react'

type Props = MarketingBranding

export default function Contact(props: Props) {
  const { locale, t } = useLocale()
  const contactEmail = props.support_email ?? 'hello@zonetec.com'
  const contactPhone = props.support_phone ?? '+20 100 000 0000'
  const [sent, setSent] = useState(false)
  const [form, setForm] = useState({
    name: '',
    email: '',
    org: '',
    subject: '',
    message: '',
  })

  function handleSubmit(event: FormEvent) {
    event.preventDefault()
    const body = [
      `Name: ${form.name}`,
      `Email: ${form.email}`,
      `Organization: ${form.org}`,
      '',
      form.message,
    ].join('\n')

    const mailto = `mailto:${contactEmail}?subject=${encodeURIComponent(form.subject || 'Conference inquiry')}&body=${encodeURIComponent(body)}`
    window.location.href = mailto
    setSent(true)
  }

  return (
    <MarketingShell branding={props} title={t('contactPageTitle')} active="contact">
      <section className="mkt-hero">
        <div className="mkt-hero__inner">
          <p className="mkt-eyebrow">{t('contactPageEyebrow')}</p>
          <h1 className="mkt-hero__title">{t('contactPageHero')}</h1>
          <p className="mkt-hero__lead">{t('contactPageLead')}</p>
        </div>
      </section>

      <section className="mkt-section mkt-contact">
        <form className="mkt-contact__form" onSubmit={handleSubmit}>
          <h2>{t('contactPageFormTitle')}</h2>
          <div className="mkt-contact__grid">
            <TextInput label={t('contactPageName')} value={form.name} onChange={(e) => setForm({ ...form, name: e.target.value })} required />
            <TextInput label={t('contactPageEmail')} type="email" value={form.email} onChange={(e) => setForm({ ...form, email: e.target.value })} required />
            <TextInput label={t('contactPageOrg')} value={form.org} onChange={(e) => setForm({ ...form, org: e.target.value })} />
            <TextInput label={t('contactPageSubject')} value={form.subject} onChange={(e) => setForm({ ...form, subject: e.target.value })} required />
          </div>
          <label className="mkt-textarea">
            <span>{t('contactPageMessage')}</span>
            <textarea
              className="control min-h-36"
              value={form.message}
              onChange={(e) => setForm({ ...form, message: e.target.value })}
              required
            />
          </label>
          <button type="submit" className="button-primary inline-flex items-center gap-2">
            <Send className="h-4 w-4" />
            {t('contactPageSubmit')}
          </button>
          {sent ? <p className="mkt-contact__hint">{t('contactPageSent')}</p> : null}
        </form>

        <aside className="mkt-contact__aside">
          <h2>{t('contactPageDetailsTitle')}</h2>
          <div className="mkt-contact__detail">
            <Mail className="h-4 w-4 text-[var(--brand)]" />
            <a href={`mailto:${contactEmail}`}>{contactEmail}</a>
          </div>
          <div className="mkt-contact__detail">
            <Phone className="h-4 w-4 text-[var(--brand)]" />
            <a href={`tel:${contactPhone}`}>{contactPhone}</a>
          </div>
          <div className="mkt-contact__block">
            <MapPin className="h-4 w-4 text-[var(--brand)]" />
            <div>
              <strong>{t('contactPageOffice')}</strong>
              <p>{t('contactPageOfficeText')}</p>
            </div>
          </div>
          <div className="mkt-contact__block">
            <Send className="h-4 w-4 text-[var(--brand)]" />
            <div>
              <strong>{t('contactPageResponse')}</strong>
              <p>{t('contactPageResponseText')}</p>
            </div>
          </div>
        </aside>
      </section>
    </MarketingShell>
  )
}
