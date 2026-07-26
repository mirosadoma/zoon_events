import type { LucideIcon } from 'lucide-react'
import {
  Activity,
  BadgeCheck,
  BarChart3,
  CalendarDays,
  ClipboardList,
  DoorOpen,
  IdCard,
  Mail,
  Map,
  Monitor,
  Printer,
  Radio,
  ScanLine,
  Shield,
  ShieldCheck,
  ShoppingCart,
  Tags,
  Ticket,
  UserCheck,
  Users,
  Wallet,
} from 'lucide-react'

export type EventSectionMeta = {
  icon: LucideIcon
}

const DEFAULT_META: EventSectionMeta = {
  icon: CalendarDays,
}

export const EVENT_SECTION_META: Record<string, EventSectionMeta> = {
  agenda: { icon: CalendarDays },
  registration_form: { icon: ClipboardList },
  ticket_types: { icon: Ticket },
  price_tiers: { icon: Tags },
  identity: { icon: ShieldCheck },
  identity_review: { icon: ShieldCheck },
  orders: { icon: ShoppingCart },
  attendees: { icon: Users },
  credentials: { icon: IdCard },
  wallet_passes: { icon: Wallet },
  check_in_dashboard: { icon: BarChart3 },
  scanner: { icon: ScanLine },
  scan_events: { icon: Radio },
  kiosks: { icon: Monitor },
  badge_templates: { icon: BadgeCheck },
  email_templates: { icon: Mail },
  badge_print_jobs: { icon: Printer },
  manual_desk: { icon: UserCheck },
  walk_up: { icon: Users },
  acs: { icon: DoorOpen },
  acs_zones: { icon: Map },
  acs_lanes: { icon: Map },
  acs_rules: { icon: Shield },
  acs_access_logs: { icon: ClipboardList },
  acs_gate_health: { icon: Activity },
  reports: { icon: BarChart3 },
}

export function sectionMetaFor(key?: string): EventSectionMeta {
  if (!key) {
    return DEFAULT_META
  }

  return EVENT_SECTION_META[key] ?? DEFAULT_META
}
