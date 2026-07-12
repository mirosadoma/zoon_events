import LocalizedLink from '@/components/routing/LocalizedLink'
import { useEffect, useState } from 'react'
import DashboardLayout from '@/layouts/DashboardLayout'
import { CheckInCounters, type CheckInSummaryView } from '@/components/checkin/CheckInCounters'
import { EmptyState, ErrorState } from '@/components/feedback'
import { PageContent, PageHeader } from '@/components/layout'
import { useLocale } from '@/hooks/useLocale'
import { CHECK_IN_SUMMARY_POLL_INTERVAL_MS } from '@/lib/checkin-polling'

type EventRow = {
  id: string
  name: { en: string; ar: string }
}

type Props = {
  event: EventRow
  tenantId: string
  initialSummary?: CheckInSummaryView | null
  pollIntervalMs?: number
}

export default function CheckInDashboard({
  event,
  tenantId,
  initialSummary = null,
  pollIntervalMs = CHECK_IN_SUMMARY_POLL_INTERVAL_MS,
}: Props) {
  const { locale, t } = useLocale()
  const [summary, setSummary] = useState<CheckInSummaryView | null>(initialSummary)
  const [loading, setLoading] = useState(initialSummary === null)
  const [error, setError] = useState<string | null>(null)

  useEffect(() => {
    let active = true

    async function loadSummary() {
      try {
        const response = await fetch(`/api/v1/tenant/events/${event.id}/check-in-summary`, {
          credentials: 'include',
          headers: {
            Accept: 'application/json',
            'X-Tenant-ID': tenantId,
          },
        })

        if (!response.ok) {
          throw new Error('summary_failed')
        }

        const body = await response.json()
        if (active) {
          setSummary(body.data as CheckInSummaryView)
          setError(null)
        }
      } catch {
        if (active) {
          setError(locale === 'ar' ? 'تعذر تحميل ملخص تسجيل الحضور' : 'Unable to load check-in summary')
        }
      } finally {
        if (active) {
          setLoading(false)
        }
      }
    }

    void loadSummary()
    const timer = window.setInterval(() => {
      void loadSummary()
    }, pollIntervalMs)

    return () => {
      active = false
      window.clearInterval(timer)
    }
  }, [event.id, tenantId, pollIntervalMs, locale])

  const isEmpty = summary !== null
    && summary.checked_in_count === 0
    && summary.rejected_count === 0
    && summary.duplicate_count === 0

  return (
    <DashboardLayout title={locale === 'ar' ? 'لوحة تسجيل الحضور' : 'Check-in dashboard'}>
      <PageHeader
        title={locale === 'ar' ? 'لوحة تسجيل الحضور' : 'Check-in dashboard'}
        description={event.name[locale]}
        breadcrumbs={[
          { label: locale === 'ar' ? 'نظرة عامة' : 'Overview', href: '/dashboard' },
          { label: locale === 'ar' ? 'الفعاليات' : 'Events', href: '/tenant/events' },
          { label: event.name[locale], href: `/tenant/events/${event.id}` },
          { label: locale === 'ar' ? 'لوحة تسجيل الحضور' : 'Check-in dashboard' },
        ]}
        actions={(
          <div className="flex flex-wrap gap-2">
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/scanner`}>{locale === 'ar' ? 'الماسح' : 'Scanner'}</LocalizedLink>
            <LocalizedLink className="button-secondary" href={`/tenant/events/${event.id}/scan-events`}>{locale === 'ar' ? 'أحداث المسح' : 'Scan events'}</LocalizedLink>
          </div>
        )}
      />
      <PageContent>
        {loading ? <p role="status">{t('loading')}</p> : null}
        {error ? <ErrorState title={error} /> : null}
        {!loading && !error && summary !== null && isEmpty ? (
          <EmptyState
            title={locale === 'ar' ? 'لا توجد عمليات مسح بعد' : 'No scans yet'}
            detail={locale === 'ar' ? 'ستظهر العدادات عند أول مسح.' : 'Counters will populate after the first scan.'}
          />
        ) : null}
        {!loading && !error && summary !== null ? (
          <CheckInCounters summary={summary} locale={locale} />
        ) : null}
      </PageContent>
    </DashboardLayout>
  )
}
