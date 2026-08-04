/** Stable palette shared by dashboard + event report maps. */
export const MAP_MARKER_COLORS = [
  '#0ea5e9', '#8b5cf6', '#10b981', '#f59e0b', '#ef4444',
  '#06b6d4', '#ec4899', '#84cc16', '#f97316', '#6366f1',
] as const

export function colorForKey(key: string, colors: readonly string[] = MAP_MARKER_COLORS): string {
  let hash = 0
  for (let i = 0; i < key.length; i += 1) {
    hash = ((hash << 5) - hash) + key.charCodeAt(i)
    hash |= 0
  }
  return colors[Math.abs(hash) % colors.length] ?? colors[0]
}

export function coloredPinIcon(color: string): google.maps.Icon | undefined {
  if (typeof google === 'undefined' || !google.maps) {
    return undefined
  }

  const svg = `
    <svg xmlns="http://www.w3.org/2000/svg" width="36" height="48" viewBox="0 0 36 48">
      <path fill="${color}" stroke="#ffffff" stroke-width="2"
        d="M18 1C9.7 1 3 7.7 3 16c0 11.3 15 30.5 15 30.5S33 27.3 33 16C33 7.7 26.3 1 18 1z"/>
      <circle cx="18" cy="16" r="5.5" fill="#ffffff"/>
    </svg>
  `.trim()

  return {
    url: `data:image/svg+xml;charset=UTF-8,${encodeURIComponent(svg)}`,
    scaledSize: new google.maps.Size(36, 48),
    anchor: new google.maps.Point(18, 46),
  }
}
