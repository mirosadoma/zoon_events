import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { AttendeeLookupPanel } from '@/components/manual-desk/AttendeeLookupPanel'
import { CheckInResultPanel } from '@/components/manual-desk/CheckInResultPanel'
import TextInput from '@/components/forms/TextInput'
import SubmitButtonWithLoader from '@/components/forms/SubmitButtonWithLoader'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import ReasonModal from '@/components/modals/ReasonModal'
import { useLocale } from '@/hooks/useLocale'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type TicketTypeRow = {
  id: string
  code: string
  name: { en: string; ar: string }
}

type LookupMatch = {
  attendee_id: string | null
  credential_id: string | null
  display_name: string | null
  ticket_type_label: string | null
  checkin_status: string
}

type ScanResult = {
  scan_event_id: string
  result: string
  reason_code: string | null
  attendee_display_name: string | null
  ticket_type_label: string | null
}

type Props = {
  event: EventRow
  tenantId: string
  ticketTypes: TicketTypeRow[]
}

export default function ManualDesk({ event, tenantId }: Props) {
  const { locale, t } = useLocale()
  const [query, setQuery] = useState('')
  const [lookupResult, setLookupResult] = useState<{ too_many: boolean; matches: LookupMatch[] } | null>(null)
  const [scanResult, setScanResult] = useState<ScanResult | null>(null)
  const [loading, setLoading] = useState(false)
  const [submitting, setSubmitting] = useState(false)
  const [overrideTarget, setOverrideTarget] = useState<LookupMatch | null>(null)
  const [reprintJobId, setReprintJobId] = useState<string | null>(null)
  const [selectedMatch, setSelectedMatch] = useState<LookupMatch | null>(null)

  const apiHeaders = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Tenant-ID': tenantId,
  }

  async function handleLookup(formEvent: FormEvent<HTMLFormElement>) {
    formEvent.preventDefault()
    setLoading(true)
    setLookupResult(null)
    setScanResult(null)
    setSelectedMatch(null)

    const response = await fetch(`/api/v1/tenant/events/${event.id}/desk/lookups`, {
      method: 'POST',
      credentials: 'include',
      headers: apiHeaders,
      body: JSON.stringify({ query }),
    })

    const body = await response.json()
    setLookupResult(body.data ?? null)
    setLoading(false)
  }

  async function submitScan(match: LookupMatch, override = false, overrideReason?: string) {
    if (!match.credential_id) return

    setSubmitting(true)
    setScanResult(null)

    const response = await fetch(`/api/v1/tenant/events/${event.id}/scans`, {
      method: 'POST',
      credentials: 'include',
      headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
      body: JSON.stringify({
        scanner_type: 'manual_desk',
        credential_id: match.credential_id,
        override,
        override_reason: overrideReason ?? null,
      }),
    })

    const body = await response.json()
    setScanResult(body.data ?? null)
    setSelectedMatch(match)
    setSubmitting(false)
    setOverrideTarget(null)
  }

  async function handlePrint(attendeeId: string, credentialId: string) {
    await fetch(`/api/v1/tenant/events/${event.id}/badge-print-jobs`, {
      method: 'POST',
      credentials: 'include',
      headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
      body: JSON.stringify({ attendee_id: attendeeId, credential_id: credentialId }),
    })
  }

  async function handleReprint(reason: string) {
    if (!reprintJobId) return

    await fetch(`/api/v1/tenant/events/${event.id}/badge-print-jobs/${reprintJobId}/reprint`, {
      method: 'POST',
      credentials: 'include',
      headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
      body: JSON.stringify({ reprint_reason: reason }),
    })
    setReprintJobId(null)
  }

  return (
    <DashboardLayout title={locale === 'ar' ? 'مكتب الاستقبال' : 'Manual desk'}>
      <PageHeader
        title={locale === 'ar' ? 'مكتب الاستقبال' : 'Manual desk'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'مكتب الاستقبال' : 'Manual desk' },
        ]}
        actions={
          <PermissionGate permission="attendee.walkup.register">
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/manual-desk/walk-up`}>
              {locale === 'ar' ? 'تسجيل مباشر' : 'Walk-up registration'}
            </LocalizedLink>
          </PermissionGate>
        }
      />
      <PageContent>
        <form className="flex flex-wrap gap-3" onSubmit={handleLookup}>
          <TextInput
            label={locale === 'ar' ? 'بحث' : 'Search'}
            name="query"
            value={query}
            onChange={(changeEvent) => setQuery(changeEvent.target.value)}
            placeholder={locale === 'ar' ? 'الاسم أو البريد أو الهاتف' : 'Name, email, or phone'}
          />
          <SubmitButtonWithLoader loading={loading} label={locale === 'ar' ? 'بحث' : 'Search'} />
        </form>

        <AttendeeLookupPanel
          result={lookupResult}
          loading={loading}
          onSelect={(match) => {
            if (match.checkin_status === 'rejected') {
              setOverrideTarget(match)
              return
            }
            void submitScan(match)
          }}
        />

        <CheckInResultPanel result={scanResult} />

        {selectedMatch?.attendee_id && selectedMatch.credential_id && scanResult?.result === 'accepted' && (
          <div className="mt-4 flex flex-wrap gap-3">
            <PermissionGate permission="badge.print">
              <button
                type="button"
                className="button-primary"
                onClick={() => void handlePrint(selectedMatch.attendee_id!, selectedMatch.credential_id!)}
              >
                {locale === 'ar' ? 'طباعة الشارة' : 'Print badge'}
              </button>
            </PermissionGate>
            <PermissionGate permission="badge.reprint">
              <button
                type="button"
                className="button-secondary"
                onClick={() => setReprintJobId('latest')}
              >
                {locale === 'ar' ? 'إعادة طباعة' : 'Reprint badge'}
              </button>
            </PermissionGate>
          </div>
        )}
      </PageContent>

      <ReasonModal
        open={overrideTarget !== null}
        title={locale === 'ar' ? 'تجاوز تسجيل الحضور' : 'Manual override'}
        message={locale === 'ar' ? 'يرجى تقديم سبب للتجاوز.' : 'Please provide a reason for this override.'}
        reasonLabel={locale === 'ar' ? 'السبب' : 'Reason'}
        confirmLabel={locale === 'ar' ? 'تجاوز' : 'Override'}
        cancelLabel={locale === 'ar' ? 'إلغاء' : 'Cancel'}
        loading={submitting}
        onConfirm={(reason) => {
          if (overrideTarget) {
            void submitScan(overrideTarget, true, reason)
          }
        }}
        onCancel={() => setOverrideTarget(null)}
      />

      <ReasonModal
        open={reprintJobId !== null}
        title={locale === 'ar' ? 'إعادة طباعة الشارة' : 'Reprint badge'}
        message={locale === 'ar' ? 'يرجى تقديم سبب لإعادة الطباعة.' : 'Please provide a reason for this reprint.'}
        reasonLabel={locale === 'ar' ? 'السبب' : 'Reason'}
        confirmLabel={locale === 'ar' ? 'إعادة طباعة' : 'Reprint'}
        cancelLabel={locale === 'ar' ? 'إلغاء' : 'Cancel'}
        onConfirm={handleReprint}
        onCancel={() => setReprintJobId(null)}
      />
    </DashboardLayout>
  )
}
