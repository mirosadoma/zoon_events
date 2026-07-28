/**
 * Make near-white / light-gray pixels transparent so the base map shows through.
 * Softens edges by scaling alpha near the threshold instead of a hard cut.
 */
export async function removeNearWhiteBackground(
  source: HTMLImageElement,
  options?: { threshold?: number; softness?: number },
): Promise<HTMLImageElement> {
  const threshold = options?.threshold ?? 238
  const softness = options?.softness ?? 18
  const canvas = document.createElement('canvas')
  canvas.width = source.naturalWidth || source.width
  canvas.height = source.naturalHeight || source.height
  const context = canvas.getContext('2d')
  if (!context || canvas.width < 1 || canvas.height < 1) {
    return source
  }

  context.drawImage(source, 0, 0)
  let imageData: ImageData
  try {
    imageData = context.getImageData(0, 0, canvas.width, canvas.height)
  } catch {
    // Cross-origin without CORS — return original.
    return source
  }

  const data = imageData.data
  for (let i = 0; i < data.length; i += 4) {
    const r = data[i]
    const g = data[i + 1]
    const b = data[i + 2]
    const minChannel = Math.min(r, g, b)
    const maxChannel = Math.max(r, g, b)
    const isNearGray = maxChannel - minChannel <= 28
    if (!isNearGray) continue

    const luminance = 0.299 * r + 0.587 * g + 0.114 * b
    if (luminance < threshold - softness) continue

    const t = Math.min(1, Math.max(0, (luminance - (threshold - softness)) / softness))
    data[i + 3] = Math.round(data[i + 3] * (1 - t))
  }

  context.putImageData(imageData, 0, 0)

  const dataUrl = canvas.toDataURL('image/png')
  return await new Promise((resolve) => {
    const result = new window.Image()
    result.onload = () => resolve(result)
    result.onerror = () => resolve(source)
    result.src = dataUrl
  })
}
