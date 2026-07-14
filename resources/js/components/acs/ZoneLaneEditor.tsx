import StatusBadge from '@/components/status/StatusBadge'
import DataTable from '@/components/tables/DataTable'
import { EmptyState } from '@/components/feedback'
import { useLocale } from '@/hooks/useLocale'
import type { AcsLane, AcsZone } from '@/types/phase4'

type ZoneLaneEditorProps = {
  zones: AcsZone[]
  lanes: AcsLane[]
  showZones?: boolean
  showLanes?: boolean
}

export function ZoneLaneEditor({
  zones,
  lanes,
  showZones = true,
  showLanes = true,
}: ZoneLaneEditorProps) {
  const { locale } = useLocale()
  const ar = locale === 'ar'
  const zoneNameById = Object.fromEntries(zones.map((zone) => [zone.id, zone.name]))

  return (
    <div className="space-y-6">
      {showZones && (
        <section className="space-y-3">
          <div>
            <h2 className="text-lg font-semibold text-[var(--ink)]">{ar ? 'المناطق' : 'Zones'}</h2>
            <p className="text-sm text-[var(--muted)]">
              {ar ? 'مناطق التحكم في الدخول لهذه الفعالية.' : 'Access control zones for this event.'}
            </p>
          </div>
          {zones.length === 0 ? (
            <EmptyState
              title={ar ? 'لا توجد مناطق بعد' : 'No zones yet'}
              detail={ar ? 'أنشئ منطقة باستخدام النموذج أدناه.' : 'Create a zone using the form below.'}
            />
          ) : (
            <DataTable
              rows={zones as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'name',
                  header: ar ? 'الاسم' : 'Name',
                  render: (row) => <span className="font-medium text-[var(--ink)]">{String(row.name)}</span>,
                },
                {
                  key: 'external_acs_zone_id',
                  header: ar ? 'المعرف الخارجي' : 'External ID',
                  render: (row) => <span className="font-mono text-sm">{String(row.external_acs_zone_id)}</span>,
                },
                {
                  key: 'status',
                  header: ar ? 'الحالة' : 'Status',
                  render: (row) => <StatusBadge status={String(row.status)} />,
                },
              ]}
            />
          )}
        </section>
      )}

      {showLanes && (
        <section className="space-y-3">
          <div>
            <h2 className="text-lg font-semibold text-[var(--ink)]">{ar ? 'المسارات' : 'Lanes'}</h2>
            <p className="text-sm text-[var(--muted)]">
              {ar ? 'البوابات والمسارات المرتبطة بالمناطق.' : 'Gates and lanes mapped to zones.'}
            </p>
          </div>
          {lanes.length === 0 ? (
            <EmptyState
              title={ar ? 'لا توجد مسارات بعد' : 'No lanes yet'}
              detail={ar ? 'أنشئ مساراً بعد إضافة منطقة.' : 'Create a lane after adding a zone.'}
            />
          ) : (
            <DataTable
              rows={lanes as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'name',
                  header: ar ? 'الاسم' : 'Name',
                  render: (row) => <span className="font-medium text-[var(--ink)]">{String(row.name)}</span>,
                },
                {
                  key: 'zone_id',
                  header: ar ? 'المنطقة' : 'Zone',
                  render: (row) => zoneNameById[String(row.zone_id)] ?? String(row.zone_id),
                },
                {
                  key: 'external_acs_lane_id',
                  header: ar ? 'المعرف الخارجي' : 'External ID',
                  render: (row) => <span className="font-mono text-sm">{String(row.external_acs_lane_id)}</span>,
                },
                {
                  key: 'status',
                  header: ar ? 'الحالة' : 'Status',
                  render: (row) => <StatusBadge status={String(row.status)} />,
                },
                {
                  key: 'health_status',
                  header: ar ? 'الصحة' : 'Health',
                  render: (row) => (row.health_status ? <StatusBadge status={String(row.health_status)} /> : '—'),
                },
              ]}
            />
          )}
        </section>
      )}
    </div>
  )
}
