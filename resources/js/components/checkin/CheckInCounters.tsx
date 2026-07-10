import StatCard from '@/components/cards/StatCard'
import { CheckCircle2, Copy, UserCheck, Users } from 'lucide-react'

export interface CheckInSummaryView {
  registered_count: number
  checked_in_count: number
  rejected_count: number
  duplicate_count: number
  last_scan_at: string | null
}

interface CheckInCountersProps {
  summary: CheckInSummaryView
  locale?: 'en' | 'ar'
}

export function CheckInCounters({ summary, locale = 'en' }: CheckInCountersProps) {
  const labels = locale === 'ar'
    ? {
        checkedIn: 'تم تسجيل الحضور',
        rejected: 'مرفوض',
        duplicate: 'مكرر',
        registered: 'مسجل',
        lastScan: 'آخر مسح',
      }
    : {
        checkedIn: 'Checked in',
        rejected: 'Rejected',
        duplicate: 'Duplicate',
        registered: 'Registered',
        lastScan: 'Last scan',
      }

  return (
    <section aria-label={labels.checkedIn} className="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <StatCard label={labels.registered} value={summary.registered_count} icon={Users} accent="sky" />
      <StatCard label={labels.checkedIn} value={summary.checked_in_count} icon={UserCheck} accent="emerald" featured />
      <StatCard label={labels.rejected} value={summary.rejected_count} icon={CheckCircle2} accent="rose" />
      <StatCard label={labels.duplicate} value={summary.duplicate_count} icon={Copy} accent="amber" />
      {summary.last_scan_at ? (
        <p className="text-sm text-[var(--muted)] md:col-span-2 xl:col-span-4">
          {labels.lastScan}: {new Date(summary.last_scan_at).toLocaleString(locale === 'ar' ? 'ar-EG' : 'en-US')}
        </p>
      ) : null}
    </section>
  )
}
