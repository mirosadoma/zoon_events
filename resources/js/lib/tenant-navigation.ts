import type { NavigationItem } from '@/types/shell'

export function tenantEventNavigation(eventId: string): NavigationItem[] {
  const base = `/tenant/events/${eventId}`

  return [
    { key: 'event-detail', label: 'eventDetail', href: base, permission: 'event.view' },
    { key: 'registration-form', label: 'registrationForm', href: `${base}/registration-form`, permission: 'registration.manage' },
    { key: 'ticket-types', label: 'ticketTypes', href: `${base}/ticket-types`, permission: 'ticketing.manage' },
    { key: 'price-tiers', label: 'priceTiers', href: `${base}/price-tiers`, permission: 'ticketing.manage' },
    { key: 'orders', label: 'orders', href: `${base}/orders`, permission: 'order.view' },
    { key: 'attendees', label: 'attendees', href: `${base}/attendees`, permission: 'attendee.view' },
    { key: 'credentials', label: 'credentials', href: `${base}/credentials`, permission: 'credential.view' },
    { key: 'wallet-passes', label: 'walletPasses', href: `${base}/wallet-passes`, permission: 'wallet.pass.view' },
    { key: 'scanner', label: 'scanner', href: `${base}/scanner`, permission: 'checkin.scan.submit' },
    { key: 'check-in-dashboard', label: 'checkInDashboard', href: `${base}/check-in-dashboard`, permission: 'checkin.dashboard.view' },
    { key: 'scan-events', label: 'scanEvents', href: `${base}/scan-events`, permission: 'checkin.dashboard.view' },
    { key: 'kiosks', label: 'kiosks', href: `${base}/kiosks`, permission: 'kiosk.manage' },
    { key: 'badge-templates', label: 'badgeTemplates', href: `${base}/badge-templates`, permission: 'badge.template.manage' },
    { key: 'badge-print-jobs', label: 'badgePrintJobs', href: `${base}/badge-print-jobs`, permission: 'badge.print' },
    { key: 'manual-desk', label: 'manualDesk', href: `${base}/manual-desk`, permission: 'checkin.desk.perform' },
    { key: 'acs', label: 'acs', href: `${base}/acs`, permission: 'acs.events.view' },
    { key: 'acs-zones', label: 'acsZones', href: `${base}/acs/zones`, permission: 'acs.configure' },
    { key: 'acs-lanes', label: 'acsLanes', href: `${base}/acs/lanes`, permission: 'acs.configure' },
    { key: 'acs-rules', label: 'acsRules', href: `${base}/acs/rules`, permission: 'acs.configure' },
    { key: 'acs-access-logs', label: 'acsAccessLogs', href: `${base}/acs/access-logs`, permission: 'acs.events.view' },
    { key: 'acs-gate-health', label: 'acsGateHealth', href: `${base}/acs/gate-health`, permission: 'acs.health.view' },
    { key: 'reports', label: 'reports', href: `${base}/reports`, permission: 'event.view' },
  ]
}

export const tenantRootNavigation: NavigationItem[] = [
  { key: 'events', label: 'events', href: '/tenant/events', permission: 'event.view' },
]
