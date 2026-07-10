export type Phase1Locale = 'en' | 'ar'

export type EventTier = 'corporate' | 'public' | 'vip' | 'vvip'

export type EventStatus =
  | 'draft'
  | 'configured'
  | 'published'
  | 'registration_open'
  | 'registration_closed'
  | 'live'
  | 'completed'
  | 'cancelled'
  | 'archived'

export interface LocalizedText {
  en: string
  ar: string
}
