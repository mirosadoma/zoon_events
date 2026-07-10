import type { AcsRule } from '../../types/phase4'

interface RuleEditorProps {
  rules: AcsRule[]
}

export function RuleEditor({ rules }: RuleEditorProps) {
  return (
    <section>
      <h2>Authorization Rules</h2>
      <ul>
        {rules.map(rule => (
          <li key={rule.id}>
            Zone {rule.zone_id} — {rule.access_direction}
            {rule.ticket_type_id ? ` (ticket ${rule.ticket_type_id})` : ''}
            {rule.anti_passback_exempt ? ' [exempt]' : ''}
          </li>
        ))}
      </ul>
    </section>
  )
}
