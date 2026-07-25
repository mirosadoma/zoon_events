/**
 * Build a URL slug from a title (Arabic or English).
 * Spaces become hyphens; letters and digits are kept.
 */
export function eventSlugFromTitle(title: string): string {
  const slug = title
    .trim()
    .replace(/\s+/gu, '-')
    .replace(/[^\p{L}\p{N}-]+/gu, '')
    .replace(/-+/gu, '-')
    .replace(/^-+|-+$/gu, '')
    .toLowerCase()

  if (slug === '') {
    return 'event'
  }

  return slug.slice(0, 100)
}

export function eventSlugFromNames(nameEn: string, nameAr: string): string {
  const en = nameEn.trim()
  const ar = nameAr.trim()

  return eventSlugFromTitle(en !== '' ? en : ar)
}
