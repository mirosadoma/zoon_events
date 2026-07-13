import { FormEvent, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { RuleEditor } from '@/components/acs/RuleEditor'
import SelectInput from '@/components/forms/SelectInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import type { AcsLane, AcsRule, AcsZone } from '@/types/phase4'

type EventRow = { id: string; name: { en: string; ar: string } }
type TicketTypeRow = { id: string; code: string; name: { en: string; ar: string } }

type Props = {
  event: EventRow
  tenantId: string
  zones: AcsZone[]
  lanes: AcsLane[]
  rules: AcsRule[]
  ticketTypes: TicketTypeRow[]
}

export default function AcsRules({ event, tenantId, zones, lanes, rules: initialRules, ticketTypes }: Props) {
  const { locale, t } = useLocale()
  const [rules, setRules] = useState(initialRules)
  const [zoneId, setZoneId] = useState(zones[0]?.id ?? '')
  const [laneId, setLaneId] = useState('')
  const [ticketTypeId, setTicketTypeId] = useState('')
  const [submitting, setSubmitting] = useState(false)
  const [error, setError] = useState<string | null>(null)

  async function handleCreate(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    setSubmitting(true)
    setError(null)

    const response = await fetch(`/api/v1/tenant/events/${event.id}/acs/rules`, {
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
        lane_id: laneId || null,
        ticket_type_id: ticketTypeId || null,
        access_direction: 'entry',
      }),
    })

    const body = await response.json()
    if (!response.ok) {
      setError(body.code ?? body.title ?? 'create_failed')
      setSubmitting(false)
      return
    }

    setRules((prev) => [...prev, body.data as AcsRule])
    setSubmitting(false)
  }

  return (
    <DashboardLayout title={locale === 'ar' ? 'قواعد ACS' : 'ACS rules'}>
      <PageHeader
        title={locale === 'ar' ? 'قواعد ACS' : 'ACS rules'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS', href: `/tenant/events/${event.id}/acs` },
          { label: locale === 'ar' ? 'القواعد' : 'Rules' },
        ]}
      />
      <PageContent>
        <RuleEditor rules={rules} />
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
            <SelectInput
              label={locale === 'ar' ? 'المسار (اختياري)' : 'Lane (optional)'}
              name="lane_id"
              value={laneId}
              onChange={(e) => setLaneId(e.target.value)}
              options={[{ value: '', label: '—' }, ...lanes.filter((lane) => lane.zone_id === zoneId).map((lane) => ({ value: lane.id, label: lane.name }))]}
            />
            <SelectInput
              label={locale === 'ar' ? 'نوع التذكرة (اختياري)' : 'Ticket type (optional)'}
              name="ticket_type_id"
              value={ticketTypeId}
              onChange={(e) => setTicketTypeId(e.target.value)}
              options={[{ value: '', label: '—' }, ...ticketTypes.map((ticket) => ({ value: ticket.id, label: ticket.name[locale] ?? ticket.code }))]}
            />
            {error && <p className="text-red-600 sm:col-span-2" role="alert">{error}</p>}
            <SubmitButtonWithLoader loading={submitting} label={locale === 'ar' ? 'إنشاء قاعدة' : 'Create rule'} />
          </form>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
