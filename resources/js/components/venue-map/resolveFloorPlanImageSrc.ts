import { removeNearWhiteBackground } from '@/components/venue-map/removeImageBackground'

/**
 * Resolve the floor-plan image source, optionally stripping near-white background.
 * Falls back to the original URL if CORS prevents canvas pixel access.
 */
export async function resolveFloorPlanImageSrc(
  url: string,
  removeBackground: boolean,
): Promise<string> {
  if (!removeBackground || url === '') {
    return url
  }

  return await new Promise((resolve) => {
    const element = new window.Image()
    element.crossOrigin = 'anonymous'
    element.onload = () => {
      void (async () => {
        try {
          const processed = await removeNearWhiteBackground(element)
          resolve(processed.src || url)
        } catch {
          resolve(url)
        }
      })()
    }
    element.onerror = () => {
      // Local/dev storage often blocks CORS — show original image.
      resolve(url)
    }
    element.src = url
  })
}
