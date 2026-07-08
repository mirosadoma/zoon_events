import { Link, router } from '@inertiajs/react'
import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { DetailsCard } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { CredentialDialog } from '@/components/credentials/CredentialDialog'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type CredentialDetail = {
  id: string
  code: string
  attendee_id: string
  status: string
  issued_at?: string | null
  expires_at?: string | null
  revoked_at?: string | null
  revocation_reason?: string | null
  superseded_by_id?: string | null
}

type Props = {
  event: EventRow
  credential: CredentialDetail
  tenantId: string
}

export default function CredentialDetailPage({ event, credential, tenantId }: Props) {
  const { locale } = useLocale()
  const { toast } = useToast()
  const [busyAction, setBusyAction] = useState<'revoke' | 'reissue' | null>(null)

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
    setBusyAction('revoke')
    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/credentials/${credential.id}/revoke`, {
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
      router.reload()
      return true
    } catch {
      toast(locale === 'ar' ? 'تعذر إلغاء بيانات الدخول.' : 'Failed to revoke credential.', 'error')
      return false
    } finally {
      setBusyAction(null)
    }
  }

  async function handleReissue(reason: string) {
    setBusyAction('reissue')
    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/credentials/${credential.id}/reissue`, {
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
      router.reload()
      return true
    } catch {
      toast(locale === 'ar' ? 'تعذر إعادة إصدار بيانات الدخول.' : 'Failed to reissue credential.', 'error')
      return false
    } finally {
      setBusyAction(null)
    }
  }

  return (
    <DashboardLayout title={credential.code}>
      <PageHeader
        title={credential.code}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'بيانات الدخول' : 'Credentials', href: `/tenant/events/${event.id}/credentials` },
          { label: credential.code },
        ]}
      />
      <PageContent>
        <DetailsCard
          title={locale === 'ar' ? 'تفاصيل بيانات الدخول' : 'Credential details'}
          items={[
            { label: locale === 'ar' ? 'الحالة' : 'Status', value: <StatusBadge status={credential.status} /> },
            {
              label: locale === 'ar' ? 'الحاضر' : 'Attendee',
              value: (
                <Link href={`/tenant/events/${event.id}/attendees/${credential.attendee_id}`} className="text-sky-700 hover:underline">
                  {credential.attendee_id.slice(-8)}
                </Link>
              ),
            },
            { label: locale === 'ar' ? 'تاريخ الإصدار' : 'Issued', value: credential.issued_at ?? '—' },
            { label: locale === 'ar' ? 'تاريخ الانتهاء' : 'Expires', value: credential.expires_at ?? '—' },
            { label: locale === 'ar' ? 'تاريخ الإلغاء' : 'Revoked', value: credential.revoked_at ?? '—' },
            { label: locale === 'ar' ? 'سبب الإلغاء' : 'Revoke reason', value: credential.revocation_reason ?? '—' },
          ]}
        />

        <CredentialDialog
          status={credential.status}
          loading={busyAction !== null}
          onRevoked={handleRevoke}
          onReissued={handleReissue}
        />
      </PageContent>
    </DashboardLayout>
  )
}
