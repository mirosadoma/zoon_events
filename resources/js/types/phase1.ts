export type Phase1Locale = 'en' | 'ar'

export type EventTier = 'public' | 'private' | 'both'

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
