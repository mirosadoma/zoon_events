import type { AppLocale } from '@/lib/localePath'

export type TourStep = {
  id: string
  target: string
  title: Record<AppLocale, string>
  body: Record<AppLocale, string>
  permission?: string
}

export type TourProfile = 'tenant_admin' | 'event_manager' | 'onsite' | 'ticketing' | 'acs' | 'platform_admin' | 'default'

export function resolveTourProfile(roleLabel: string | null | undefined, permissions: string[]): TourProfile {
  const label = (roleLabel ?? '').toLowerCase()

  if (permissions.some((key) => key.startsWith('platform.'))) {
    return 'platform_admin'
  }
  if (label.includes('on-site') || label.includes('ميداني')) {
    return 'onsite'
  }
  if (label.includes('ticketing') || label.includes('تذاكر')) {
    return 'ticketing'
  }
  if (label.includes('acs') || label.includes('access')) {
    return 'acs'
  }
  if (label.includes('event manager') || label.includes('فعاليات')) {
    return 'event_manager'
  }
  if (label.includes('administrator') || label.includes('مدير')) {
    return 'tenant_admin'
  }

  return 'default'
}

const baseSteps: TourStep[] = [
  {
    id: 'sidebar',
    target: '[data-tour="sidebar"]',
    title: { en: 'Navigation', ar: 'التنقل' },
    body: {
      en: 'Use the sidebar to move between overview, events, operations, and administration.',
      ar: 'استخدم القائمة الجانبية للتنقل بين النظرة العامة والفعاليات والتشغيل والإدارة.',
    },
  },
  {
    id: 'search',
    target: '[data-tour="search"]',
    title: { en: 'Quick search', ar: 'بحث سريع' },
    body: {
      en: 'Find events and users quickly from the top search bar.',
      ar: 'ابحث عن الفعاليات والمستخدمين بسرعة من شريط البحث العلوي.',
    },
  },
]

const profileSteps: Record<TourProfile, TourStep[]> = {
  tenant_admin: [
    {
      id: 'events',
      target: '[data-tour="nav-events"]',
      title: { en: 'Events', ar: 'الفعاليات' },
      body: {
        en: 'Create, publish, and manage your event lifecycle from here.',
        ar: 'أنشئ الفعاليات وانشرها وادِر دورة حياتها من هنا.',
      },
      permission: 'event.view',
    },
    {
      id: 'roles',
      target: '[data-tour="nav-roles"]',
      title: { en: 'Roles', ar: 'الأدوار' },
      body: {
        en: 'Define custom roles and assign permissions for your team.',
        ar: 'عرّف أدواراً مخصصة وحدد الصلاحيات لفريقك.',
      },
      permission: 'role.view',
    },
  ],
  event_manager: [
    {
      id: 'events',
      target: '[data-tour="nav-events"]',
      title: { en: 'Event setup', ar: 'إعداد الفعالية' },
      body: {
        en: 'Configure registration, ticketing, and publish when you are ready.',
        ar: 'اضبط التسجيل والتذاكر ثم انشر عندما تكون جاهزاً.',
      },
      permission: 'event.view',
    },
  ],
  onsite: [
    {
      id: 'scanner',
      target: '[data-tour="nav-scanner"]',
      title: { en: 'Scanner', ar: 'الماسح' },
      body: {
        en: 'Scan attendee credentials at the gate with large accept/reject feedback.',
        ar: 'امسح بيانات دخول الحضور عند البوابة مع نتيجة واضحة للقبول أو الرفض.',
      },
      permission: 'checkin.scan.submit',
    },
  ],
  ticketing: [
    {
      id: 'ticketing',
      target: '[data-tour="nav-ticketing"]',
      title: { en: 'Ticketing', ar: 'التذاكر' },
      body: {
        en: 'Manage ticket types, pricing tiers, and orders.',
        ar: 'أدر أنواع التذاكر وشرائح الأسعار والطلبات.',
      },
      permission: 'ticketing.manage',
    },
  ],
  acs: [
    {
      id: 'acs',
      target: '[data-tour="nav-acs"]',
      title: { en: 'Access control', ar: 'التحكم في الوصول' },
      body: {
        en: 'Configure zones, lanes, and monitor gate health.',
        ar: 'اضبط المناطق والممرات وراقب صحة البوابات.',
      },
      permission: 'acs.configure',
    },
  ],
  platform_admin: [
    {
      id: 'site-settings',
      target: '[data-tour="nav-site-settings"]',
      title: { en: 'Site settings', ar: 'إعدادات الموقع' },
      body: {
        en: 'Update branding, contact details, and maintenance mode.',
        ar: 'حدّث الهوية البصرية وبيانات التواصل ووضع الصيانة.',
      },
      permission: 'platform.configuration.view',
    },
  ],
  default: [],
}

export function buildTourSteps(profile: TourProfile, permissions: string[]): TourStep[] {
  const allowed = new Set(permissions)
  const extras = profileSteps[profile].filter((step) => !step.permission || allowed.has(step.permission))

  return [...baseSteps, ...extras]
}

export function tourStorageKey(userId: string | number, profile: TourProfile): string {
  return `zonetec.tour.completed.${userId}.${profile}`
}
