import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { RuleEditor } from '@/components/acs/RuleEditor'
import SelectInput from '@/components/forms/SelectInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
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

    try {
      const created = await apiFetch<AcsRule>(`/api/v1/tenant/events/${event.id}/acs/rules`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: {
          zone_id: zoneId,
          lane_id: laneId || null,
          ticket_type_id: ticketTypeId || null,
          access_direction: 'entry',
        },
      })

      setRules((prev) => [...prev, created])
    } catch (caught) {
      setError(caught instanceof ApiFetchError
        ? (caught.code ?? caught.message)
        : 'create_failed')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <DashboardLayout title={t('acsPageRules')}>
      <PageHeader
        title={t('acsPageRules')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS', href: `/tenant/events/${event.id}/acs` },
          { label: t('acsPageRules') },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/gate-health`}>
            {t('acsPageGateHealth')}
          </LocalizedLink>
        )}
      />
      <PageContent>
        <RuleEditor rules={rules} zones={zones} lanes={lanes} />

        {zones.length === 0 ? (
          <div className="mt-6">
            <EmptyState
              title={t('acsPageCreateZoneFirst')}
              detail={t('acsPageRulesNeedZone')}
              action={(
                <LocalizedLink className="button-primary" href={`/tenant/events/${event.id}/acs/zones`}>
                  {t('acsPageAddZone')}
                </LocalizedLink>
              )}
            />
          </div>
        ) : (
          <form className="ta-card mt-6 space-y-4" onSubmit={handleCreate}>
            <div>
              <h2 className="text-lg font-semibold text-[var(--ink)]">{t('acsPageCreateRule')}</h2>
              <p className="mt-1 text-sm text-[var(--muted)]">
                {t('acsPageCreateRuleDescription')}
              </p>
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
              <SelectInput
                label={t('zone')}
                name="zone_id"
                value={zoneId}
                onChange={(e) => setZoneId(e.target.value)}
                options={zones.map((zone) => ({ value: zone.id, label: zone.name }))}
              />
              <SelectInput
                label={t('acsPageLaneOptional')}
                name="lane_id"
                value={laneId}
                onChange={(e) => setLaneId(e.target.value)}
                options={[
                  { value: '', label: '—' },
                  ...lanes
                    .filter((lane) => lane.zone_id === zoneId)
                    .map((lane) => ({ value: lane.id, label: lane.name })),
                ]}
              />
              <SelectInput
                label={t('acsPageTicketTypeOptional')}
                name="ticket_type_id"
                value={ticketTypeId}
                onChange={(e) => setTicketTypeId(e.target.value)}
                options={[
                  { value: '', label: '—' },
                  ...ticketTypes.map((ticket) => ({ value: ticket.id, label: ticket.name[locale] ?? ticket.code })),
                ]}
              />
            </div>
            {error && (
              <p className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300" role="alert">
                {error}
              </p>
            )}
            <SubmitButtonWithLoader loading={submitting} label={t('acsPageCreateRule')} />
          </form>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
