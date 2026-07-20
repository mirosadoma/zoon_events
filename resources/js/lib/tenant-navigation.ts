import type { NavigationGroup, NavigationItem } from '@/types/shell'
import type { EventCapabilities } from '@/lib/eventOptions'

export type { NavigationGroup }

export function eventNavigationGroups(eventId: string, capabilities?: EventCapabilities): NavigationGroup[] {
  const base = `/tenant/events/${eventId}`
  const showTicketing = capabilities?.requires_ticketing ?? true
  const showPriceTiers = capabilities?.requires_price_tiers ?? showTicketing

  const setupItems: NavigationItem[] = [
    { key: 'event-detail', label: 'eventDetail', href: base, permission: 'event.view' },
    { key: 'agenda', label: 'agenda', href: `${base}/agenda`, permission: 'event.manage' },
    { key: 'categories', label: 'eventCategories', href: `${base}/categories`, permission: 'category.view' },
    { key: 'registration-form', label: 'registrationForm', href: `${base}/registration-form`, permission: 'registration.manage' },
  ]

  if (showTicketing) {
    setupItems.push(
      { key: 'ticket-types', label: 'ticketTypes', href: `${base}/ticket-types`, permission: 'ticketing.manage' },
    )
  }

  if (showPriceTiers) {
    setupItems.push(
      { key: 'price-tiers', label: 'priceTiers', href: `${base}/price-tiers`, permission: 'ticketing.manage' },
    )
  }

  setupItems.push(
    { key: 'badge-templates', label: 'badgeTemplates', href: `${base}/badge-templates`, permission: 'badge.template.manage' },
    { key: 'kiosks', label: 'kiosks', href: `${base}/kiosks`, permission: 'kiosk.manage' },
  )

  return [
    {
      key: 'event-setup',
      label: 'navGroupEventSetup',
      items: setupItems,
    },
    {
      key: 'orders-attendees',
      label: 'navGroupOrdersAttendees',
      items: [
        { key: 'orders', label: 'orders', href: `${base}/orders`, permission: 'order.view' },
        { key: 'attendees', label: 'attendees', href: `${base}/attendees`, permission: 'attendee.view' },
        { key: 'credentials', label: 'credentials', href: `${base}/credentials`, permission: 'credential.view' },
      ],
    },
    {
      key: 'identity',
      label: 'navGroupIdentity',
      items: [
        { key: 'identity-requirements', label: 'identityRequirements', href: `${base}/identity`, permission: 'identity.configure' },
        { key: 'identity-review', label: 'identityReviewQueue', href: `${base}/identity/review`, permission: 'identity.review' },
      ],
    },
    {
      key: 'checkin',
      label: 'navGroupCheckIn',
      items: [
        { key: 'scanner', label: 'scanner', href: `${base}/scanner`, permission: 'checkin.scan.submit' },
        { key: 'check-in-dashboard', label: 'checkInDashboard', href: `${base}/check-in-dashboard`, permission: 'checkin.dashboard.view' },
        { key: 'scan-events', label: 'scanEvents', href: `${base}/scan-events`, permission: 'checkin.dashboard.view' },
        { key: 'wallet-passes', label: 'walletPasses', href: `${base}/wallet-passes`, permission: 'wallet.pass.view' },
      ],
    },
    {
      key: 'onsite',
      label: 'navGroupOnSite',
      items: [
        { key: 'badge-print-jobs', label: 'badgePrintJobs', href: `${base}/badge-print-jobs`, permission: 'badge.print' },
        { key: 'manual-desk', label: 'manualDesk', href: `${base}/manual-desk`, permission: 'checkin.desk.perform' },
        { key: 'walk-up', label: 'walkUpRegistration', href: `${base}/manual-desk/walk-up`, permission: 'attendee.walkup.register' },
      ],
    },
    {
      key: 'acs',
      label: 'navGroupAcs',
      items: [
        { key: 'acs', label: 'acs', href: `${base}/acs`, permission: 'acs.events.view' },
        { key: 'acs-zones', label: 'acsZones', href: `${base}/acs/zones`, permission: 'acs.configure' },
        { key: 'acs-lanes', label: 'acsLanes', href: `${base}/acs/lanes`, permission: 'acs.configure' },
        { key: 'acs-rules', label: 'acsRules', href: `${base}/acs/rules`, permission: 'acs.configure' },
        { key: 'acs-access-logs', label: 'acsAccessLogs', href: `${base}/acs/access-logs`, permission: 'acs.events.view' },
        { key: 'acs-gate-health', label: 'acsGateHealth', href: `${base}/acs/gate-health`, permission: 'acs.health.view' },
      ],
    },
    {
      key: 'reports',
      label: 'navGroupReports',
      items: [
        { key: 'reports', label: 'reports', href: `${base}/reports`, permission: 'event.view' },
      ],
    },
  ]
}

/** @deprecated Use eventNavigationGroups */
export function tenantEventNavigation(eventId: string): NavigationItem[] {
  return eventNavigationGroups(eventId).flatMap((group) => group.items)
}

export const tenantRootNavigation: NavigationItem[] = [
  { key: 'events', label: 'events', href: '/tenant/events', permission: 'event.view' },
]

export function extractEventIdFromPath(pathname: string): string | null {
  const match = pathname.match(/\/tenant\/events\/([^/]+)/)
  if (!match || match[1] === 'create') {
    return null
  }

  return match[1]
}
