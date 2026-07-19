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
  const { locale, t } = useLocale()
  const ar = locale === 'ar'
  const zoneNameById = Object.fromEntries(zones.map((zone) => [zone.id, zone.name]))
  const laneNameById = Object.fromEntries(lanes.map((lane) => [lane.id, lane.name]))

  if (rules.length === 0) {
    return (
      <EmptyState
        title={t('acsRulesEmpty')}
        detail={t('acsRulesEmptyDetail')}
      />
    )
  }

  return (
    <section className="space-y-3">
      <div>
        <h2 className="text-lg font-semibold text-[var(--ink)]">{t('acsRulesTitle')}</h2>
        <p className="text-sm text-[var(--muted)]">
          {t('acsRulesDescription')}
        </p>
      </div>
      <DataTable
        rows={rules as unknown as Record<string, unknown>[]}
        getRowKey={(row) => String(row.id)}
        columns={[
          {
            key: 'zone_id',
            header: t('acsRuleZone'),
            render: (row) => {
              const zoneId = String(row.zone_id)
              return zoneNameById[zoneId] ?? `Zone ${zoneId}`
            },
          },
          {
            key: 'lane_id',
            header: t('acsRuleLane'),
            render: (row) => {
              const laneId = row.lane_id as string | null
              if (!laneId) return '—'
              return laneNameById[laneId] ?? laneId
            },
          },
          {
            key: 'access_direction',
            header: t('acsRuleDirection'),
            render: (row) => String(row.access_direction),
          },
          {
            key: 'ticket_type_id',
            header: t('acsRuleTicketType'),
            render: (row) => (row.ticket_type_id ? String(row.ticket_type_id) : t('acsRuleTicketTypeAny')),
          },
          {
            key: 'status',
            header: t('status'),
            render: (row) => <StatusBadge status={String(row.status)} />,
          },
        ]}
      />
    </section>
  )
}
