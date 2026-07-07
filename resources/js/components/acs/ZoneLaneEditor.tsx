import type { AcsLane, AcsZone } from '../../types/phase4'

interface ZoneLaneEditorProps {
  zones: AcsZone[]
  lanes: AcsLane[]
}

export function ZoneLaneEditor({ zones, lanes }: ZoneLaneEditorProps) {
  return (
    <section>
      <h2>Zones</h2>
      <ul>
        {zones.map(zone => (
          <li key={zone.id}>
            {zone.name} ({zone.external_acs_zone_id}) — {zone.status}
          </li>
        ))}
      </ul>
      <h2>Lanes</h2>
      <ul>
        {lanes.map(lane => (
          <li key={lane.id}>
            {lane.name} ({lane.external_acs_lane_id}) — zone {lane.zone_id}
          </li>
        ))}
      </ul>
    </section>
  )
}
