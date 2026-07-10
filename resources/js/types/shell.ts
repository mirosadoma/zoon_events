export type PermissionKey = string

export type SessionUser = {
  id: string
  name: string
  email: string
  role_label: string
  phone?: string | null
  last_login_at?: string | null
}

export type SessionTenant = {
  id: string
  name: string
  slug: string
  branding?: Record<string, unknown> | null
  default_locale: string
  default_timezone: string
}

export type SessionContext = {
  user: SessionUser
  tenant: SessionTenant | null
  locale: 'en' | 'ar'
  theme: 'light' | 'dark' | 'system'
  role_label: string
}

export type PermissionMap = Record<PermissionKey, boolean>

export type NavigationItem = {
  key: string
  label: string
  href: string
  icon?: string
  permission: PermissionKey | null
  children?: NavigationItem[]
}

export type NavigationManifest = NavigationItem[]

export type NavigationGroup = {
  key: string
  label: string
  items: NavigationItem[]
}

export type BreadcrumbItem = {
  label: string
  href?: string
}
