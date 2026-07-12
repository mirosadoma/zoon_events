import { router } from '@inertiajs/react'
import LocalizedLink from '@/components/routing/LocalizedLink'
import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { DetailsCard } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { CredentialDialog } from '@/components/credentials/CredentialDialog'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useTenantId } from '@/hooks/useTenantId'
import { useToast } from '@/hooks/useToast'
import { apiFetch, ApiFetchError } from '@/lib/apiFetch'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type CredentialDetail = {
  id: string
  code: string
  attendee_id: string
  attendee_label?: string | null
  status: string
  issued_at?: string | null
  expires_at?: string | null
  revoked_at?: string | null
  revocation_reason?: string | null
  superseded_by_id?: string | null
}

type IdentityState = {
  status: string
  pending: boolean
  reason_code?: string | null
  requirement_level: string
}

type Props = {
  event: EventRow
  credential: CredentialDetail
  tenantId: string
  identity?: IdentityState | null
}

export default function CredentialDetailPage({ event, credential, tenantId: pageTenantId, identity }: Props) {
  const { locale, t } = useLocale()
  const { toast } = useToast()
  const tenantId = useTenantId(pageTenantId)
  const [busyAction, setBusyAction] = useState<'revoke' | 'reissue' | null>(null)

  function extractError(error: unknown, fallback: string): string {
    if (error instanceof ApiFetchError) {
      return error.message
    }

    return fallback
  }

  function ensureTenantId(): boolean {
    if (tenantId) {
      return true
    }

    toast(locale === 'ar' ? 'سياق المستأجر غير متوفر.' : 'Tenant context is unavailable.', 'error')

    return false
  }

  async function handleRevoke(reason: string) {
    if (!ensureTenantId()) return false
    setBusyAction('revoke')
    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/credentials/${credential.id}/revoke`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: { reason },
      })
      toast(locale === 'ar' ? 'تم إلغاء بيانات الدخول.' : 'Credential revoked.', 'success')
      router.reload()
      return true
    } catch (error) {
      toast(extractError(error, locale === 'ar' ? 'تعذر إلغاء بيانات الدخول.' : 'Failed to revoke credential.'), 'error')
      return false
    } finally {
      setBusyAction(null)
    }
  }

  async function handleReissue(reason: string) {
    if (!ensureTenantId()) return false
    setBusyAction('reissue')
    try {
      await apiFetch(`/api/v1/tenant/events/${event.id}/credentials/${credential.id}/reissue`, {
        method: 'POST',
        tenantId,
        idempotency: true,
        body: { reason },
      })
      toast(locale === 'ar' ? 'تمت إعادة إصدار بيانات الدخول.' : 'Credential reissued.', 'success')
      router.reload()
      return true
    } catch (error) {
      toast(extractError(error, locale === 'ar' ? 'تعذر إعادة إصدار بيانات الدخول.' : 'Failed to reissue credential.'), 'error')
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
        {identity?.pending ? (
          <section className="state-panel mb-6 border-amber-200 bg-amber-50" role="status">
            <div className="flex flex-col gap-3">
              <div className="flex flex-wrap items-center gap-3">
                <StatusBadge status={identity.status} />
                <p className="text-sm text-amber-900">{t('identityPendingIssuanceBanner')}</p>
              </div>
              <p className="text-sm text-amber-900">{t('identityPendingIssuanceHelp')}</p>
              <LocalizedLink
                href={`/tenant/events/${event.id}/identity/review`}
                className="text-sm font-medium text-sky-800 hover:underline"
              >
                {t('openIdentityReviewQueue')}
              </LocalizedLink>
            </div>
          </section>
        ) : null}

        <DetailsCard
          title={locale === 'ar' ? 'تفاصيل بيانات الدخول' : 'Credential details'}
          items={[
            { label: locale === 'ar' ? 'الحالة' : 'Status', value: <StatusBadge status={credential.status} /> },
            {
              label: locale === 'ar' ? 'الحاضر' : 'Attendee',
              value: (
                <LocalizedLink href={`/tenant/events/${event.id}/attendees/${String(credential.attendee_id)}`} className="text-sky-700 hover:underline">
                  {credential.attendee_label ?? String(credential.attendee_id)}
                </LocalizedLink>
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
