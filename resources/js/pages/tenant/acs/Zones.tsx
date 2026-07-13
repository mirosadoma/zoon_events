import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { ZoneLaneEditor } from '@/components/acs/ZoneLaneEditor'
import TextInput from '@/components/forms/TextInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import type { AcsZone } from '@/types/phase4'

type EventRow = { id: string; name: { en: string; ar: string } }

type Props = {
  event: EventRow
  tenantId: string
  zones: AcsZone[]
}

export default function AcsZones({ event, tenantId, zones: initialZones }: Props) {
  const { locale, t } = useLocale()
  const [zones, setZones] = useState(initialZones)
  const [name, setName] = useState('')
  const [externalId, setExternalId] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleCreate(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    setSubmitting(true)
    setError(null)

    const response = await fetch(`/api/v1/tenant/events/${event.id}/acs/zones`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Tenant-ID': tenantId,
        'Idempotency-Key': crypto.randomUUID(),
      },
      body: JSON.stringify({ name, external_acs_zone_id: externalId }),
    })

    const body = await response.json()
    if (!response.ok) {
      setError(body.code ?? body.title ?? 'create_failed')
      setSubmitting(false)
      return
    }

    setZones((prev) => [...prev, body.data as AcsZone])
    setName('')
    setExternalId('')
    setSubmitting(false)
  }

  return (
    <DashboardLayout title={locale === 'ar' ? 'مناطق ACS' : 'ACS zones'}>
      <PageHeader
        title={locale === 'ar' ? 'مناطق ACS' : 'ACS zones'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS', href: `/tenant/events/${event.id}/acs` },
          { label: locale === 'ar' ? 'المناطق' : 'Zones' },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/lanes`}>{locale === 'ar' ? 'المسارات' : 'Lanes'}</LocalizedLink>}
      />
      <PageContent>
        <ZoneLaneEditor zones={zones} lanes={[]} />
        <form className="mt-8 grid gap-4 sm:grid-cols-2" onSubmit={handleCreate}>
          <TextInput label={locale === 'ar' ? 'الاسم' : 'Name'} name="name" value={name} onChange={(e) => setName(e.target.value)} required />
          <TextInput label={locale === 'ar' ? 'المعرف الخارجي' : 'External zone ID'} name="external_acs_zone_id" value={externalId} onChange={(e) => setExternalId(e.target.value)} required />
          {error && <p className="text-red-600 sm:col-span-2" role="alert">{error}</p>}
          <SubmitButtonWithLoader loading={submitting} label={locale === 'ar' ? 'إنشاء منطقة' : 'Create zone'} />
        </form>
      </PageContent>
    </DashboardLayout>
  )
}
