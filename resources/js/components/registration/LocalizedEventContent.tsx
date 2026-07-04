export type LocalizedText = { en: string | null; ar: string | null }

export function LocalizedEventContent({
  value,
  locale,
}: {
  value: LocalizedText
  locale: 'en' | 'ar'
}) {
  return <>{value[locale] ?? value.en ?? value.ar ?? ''}</>
}
