import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { DetailsCard } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { CredentialDialog } from '@/components/credentials/CredentialDialog'
import PermissionGate from '@/components/layout/PermissionGate'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type CredentialSummary = {
  id: string
  status: string
  issued_at?: string | null
  expires_at?: string | null
  revoked_at?: string | null
  revocation_reason?: string | null
}

type AttendeeDetail = {
  id: string
  label: string
  status: string
  locale: string
  order_id?: string | null
  ticket_type_id?: string | null
  registered_at?: string | null
  first_checked_in_at?: string | null
  origin?: string | null
  credential?: CredentialSummary | null
}

type IdentityState = {
  status: string
  pending: boolean
  reason_code?: string | null
  requirement_level: string
}

type Props = {
  event: EventRow
  attendee: AttendeeDetail
  tenantId: string
  identity?: IdentityState | null
}

export default function AttendeeDetailPage({ event, attendee, tenantId, identity }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const [busyAction, setBusyAction] = useState<'revoke' | 'reissue' | 'print' | 'checkin' | null>(null)
  const apiHeaders = {
    Accept: 'application/json',
    'Content-Type': 'application/json',
    'X-Tenant-ID': tenantId,
  }

  function extractError(body: unknown, fallback: string): string {
    if (typeof body !== 'object' || body === null) {
      return fallback
    }
    const maybe = body as { detail?: string; message?: string; title?: string; code?: string }
    return maybe.detail ?? maybe.message ?? maybe.title ?? maybe.code ?? fallback
  }

  async function handleRevoke(reason: string) {
    if (!attendee.credential) return false
    setBusyAction('revoke')
    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/credentials/${attendee.credential.id}/revoke`, {
        method: 'POST',
        credentials: 'include',
        headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify({ reason }),
      })
      const body = await response.json()
      if (!response.ok) {
        toast(extractError(body, locale === 'ar' ? 'تعذر إلغاء بيانات الدخول.' : 'Failed to revoke credential.'), 'error')
        return false
      }
      toast(locale === 'ar' ? 'تم إلغاء بيانات الدخول.' : 'Credential revoked.', 'success')
      router.reload({ only: ['attendee'] })
      return true
    } catch {
      toast(locale === 'ar' ? 'تعذر إلغاء بيانات الدخول.' : 'Failed to revoke credential.', 'error')
      return false
    } finally {
      setBusyAction(null)
    }
  }

  async function handleReissue(reason: string) {
    if (!attendee.credential) return false
    setBusyAction('reissue')
    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/credentials/${attendee.credential.id}/reissue`, {
        method: 'POST',
        credentials: 'include',
        headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify({ reason }),
      })
      const body = await response.json()
      if (!response.ok) {
        toast(extractError(body, locale === 'ar' ? 'تعذر إعادة إصدار بيانات الدخول.' : 'Failed to reissue credential.'), 'error')
        return false
      }
      toast(locale === 'ar' ? 'تمت إعادة إصدار بيانات الدخول.' : 'Credential reissued.', 'success')
      router.reload({ only: ['attendee'] })
      return true
    } catch {
      toast(locale === 'ar' ? 'تعذر إعادة إصدار بيانات الدخول.' : 'Failed to reissue credential.', 'error')
      return false
    } finally {
      setBusyAction(null)
    }
  }

  async function handlePrintBadge() {
    if (!attendee.credential) return
    setBusyAction('print')
    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/badge-print-jobs`, {
        method: 'POST',
        credentials: 'include',
        headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify({ attendee_id: attendee.id, credential_id: attendee.credential.id }),
      })
      const body = await response.json()
      if (!response.ok) {
        toast(extractError(body, locale === 'ar' ? 'تعذرت طباعة البطاقة.' : 'Failed to print badge.'), 'error')
        return
      }
      toast(locale === 'ar' ? 'تم إنشاء مهمة طباعة البطاقة.' : 'Badge print job created.', 'success')
    } catch {
      toast(locale === 'ar' ? 'تعذرت طباعة البطاقة.' : 'Failed to print badge.', 'error')
    } finally {
      setBusyAction(null)
    }
  }

  async function handleManualCheckIn() {
    if (!attendee.credential) return
    setBusyAction('checkin')
    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/scans`, {
        method: 'POST',
        credentials: 'include',
        headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
        body: JSON.stringify({
          scanner_type: 'manual_desk',
          credential_id: attendee.credential.id,
          override: false,
          override_reason: null,
        }),
      })
      const body = await response.json()
      if (!response.ok) {
        toast(extractError(body, locale === 'ar' ? 'تعذر تسجيل الحضور يدويًا.' : 'Failed to check in attendee manually.'), 'error')
        return
      }
      toast(locale === 'ar' ? 'تم تسجيل الحضور.' : 'Attendee checked in.', 'success')
      router.reload({ only: ['attendee'] })
    } catch {
      toast(locale === 'ar' ? 'تعذر تسجيل الحضور يدويًا.' : 'Failed to check in attendee manually.', 'error')
    } finally {
      setBusyAction(null)
    }
  }

  return (
    <DashboardLayout title={attendee.label}>
      <PageHeader
        title={attendee.label}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'الحضور' : 'Attendees', href: `/tenant/events/${event.id}/attendees` },
          { label: attendee.label },
        ]}
      />
      <PageContent>
        {identity?.pending ? (
          <section className="state-panel mb-6 border-amber-200 bg-amber-50" role="status">
            <div className="flex flex-wrap items-center gap-3">
              <StatusBadge status={identity.status} />
              <p className="text-sm text-amber-900">{t('identityPendingIssuanceBanner')}</p>
            </div>
          </section>
        ) : null}

        <DetailsCard
          title={locale === 'ar' ? 'ملف الحاضر' : 'Attendee profile'}
          items={[
            { label: locale === 'ar' ? 'تسجيل الحضور' : 'Check-in status', value: <StatusBadge status={attendee.status} /> },
            { label: locale === 'ar' ? 'اللغة' : 'Locale', value: attendee.locale },
            { label: locale === 'ar' ? 'المصدر' : 'Origin', value: attendee.origin ?? '—' },
            { label: locale === 'ar' ? 'تاريخ التسجيل' : 'Registered', value: attendee.registered_at ?? '—' },
            { label: locale === 'ar' ? 'أول تسجيل حضور' : 'First check-in', value: attendee.first_checked_in_at ?? '—' },
            {
              label: locale === 'ar' ? 'الطلب' : 'Order',
              value: attendee.order_id
                ? (
                  <LocalizedLink href={`/tenant/events/${event.id}/orders/${String(attendee.order_id)}`} className="text-sky-700 hover:underline">
                    {String(attendee.order_id).slice(-8)}
                  </LocalizedLink>
                )
                : '—',
            },
          ]}
        />

        {attendee.credential && (
          <section className="state-panel mt-6">
            <h2 className="text-lg font-semibold">{locale === 'ar' ? 'بيانات الدخول' : 'Credential'}</h2>
            <dl className="mt-4 grid gap-3 sm:grid-cols-2">
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">{locale === 'ar' ? 'الحالة' : 'Status'}</dt>
                <dd className="mt-1"><StatusBadge status={attendee.credential.status} /></dd>
              </div>
              <div>
                <dt className="text-xs uppercase tracking-wide text-slate-500">{locale === 'ar' ? 'الرمز' : 'Credential'}</dt>
                <dd className="mt-1">
                  <LocalizedLink href={`/tenant/events/${event.id}/credentials/${attendee.credential.id}`} className="text-sky-700 hover:underline">
                    {attendee.credential.id.slice(-8)}
                  </LocalizedLink>
                </dd>
              </div>
            </dl>
          </section>
        )}

        {attendee.credential && (
          <section className="state-panel mt-6">
            <h2 className="text-lg font-semibold">{locale === 'ar' ? 'إجراءات الحاضر' : 'Attendee actions'}</h2>
            <div className="mt-4 flex flex-wrap gap-2">
              <PermissionGate permission="badge.print">
                <button type="button" className="button-secondary" onClick={() => void handlePrintBadge()} disabled={busyAction !== null}>
                  {locale === 'ar' ? 'طباعة البطاقة' : 'Print badge'}
                </button>
              </PermissionGate>
              <PermissionGate permission="checkin.desk.perform">
                <button type="button" className="button-secondary" onClick={() => void handleManualCheckIn()} disabled={busyAction !== null}>
                  {locale === 'ar' ? 'تسجيل حضور يدوي' : 'Manual check-in'}
                </button>
              </PermissionGate>
            </div>
          </section>
        )}

        {attendee.credential && (
          <CredentialDialog
            status={attendee.credential.status}
            loading={busyAction !== null}
            onRevoked={handleRevoke}
            onReissued={handleReissue}
          />
        )}
      </PageContent>
    </DashboardLayout>
  )
}
