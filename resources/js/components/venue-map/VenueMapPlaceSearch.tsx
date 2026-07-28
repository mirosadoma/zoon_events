import { useCallback, useEffect, useRef, useState, type KeyboardEvent } from 'react'
import { useJsApiLoader } from '@react-google-maps/api'
import type { Libraries } from '@react-google-maps/api'
import { useLocale } from '@/hooks/useLocale'

type PlacePrediction = {
  placeId: string
  description: string
  mainText: string
  secondaryText: string
}

type Props = {
  onPlaceResolved: (place: { latitude: number; longitude: number }) => void
}

const MAP_LIBRARIES: Libraries = ['places']
const SEARCH_DEBOUNCE_MS = 550
const SEARCH_MIN_CHARS = 1

export default function VenueMapPlaceSearch({ onPlaceResolved }: Props) {
  const { locale, t } = useLocale()
  const rootRef = useRef<HTMLDivElement>(null)
  const attributionRef = useRef<HTMLDivElement>(null)
  const skipSearchRef = useRef(false)
  const latestQueryRef = useRef('')
  const lastSearchedQueryRef = useRef('')
  const onPlaceResolvedRef = useRef(onPlaceResolved)
  const [query, setQuery] = useState('')
  const [predictions, setPredictions] = useState<PlacePrediction[]>([])
  const [open, setOpen] = useState(false)
  const [loading, setLoading] = useState(false)
  const [activeIndex, setActiveIndex] = useState(-1)
  const [searchError, setSearchError] = useState<string | null>(null)

  const apiKey = ((import.meta.env.VITE_GOOGLE_MAPS_API_KEY as string | undefined) ?? '')
    .trim()
    .replace(/^["']|["']$/g, '')

  const { isLoaded } = useJsApiLoader({
    id: 'zoon-google-maps',
    googleMapsApiKey: apiKey,
    libraries: MAP_LIBRARIES,
    language: locale,
  })

  useEffect(() => {
    onPlaceResolvedRef.current = onPlaceResolved
  }, [onPlaceResolved])

  useEffect(() => {
    function handlePointerDown(event: MouseEvent) {
      if (!rootRef.current?.contains(event.target as Node)) {
        setOpen(false)
        setActiveIndex(-1)
      }
    }

    document.addEventListener('mousedown', handlePointerDown)
    return () => document.removeEventListener('mousedown', handlePointerDown)
  }, [])

  useEffect(() => {
    const trimmed = query.trim()
    latestQueryRef.current = trimmed

    if (skipSearchRef.current) {
      skipSearchRef.current = false
      lastSearchedQueryRef.current = trimmed
      return
    }

    if (trimmed.length < SEARCH_MIN_CHARS || !isLoaded) {
      lastSearchedQueryRef.current = ''
      return
    }

    if (trimmed === lastSearchedQueryRef.current) {
      return
    }

    if (!window.google?.maps?.places?.AutocompleteService) {
      return
    }

    let cancelled = false
    const timer = window.setTimeout(() => {
      if (cancelled || latestQueryRef.current !== trimmed) {
        return
      }

      if (trimmed === lastSearchedQueryRef.current) {
        return
      }

      setLoading(true)
      setSearchError(null)

      const service = new google.maps.places.AutocompleteService()
      service.getPlacePredictions({ input: trimmed }, (results, status) => {
        if (cancelled || latestQueryRef.current !== trimmed) {
          return
        }

        setLoading(false)
        lastSearchedQueryRef.current = trimmed

        if (
          status !== google.maps.places.PlacesServiceStatus.OK
          && status !== google.maps.places.PlacesServiceStatus.ZERO_RESULTS
        ) {
          setPredictions([])
          setOpen(false)
          setSearchError(t('mapPickerSearchFailed'))
          return
        }

        const next = (results ?? []).map((prediction) => ({
          placeId: prediction.place_id,
          description: prediction.description,
          mainText: prediction.structured_formatting?.main_text || prediction.description,
          secondaryText: prediction.structured_formatting?.secondary_text || '',
        }))

        setPredictions(next)
        setOpen(next.length > 0)
        setActiveIndex(next.length > 0 ? 0 : -1)
        setSearchError(null)
      })
    }, SEARCH_DEBOUNCE_MS)

    return () => {
      cancelled = true
      window.clearTimeout(timer)
    }
  }, [query, isLoaded, t])

  const listOpen = open && predictions.length > 0 && query.trim().length >= SEARCH_MIN_CHARS

  const resolvePlace = useCallback((prediction: PlacePrediction) => {
    if (!window.google?.maps?.places?.PlacesService) {
      setSearchError(t('mapPickerPlacesUnavailable'))
      return
    }

    const attributionNode = attributionRef.current
    if (!attributionNode) {
      return
    }

    setLoading(true)
    setSearchError(null)

    const service = new google.maps.places.PlacesService(attributionNode)
    service.getDetails(
      {
        placeId: prediction.placeId,
        fields: ['geometry', 'name'],
      },
      (place, status) => {
        setLoading(false)

        if (status !== google.maps.places.PlacesServiceStatus.OK || !place?.geometry?.location) {
          setSearchError(t('mapPickerSearchFailed'))
          return
        }

        const location = place.geometry.location
        const label = (place.name || prediction.mainText || prediction.description).trim()

        skipSearchRef.current = true
        lastSearchedQueryRef.current = label
        setQuery(label)
        setPredictions([])
        setOpen(false)
        setActiveIndex(-1)

        onPlaceResolvedRef.current({
          latitude: location.lat(),
          longitude: location.lng(),
        })
      },
    )
  }, [t])

  function clearSearchState() {
    setPredictions([])
    setOpen(false)
    setLoading(false)
    setSearchError(null)
    setActiveIndex(-1)
    lastSearchedQueryRef.current = ''
  }

  function handleKeyDown(event: KeyboardEvent<HTMLInputElement>) {
    if (!listOpen || predictions.length === 0) {
      return
    }

    if (event.key === 'ArrowDown') {
      event.preventDefault()
      setActiveIndex((current) => (current + 1) % predictions.length)
      return
    }

    if (event.key === 'ArrowUp') {
      event.preventDefault()
      setActiveIndex((current) => (current <= 0 ? predictions.length - 1 : current - 1))
      return
    }

    if (event.key === 'Enter' && activeIndex >= 0) {
      event.preventDefault()
      resolvePlace(predictions[activeIndex])
      return
    }

    if (event.key === 'Escape') {
      setOpen(false)
      setActiveIndex(-1)
    }
  }

  if (apiKey === '') {
    return null
  }

  return (
    <div ref={rootRef} className="venue-map-place-search">
      <input
        type="text"
        role="combobox"
        aria-expanded={listOpen}
        aria-controls="venue-map-place-list"
        aria-autocomplete="list"
        aria-activedescendant={activeIndex >= 0 ? `venue-map-place-${activeIndex}` : undefined}
        value={query}
        onChange={(event) => {
          const nextValue = event.target.value
          skipSearchRef.current = false
          setQuery(nextValue)

          if (nextValue.trim().length < SEARCH_MIN_CHARS) {
            clearSearchState()
          }
        }}
        onFocus={() => {
          if (predictions.length > 0 && query.trim().length >= SEARCH_MIN_CHARS) {
            setOpen(true)
          }
        }}
        onKeyDown={handleKeyDown}
        placeholder={t('venueMapSearchPlaceholder')}
        aria-label={t('venueMapSearchPlaceholder')}
        className="venue-map-place-search__input"
        disabled={!isLoaded}
      />
      {loading ? (
        <span className="venue-map-place-search__spinner" aria-hidden>
          <span className="venue-map-place-search__spinner-dot" />
        </span>
      ) : null}
      <div ref={attributionRef} className="sr-only" aria-hidden />

      {listOpen ? (
        <ul
          id="venue-map-place-list"
          role="listbox"
          className="venue-map-place-search__results"
        >
          {predictions.map((prediction, index) => {
            const active = index === activeIndex
            return (
              <li key={prediction.placeId} role="presentation">
                <button
                  id={`venue-map-place-${index}`}
                  type="button"
                  role="option"
                  aria-selected={active}
                  className={`venue-map-place-search__option${active ? ' is-active' : ''}`}
                  onMouseEnter={() => setActiveIndex(index)}
                  onMouseDown={(event) => event.preventDefault()}
                  onClick={() => resolvePlace(prediction)}
                >
                  <span className="venue-map-place-search__option-title">{prediction.mainText}</span>
                  {prediction.secondaryText ? (
                    <span className="venue-map-place-search__option-sub">{prediction.secondaryText}</span>
                  ) : null}
                </button>
              </li>
            )
          })}
        </ul>
      ) : null}

      {searchError ? (
        <p className="venue-map-place-search__error" role="status">{searchError}</p>
      ) : null}
    </div>
  )
}
