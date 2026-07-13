import type { LucideIcon } from 'lucide-react'
import {
  BadgeCheck,
  BarChart3,
  CalendarDays,
  ClipboardList,
  DoorOpen,
  IdCard,
  Monitor,
  Printer,
  Radio,
  ScanLine,
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
  orders: { icon: ShoppingCart },
  attendees: { icon: Users },
  credentials: { icon: IdCard },
  wallet_passes: { icon: Wallet },
  check_in_dashboard: { icon: BarChart3 },
  scanner: { icon: ScanLine },
  scan_events: { icon: Radio },
  kiosks: { icon: Monitor },
  badge_templates: { icon: BadgeCheck },
  badge_print_jobs: { icon: Printer },
  manual_desk: { icon: UserCheck },
  acs: { icon: DoorOpen },
  reports: { icon: BarChart3 },
}

export function sectionMetaFor(key?: string): EventSectionMeta {
  if (!key) {
    return DEFAULT_META
  }

  return EVENT_SECTION_META[key] ?? DEFAULT_META
}
