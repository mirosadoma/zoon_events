import type { CSSProperties } from 'react'

export type SiteBackground = {
  type?: 'none' | 'solid' | 'gradient' | 'image'
  color?: string
  color_end?: string
  image?: string
  overlay?: number
}

export function backgroundStyle(bg?: SiteBackground | null): CSSProperties {
  if (!bg || bg.type === 'none') {
    return {}
  }

  const style: CSSProperties = {}

  switch (bg.type) {
    case 'solid':
      if (bg.color) {
        style.backgroundColor = bg.color
      }
      break

    case 'gradient':
      if (bg.color) {
        const endColor = bg.color_end || bg.color
        style.background = `linear-gradient(135deg, ${bg.color} 0%, ${endColor} 100%)`
      }
      break

    case 'image':
      if (bg.image) {
        style.backgroundImage = `url(${bg.image})`
        style.backgroundSize = 'cover'
        style.backgroundPosition = 'center'
        style.backgroundRepeat = 'no-repeat'
      }
      break
  }

  return style
}

export function backgroundOverlayStyle(bg?: SiteBackground | null): CSSProperties | null {
  if (!bg || bg.type !== 'image' || typeof bg.overlay !== 'number' || bg.overlay <= 0) {
    return null
  }

  return {
    position: 'absolute',
    inset: 0,
    backgroundColor: `rgba(0, 0, 0, ${bg.overlay / 100})`,
    pointerEvents: 'none',
  }
}
