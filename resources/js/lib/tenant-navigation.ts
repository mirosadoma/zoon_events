export const tenantEventNavigation = [
  { label: 'events', href: '/tenant/events', permission: 'event.view' },
  { label: 'orders', href: '/tenant/events/{event_id}/orders', permission: 'order.view' },
  { label: 'attendees', href: '/tenant/events/{event_id}/attendees', permission: 'attendee.view' },
  { label: 'credentials', href: '/tenant/events/{event_id}/credentials', permission: 'credential.view' },
  { label: 'checkIn', href: '/tenant/events/{event_id}/check-in', permission: 'checkin.dashboard.view' },
  { label: 'walletPasses', href: '/tenant/events/{event_id}/wallet-passes', permission: 'wallet.pass.view' },
] as const
