import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { EmptyState } from '@/components/feedback'
import { useLocale } from '@/hooks/useLocale'
import type { AcsLane, AcsRule, AcsZone } from '@/types/phase4'

type RuleEditorProps = {
  rules: AcsRule[]
  zones?: AcsZone[]
  lanes?: AcsLane[]
}

export function RuleEditor({ rules, zones = [], lanes = [] }: RuleEditorProps) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const zoneNameById = Object.fromEntries(zones.map((zone) => [zone.id, zone.name]))
  const laneNameById = Object.fromEntries(lanes.map((lane) => [lane.id, lane.name]))

  if (rules.length === 0) {
    return (
      <EmptyState
        title={ar ? 'لا توجد قواعد بعد' : 'No authorization rules yet'}
        detail={ar ? 'أنشئ قاعدة للسماح بالدخول حسب المنطقة ونوع التذكرة.' : 'Create a rule to allow entry by zone and ticket type.'}
      />
    )
  }

  return (
    <section className="space-y-3">
      <div>
        <h2 className="text-lg font-semibold text-[var(--ink)]">{ar ? 'قواعد التفويض' : 'Authorization rules'}</h2>
        <p className="text-sm text-[var(--muted)]">
          {ar ? 'من يُسمح له بالدخول إلى كل منطقة أو مسار.' : 'Who is allowed into each zone or lane.'}
        </p>
      </div>
      <DataTable
        rows={rules as unknown as Record<string, unknown>[]}
        getRowKey={(row) => String(row.id)}
        columns={[
          {
            key: 'zone_id',
            header: ar ? 'المنطقة' : 'Zone',
            render: (row) => {
              const zoneId = String(row.zone_id)
              return zoneNameById[zoneId] ?? `Zone ${zoneId}`
            },
          },
          {
            key: 'lane_id',
            header: ar ? 'المسار' : 'Lane',
            render: (row) => {
              const laneId = row.lane_id as string | null
              if (!laneId) return '—'
              return laneNameById[laneId] ?? laneId
            },
          },
          {
            key: 'access_direction',
            header: ar ? 'الاتجاه' : 'Direction',
            render: (row) => String(row.access_direction),
          },
          {
            key: 'ticket_type_id',
            header: ar ? 'نوع التذكرة' : 'Ticket type',
            render: (row) => (row.ticket_type_id ? String(row.ticket_type_id) : (ar ? 'الكل' : 'Any')),
          },
          {
            key: 'status',
            header: ar ? 'الحالة' : 'Status',
            render: (row) => <StatusBadge status={String(row.status)} />,
          },
        ]}
      />
    </section>
  )
}
