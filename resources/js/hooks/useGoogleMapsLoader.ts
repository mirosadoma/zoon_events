import { useJsApiLoader } from '@react-google-maps/api'
import type { Libraries } from '@react-google-maps/api'

const GOOGLE_MAPS_LOADER_ID = 'zoon-google-maps'

/** Superset of libraries used across map components — must stay stable for the singleton loader. */
const GOOGLE_MAPS_LIBRARIES: Libraries = ['places', 'geometry']

let frozenMapLanguage: 'en' | 'ar' | null = null

function resolveMapLanguage(): 'en' | 'ar' {
  if (frozenMapLanguage === null) {
    frozenMapLanguage = document.documentElement.lang === 'ar' ? 'ar' : 'en'
  }

  return frozenMapLanguage
}

export function useGoogleMapsLoader() {
  const apiKey = ((import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined) ?? '').trim()
  const loader = useJsApiLoader({
    id: GOOGLE_MAPS_LOADER_ID,
    googleMapsApiKey: apiKey,
    libraries: GOOGLE_MAPS_LIBRARIES,
    language: resolveMapLanguage(),
  })

  return { apiKey, ...loader }
}
