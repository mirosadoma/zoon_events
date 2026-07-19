import type { EventCapabilities } from '@/lib/eventOptions'

export type EventSetupProgress = {
  registration_form: boolean
  ticket_types: boolean
  price_tiers: boolean
  agenda: boolean
  categories: boolean
  badge_templates: boolean
  kiosks: boolean
  identity: boolean
  published: boolean
}

export type EventSectionTab = {
  label: string
  href: string
  key?: string
  completed?: boolean
}

export function isNextStepComplete(
  stepKey: string,
  progress: EventSetupProgress,
): boolean {
  switch (stepKey) {
    case 'agenda':
      return progress.agenda
    case 'registration-form':
      return progress.registration_form
    case 'ticket-types':
      return progress.ticket_types
    case 'price-tiers':
      return progress.price_tiers
    case 'categories':
      return progress.categories
    case 'badge-templates':
      return progress.badge_templates
    case 'kiosks':
      return progress.kiosks
    case 'publish':
      return progress.published
    default:
      return false
  }
}

export function getApplicableSetupKeys(capabilities?: EventCapabilities): Array<keyof EventSetupProgress> {
  const keys: Array<keyof EventSetupProgress> = ['agenda', 'registration_form']

  if (capabilities?.requires_ticketing) {
    keys.push('ticket_types')
  }

  if (capabilities?.requires_price_tiers) {
    keys.push('price_tiers')
  }

  // Kiosks are optional and intentionally excluded from publish completion %.
  keys.push('categories', 'badge_templates')

  return keys
}

export function setupCompletionPercent(
  progress: EventSetupProgress,
  capabilities?: EventCapabilities,
): number {
  const keys = getApplicableSetupKeys(capabilities)

  if (keys.length === 0) {
    return 100
  }

  const completed = keys.filter((key) => progress[key]).length

  return Math.round((completed / keys.length) * 100)
}
