import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { ZoneLaneEditor } from '@/components/acs/ZoneLaneEditor'
import TextInput from '@/components/forms/TextInput'
import SelectInput from '@/components/forms/SelectInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import type { AcsLane, AcsZone } from '@/types/phase4'

type EventRow = { id: string; name: { en: string; ar: string } }

type Props = {
  event: EventRow
  tenantId: string
  zones: AcsZone[]
  lanes: AcsLane[]
}

export default function AcsLanes({ event, tenantId, zones, lanes: initialLanes }: Props) {
  const { locale } = useLocale()
  const [lanes, setLanes] = useState(initialLanes)
  const [zoneId, setZoneId] = useState(zones[0]?.id ?? '')
  const [name, setName] = useState('')
  const [externalId, setExternalId] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleCreate(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    setSubmitting(true)
    setError(null)

    const response = await fetch(`/api/v1/tenant/events/${event.id}/acs/lanes`, {
      method: 'POST',
      credentials: 'include',
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-Tenant-ID': tenantId,
        'Idempotency-Key': crypto.randomUUID(),
      },
      body: JSON.stringify({
        zone_id: zoneId,
        name,
        external_acs_lane_id: externalId,
        gate_type: 'turnstile',
        access_direction: 'entry',
      }),
    })

    const body = await response.json()
    if (!response.ok) {
      setError(body.code ?? body.title ?? 'create_failed')
      setSubmitting(false)
      return
    }

    setLanes((prev) => [...prev, body.data as AcsLane])
    setName('')
    setExternalId('')
    setSubmitting(false)
  }

  return (
    <DashboardLayout title={locale === 'ar' ? 'مسارات ACS' : 'ACS lanes'}>
      <PageHeader
        title={locale === 'ar' ? 'مسارات ACS' : 'ACS lanes'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS', href: `/tenant/events/${event.id}/acs` },
          { label: locale === 'ar' ? 'المسارات' : 'Lanes' },
        ]}
        actions={<LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/rules`}>{locale === 'ar' ? 'القواعد' : 'Rules'}</LocalizedLink>}
      />
      <PageContent>
        <ZoneLaneEditor zones={zones} lanes={lanes} />
        {zones.length === 0 ? (
          <p className="mt-4">{locale === 'ar' ? 'أنشئ منطقة أولاً.' : 'Create a zone first.'}</p>
        ) : (
          <form className="mt-8 grid gap-4 sm:grid-cols-2" onSubmit={handleCreate}>
            <SelectInput
              label={locale === 'ar' ? 'المنطقة' : 'Zone'}
              name="zone_id"
              value={zoneId}
              onChange={(e) => setZoneId(e.target.value)}
              options={zones.map((zone) => ({ value: zone.id, label: zone.name }))}
            />
            <TextInput label={locale === 'ar' ? 'الاسم' : 'Name'} name="name" value={name} onChange={(e) => setName(e.target.value)} required />
            <TextInput label={locale === 'ar' ? 'المعرف الخارجي' : 'External lane ID'} name="external_acs_lane_id" value={externalId} onChange={(e) => setExternalId(e.target.value)} required />
            {error && <p className="text-red-600 sm:col-span-2" role="alert">{error}</p>}
            <SubmitButtonWithLoader loading={submitting} label={locale === 'ar' ? 'إنشاء مسار' : 'Create lane'} />
          </form>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
