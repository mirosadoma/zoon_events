import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { ZoneLaneEditor } from '@/components/acs/ZoneLaneEditor'
import TextInput from '@/components/forms/TextInput'
import SelectInput from '@/components/forms/SelectInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { EmptyState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
import type { AcsLane, AcsZone } from '@/types/phase4'

type EventRow = { id: string; name: { en: string; ar: string } }

type Props = {
  event: EventRow
  tenantId: string
  zones: AcsZone[]
  lanes: AcsLane[]
}

export default function AcsLanes({ event, tenantId, zones, lanes: initialLanes }: Props) {
  const { locale, t } = useLocale()
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

    try {
      const created = await apiFetch<AcsLane>(`/api/v1/tenant/events/${event.id}/acs/lanes`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: {
          zone_id: zoneId,
          name,
          external_acs_lane_id: externalId,
          gate_type: 'turnstile',
          access_direction: 'entry',
        },
      })

      setLanes((prev) => [...prev, created])
      setName('')
      setExternalId('')
    } catch (caught) {
      setError(caught instanceof ApiFetchError
        ? (caught.code ?? caught.message)
        : 'create_failed')
    } finally {
      setSubmitting(false)
    }
  }

  return (
    <DashboardLayout title={t('acsPageLanes')}>
      <PageHeader
        title={t('acsPageLanes')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS', href: `/tenant/events/${event.id}/acs` },
          { label: t('acsPageLanes') },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/rules`}>
            {t('acsPageRules')}
          </LocalizedLink>
        )}
      />
      <PageContent>
        <ZoneLaneEditor zones={zones} lanes={lanes} showZones={false} />

        {zones.length === 0 ? (
          <div className="mt-6">
            <EmptyState
              title={t('acsPageCreateZoneFirst')}
              detail={t('acsPageLanesRequireZone')}
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
              <h2 className="text-lg font-semibold text-[var(--ink)]">{t('acsPageCreateLane')}</h2>
              <p className="mt-1 text-sm text-[var(--muted)]">
                {t('acsPageCreateLaneDescription')}
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
              <TextInput label={t('name')} name="name" value={name} onChange={(e) => setName(e.target.value)} required />
              <TextInput
                label={t('acsPageExternalLaneId')}
                name="external_acs_lane_id"
                value={externalId}
                onChange={(e) => setExternalId(e.target.value)}
                required
              />
            </div>
            {error && (
              <p className="rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-700 dark:border-red-900 dark:bg-red-950/40 dark:text-red-300" role="alert">
                {error}
              </p>
            )}
            <SubmitButtonWithLoader loading={submitting} label={t('acsPageCreateLane')} />
          </form>
        )}
      </PageContent>
    </DashboardLayout>
  )
}
