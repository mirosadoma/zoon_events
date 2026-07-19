import LocalizedLink from '@/components/routing/LocalizedLink'
import { FormEvent, useState } from 'react'
import { Printer, RotateCcw, Search } from 'lucide-react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { AttendeeLookupPanel, DeskSearchHint } from '@/components/manual-desk/AttendeeLookupPanel'
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
    <DashboardLayout title={t('manualDeskPageTitle')}>
      <PageHeader
        title={t('manualDeskPageTitle')}
        description={event.name[locale]}
        breadcrumbs={[
          { label: t('overview'), href: '/dashboard' },
          { label: t('events'), href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: t('manualDeskPageTitle') },
        ]}
        actions={(
          <PermissionGate permission="attendee.walkup.register">
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/manual-desk/walk-up`}>
              {t('manualDeskPageWalkUp')}
            </LocalizedLink>
          </PermissionGate>
        )}
      />
      <PageContent>
        <div className="grid gap-6 lg:grid-cols-[minmax(0,1.2fr)_minmax(0,0.8fr)]">
          <div className="space-y-4">
            <form className="ta-card space-y-4" onSubmit={handleLookup}>
              <div>
                <h2 className="text-lg font-semibold text-[var(--ink)]">
                  {t('manualDeskPageAttendeeLookup')}
                </h2>
                <div className="mt-1">
                  <DeskSearchHint />
                </div>
              </div>
              <div className="flex flex-wrap items-end gap-3">
                <TextInput
                  label={t('manualDeskPageSearch')}
                  name="query"
                  value={query}
                  onChange={(changeEvent) => setQuery(changeEvent.target.value)}
                  placeholder={t('manualDeskPageSearchPlaceholder')}
                  wrapperClassName="min-w-[16rem] flex-1"
                />
                <SubmitButtonWithLoader
                  loading={loading}
                  label={t('manualDeskPageSearch')}
                />
              </div>
            </form>

            <AttendeeLookupPanel
              result={lookupResult}
              loading={loading}
              selecting={submitting}
              onSelect={(match) => {
                if (match.checkin_status === 'rejected') {
                  setOverrideTarget(match)
                  return
                }
                void submitScan(match)
              }}
            />
          </div>

          <div className="space-y-4">
            {scanResult ? (
              <>
                <CheckInResultPanel result={scanResult} />
                {selectedMatch?.attendee_id && selectedMatch.credential_id && scanResult.result === 'accepted' && (
                  <div className="ta-card space-y-3">
                    <h2 className="text-lg font-semibold text-[var(--ink)]">
                      {t('manualDeskPageBadge')}
                    </h2>
                    <p className="text-sm text-[var(--muted)]">
                      {t('manualDeskPageBadgeDescription')}
                    </p>
                    <div className="flex flex-wrap gap-3">
                      <PermissionGate permission="badge.print">
                        <button
                          type="button"
                          className="button-primary inline-flex items-center gap-2"
                          onClick={() => void handlePrint(selectedMatch.attendee_id!, selectedMatch.credential_id!)}
                        >
                          <Printer className="h-4 w-4" aria-hidden />
                          {t('manualDeskPagePrintBadge')}
                        </button>
                      </PermissionGate>
                      <PermissionGate permission="badge.reprint">
                        <button
                          type="button"
                          className="button-secondary inline-flex items-center gap-2"
                          onClick={() => setReprintJobId('latest')}
                        >
                          <RotateCcw className="h-4 w-4" aria-hidden />
                          {t('manualDeskPageReprintBadge')}
                        </button>
                      </PermissionGate>
                    </div>
                  </div>
                )}
              </>
            ) : (
              <div className="ta-card flex min-h-48 flex-col items-center justify-center gap-3 text-center">
                <div className="flex h-12 w-12 items-center justify-center rounded-full bg-[var(--brand-soft)] text-[var(--brand)]">
                  <Search className="h-5 w-5" aria-hidden />
                </div>
                <div>
                  <p className="font-semibold text-[var(--ink)]">
                    {t('manualDeskPageCheckInResult')}
                  </p>
                  <p className="mt-1 text-sm text-[var(--muted)]">
                    {t('manualDeskPageCheckInResultDescription')}
                  </p>
                </div>
              </div>
            )}
          </div>
        </div>
      </PageContent>

      <ReasonModal
        open={overrideTarget !== null}
        title={t('manualDeskPageManualOverride')}
        message={t('manualDeskPageOverrideReason')}
        reasonLabel={t('reason')}
        confirmLabel={t('manualDeskPageOverride')}
        cancelLabel={t('cancel')}
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
        title={t('manualDeskPageReprintBadge')}
        message={t('manualDeskPageReprintReason')}
        reasonLabel={t('reason')}
        confirmLabel={t('kioskPageReprint')}
        cancelLabel={t('cancel')}
        onConfirm={handleReprint}
        onCancel={() => setReprintJobId(null)}
      />
    </DashboardLayout>
  )
}
