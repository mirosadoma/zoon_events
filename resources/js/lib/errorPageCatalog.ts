export const ERROR_PAGE_STATUSES = [401, 403, 404, 405, 419, 429, 500, 503] as const

export type ErrorPageStatus = (typeof ERROR_PAGE_STATUSES)[number]

export type ErrorPageTone = 'auth' | 'forbidden' | 'notfound' | 'method' | 'session' | 'rate' | 'server' | 'unavailable'

export type ErrorPageActionIcon = 'home' | 'refresh' | 'back' | 'login'

export type ErrorPageAction = {
  labelKey: string
  href?: string
  onClick?: 'reload' | 'back'
  icon: ErrorPageActionIcon
}

export type ErrorPageConfig = {
  titleKey: string
  descriptionKey: string
  tone: ErrorPageTone
  primaryAction: ErrorPageAction
  secondaryAction?: ErrorPageAction
}

const ERROR_PAGE_CONFIG: Record<ErrorPageStatus, ErrorPageConfig> = {
  401: {
    titleKey: 'error401Title',
    descriptionKey: 'error401Description',
    tone: 'auth',
    primaryAction: { labelKey: 'errorPageSignIn', href: '/login', icon: 'login' },
    secondaryAction: { labelKey: 'errorPageGoHome', href: '/', icon: 'home' },
  },
  403: {
    titleKey: 'error403Title',
    descriptionKey: 'error403Description',
    tone: 'forbidden',
    primaryAction: { labelKey: 'errorPageGoHome', href: '/', icon: 'home' },
    secondaryAction: { labelKey: 'errorPageGoBack', onClick: 'back', icon: 'back' },
  },
  404: {
    titleKey: 'notFoundTitle',
    descriptionKey: 'notFoundDescription',
    tone: 'notfound',
    primaryAction: { labelKey: 'errorPageGoHome', href: '/', icon: 'home' },
    secondaryAction: { labelKey: 'errorPageGoBack', onClick: 'back', icon: 'back' },
  },
  405: {
    titleKey: 'error405Title',
    descriptionKey: 'error405Description',
    tone: 'method',
    primaryAction: { labelKey: 'errorPageGoHome', href: '/', icon: 'home' },
    secondaryAction: { labelKey: 'errorPageGoBack', onClick: 'back', icon: 'back' },
  },
  419: {
    titleKey: 'error419Title',
    descriptionKey: 'error419Description',
    tone: 'session',
    primaryAction: { labelKey: 'errorPageTryAgain', onClick: 'reload', icon: 'refresh' },
    secondaryAction: { labelKey: 'errorPageSignIn', href: '/login', icon: 'login' },
  },
  429: {
    titleKey: 'error429Title',
    descriptionKey: 'error429Description',
    tone: 'rate',
    primaryAction: { labelKey: 'errorPageTryAgain', onClick: 'reload', icon: 'refresh' },
    secondaryAction: { labelKey: 'errorPageGoHome', href: '/', icon: 'home' },
  },
  500: {
    titleKey: 'serverErrorTitle',
    descriptionKey: 'serverErrorDescription',
    tone: 'server',
    primaryAction: { labelKey: 'errorPageTryAgain', onClick: 'reload', icon: 'refresh' },
    secondaryAction: { labelKey: 'errorPageGoHome', href: '/', icon: 'home' },
  },
  503: {
    titleKey: 'error503Title',
    descriptionKey: 'error503Description',
    tone: 'unavailable',
    primaryAction: { labelKey: 'errorPageTryAgain', onClick: 'reload', icon: 'refresh' },
    secondaryAction: { labelKey: 'errorPageGoHome', href: '/', icon: 'home' },
  },
}

export function isErrorPageStatus(status: number): status is ErrorPageStatus {
  return (ERROR_PAGE_STATUSES as readonly number[]).includes(status)
}

export function resolveErrorPageConfig(status: number): ErrorPageConfig {
  if (isErrorPageStatus(status)) {
    return ERROR_PAGE_CONFIG[status]
  }

  return ERROR_PAGE_CONFIG[500]
}
