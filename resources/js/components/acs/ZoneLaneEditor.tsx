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
  const { locale, t } = useLocale()
  const ar = locale === 'ar'
  const zoneNameById = Object.fromEntries(zones.map((zone) => [zone.id, zone.name]))

  return (
    <div className="space-y-6">
      {showZones && (
        <section className="space-y-3">
          <div>
            <h2 className="text-lg font-semibold text-[var(--ink)]">{t('acsZonesTitle')}</h2>
            <p className="text-sm text-[var(--muted)]">
              {t('acsZonesDescription')}
            </p>
          </div>
          {zones.length === 0 ? (
            <EmptyState
              title={t('acsZonesEmpty')}
              detail={t('acsZonesEmptyDetail')}
            />
          ) : (
            <DataTable
              rows={zones as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'name',
                  header: t('acsZoneName'),
                  render: (row) => <span className="font-medium text-[var(--ink)]">{String(row.name)}</span>,
                },
                {
                  key: 'external_acs_zone_id',
                  header: t('acsZoneExternalId'),
                  render: (row) => <span className="font-mono text-sm">{String(row.external_acs_zone_id)}</span>,
                },
                {
                  key: 'status',
                  header: t('status'),
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
            <h2 className="text-lg font-semibold text-[var(--ink)]">{t('acsLanesTitle')}</h2>
            <p className="text-sm text-[var(--muted)]">
              {t('acsLanesDescription')}
            </p>
          </div>
          {lanes.length === 0 ? (
            <EmptyState
              title={t('acsLanesEmpty')}
              detail={t('acsLanesEmptyDetail')}
            />
          ) : (
            <DataTable
              rows={lanes as unknown as Record<string, unknown>[]}
              getRowKey={(row) => String(row.id)}
              columns={[
                {
                  key: 'name',
                  header: t('acsLaneName'),
                  render: (row) => <span className="font-medium text-[var(--ink)]">{String(row.name)}</span>,
                },
                {
                  key: 'zone_id',
                  header: t('acsLaneZone'),
                  render: (row) => zoneNameById[String(row.zone_id)] ?? String(row.zone_id),
                },
                {
                  key: 'external_acs_lane_id',
                  header: t('acsLaneExternalId'),
                  render: (row) => <span className="font-mono text-sm">{String(row.external_acs_lane_id)}</span>,
                },
                {
                  key: 'status',
                  header: t('status'),
                  render: (row) => <StatusBadge status={String(row.status)} />,
                },
                {
                  key: 'health_status',
                  header: t('acsLaneHealth'),
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
