import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { ZoneLaneEditor } from '@/components/acs/ZoneLaneEditor'
import TextInput from '@/components/forms/TextInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { ApiFetchError, apiFetch } from '@/lib/apiFetch'
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
  const ar = locale === 'ar'

  async function handleCreate(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    setSubmitting(true)
    setError(null)

    try {
      const created = await apiFetch<AcsZone>(`/api/v1/tenant/events/${event.id}/acs/zones`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: { name, external_acs_zone_id: externalId },
      })

      setZones((prev) => [...prev, created])
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
    <DashboardLayout title={ar ? 'مناطق ACS' : 'ACS zones'}>
      <PageHeader
        title={ar ? 'مناطق ACS' : 'ACS zones'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: ar ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: 'ACS', href: `/tenant/events/${event.id}/acs` },
          { label: ar ? 'المناطق' : 'Zones' },
        ]}
        actions={(
          <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/acs/lanes`}>
            {ar ? 'المسارات' : 'Lanes'}
          </LocalizedLink>
        )}
      />
      <PageContent>
        <ZoneLaneEditor zones={zones} lanes={[]} showLanes={false} />

        <form className="ta-card mt-6 space-y-4" onSubmit={handleCreate}>
          <div>
            <h2 className="text-lg font-semibold text-[var(--ink)]">{ar ? 'إنشاء منطقة' : 'Create zone'}</h2>
            <p className="mt-1 text-sm text-[var(--muted)]">
              {ar ? 'أضف منطقة تحكم جديدة واربطها بمعرف ACS الخارجي.' : 'Add a new access zone and map it to the external ACS id.'}
            </p>
          </div>
          <div className="grid gap-4 sm:grid-cols-2">
            <TextInput label={ar ? 'الاسم' : 'Name'} name="name" value={name} onChange={(e) => setName(e.target.value)} required />
            <TextInput
              label={ar ? 'المعرف الخارجي' : 'External zone ID'}
              name="external_acs_zone_id"
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
          <SubmitButtonWithLoader loading={submitting} label={ar ? 'إنشاء منطقة' : 'Create zone'} />
        </form>
      </PageContent>
    </DashboardLayout>
  )
}
