const labels = {
  en: { available: 'Available', sold_out: 'Sold out', paused: 'Paused', conflict: 'Inventory changed' },
  ar: { available: 'متاح', sold_out: 'نفدت التذاكر', paused: 'متوقف مؤقتًا', conflict: 'تغير المخزون' },
} as const

export function InventoryStatus({
  state,
  locale,
}: {
  state: keyof typeof labels.en
  locale: 'en' | 'ar'
}) {
  return <span role="status" aria-live="polite">{labels[locale][state]}</span>
}
