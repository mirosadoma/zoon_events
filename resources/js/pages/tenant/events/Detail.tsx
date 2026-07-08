import { Link, router } from '@inertiajs/react'
import { useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { DetailsCard } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import PermissionGate from '@/components/layout/PermissionGate'
import ConfirmModal from '@/components/modals/ConfirmModal'
import StatusBadge from '@/components/status/StatusBadge'
import { useLocale } from '@/hooks/useLocale'
import { useToast } from '@/hooks/useToast'

type EventRow = {
  id: string
  name: { en: string; ar: string }
  status: string
  tier: string
  timezone: string
  start_at?: string | null
  end_at?: string | null
  capacity?: number | null
}

type Props = {
  event: EventRow
  tabs: Array<{ label: string; href: string }>
  tenantId: string
}

export default function EventDetail({ event, tabs, tenantId }: Props) {
  const { locale } = useLocale()
  const { toast } = useToast()
  const [publishOpen, setPublishOpen] = useState(false)
  const [cancelOpen, setCancelOpen] = useState(false)
  const [submitting, setSubmitting] = useState<'publish' | 'cancel' | null>(null)

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

  async function runStatusAction(action: 'publish' | 'cancel') {
    setSubmitting(action)
    try {
      const response = await fetch(`/api/v1/tenant/events/${event.id}/${action}`, {
        method: 'POST',
        credentials: 'include',
        headers: { ...apiHeaders, 'Idempotency-Key': crypto.randomUUID() },
      })
      const body = await response.json()
      if (!response.ok) {
        toast(
          extractError(body, action === 'publish'
            ? (locale === 'ar' ? 'تعذر نشر الفعالية.' : 'Failed to publish event.')
            : (locale === 'ar' ? 'تعذر إلغاء الفعالية.' : 'Failed to cancel event.')),
          'error',
        )
        return
      }
      toast(
        action === 'publish'
          ? (locale === 'ar' ? 'تم نشر الفعالية.' : 'Event published.')
          : (locale === 'ar' ? 'تم إلغاء الفعالية.' : 'Event cancelled.'),
        'success',
      )
      setPublishOpen(false)
      setCancelOpen(false)
      router.reload({ only: ['event'] })
    } catch {
      toast(
        action === 'publish'
          ? (locale === 'ar' ? 'تعذر نشر الفعالية.' : 'Failed to publish event.')
          : (locale === 'ar' ? 'تعذر إلغاء الفعالية.' : 'Failed to cancel event.'),
        'error',
      )
    } finally {
      setSubmitting(null)
    }
  }

  return (
    <DashboardLayout title={event.name[locale]}>
      <PageHeader
        title={event.name[locale]}
        description={locale === 'ar' ? 'تفاصيل الفعالية وإعداداتها.' : 'Event details and configuration.'}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale] },
        ]}
        actions={(
          <div className="flex flex-wrap gap-2">
            <Link className="button-secondary" href={`/tenant/events/${event.id}/edit`}>{locale === 'ar' ? 'تعديل' : 'Edit'}</Link>
            <PermissionGate permission="event.publish">
              <button type="button" className="button-primary" onClick={() => setPublishOpen(true)}>{locale === 'ar' ? 'نشر' : 'Publish'}</button>
            </PermissionGate>
            <PermissionGate permission="event.cancel">
              <button type="button" className="button-secondary" onClick={() => setCancelOpen(true)}>{locale === 'ar' ? 'إلغاء' : 'Cancel'}</button>
            </PermissionGate>
          </div>
        )}
      />
      <PageContent>
        <DetailsCard
          title={locale === 'ar' ? 'ملخص الفعالية' : 'Event summary'}
          items={[
            { label: locale === 'ar' ? 'الحالة' : 'Status', value: <StatusBadge status={event.status} /> },
            { label: locale === 'ar' ? 'الفئة' : 'Tier', value: event.tier },
            { label: locale === 'ar' ? 'المنطقة الزمنية' : 'Timezone', value: event.timezone },
            { label: locale === 'ar' ? 'السعة' : 'Capacity', value: event.capacity ?? '—' },
            { label: locale === 'ar' ? 'البداية' : 'Starts', value: event.start_at ?? '—' },
            { label: locale === 'ar' ? 'النهاية' : 'Ends', value: event.end_at ?? '—' },
          ]}
        />

        <section className="state-panel">
          <h2 className="text-lg font-semibold">{locale === 'ar' ? 'أقسام الفعالية' : 'Event sections'}</h2>
          <div className="mt-4 flex flex-wrap gap-2">
            {tabs.map((tab) => (
              <Link key={tab.href} href={tab.href} className="button-secondary">{tab.label}</Link>
            ))}
          </div>
        </section>
      </PageContent>

      <ConfirmModal
        open={publishOpen}
        title={locale === 'ar' ? 'نشر الفعالية' : 'Publish event'}
        message={locale === 'ar' ? 'سيتم نشر الفعالية للمشتركين.' : 'This will publish the event for attendees.'}
        confirmLabel={locale === 'ar' ? 'نشر' : 'Publish'}
        loading={submitting !== null}
        onConfirm={() => void runStatusAction('publish')}
        onCancel={() => setPublishOpen(false)}
      />
      <ConfirmModal
        open={cancelOpen}
        title={locale === 'ar' ? 'إلغاء الفعالية' : 'Cancel event'}
        message={locale === 'ar' ? 'سيتم إيقاف التسجيل والعمليات المرتبطة.' : 'This will stop registration and related operations.'}
        confirmLabel={locale === 'ar' ? 'تأكيد الإلغاء' : 'Confirm cancel'}
        loading={submitting !== null}
        onConfirm={() => void runStatusAction('cancel')}
        onCancel={() => setCancelOpen(false)}
      />
    </DashboardLayout>
  )
}
