import { presetLayout } from '@/components/event-site/LayoutPresetPicker'

/** Layout presets for the drag-to-column picker (landing-builder style). */
export type LayoutDropPreset = {
  id: string
  /** Maps to section block `layout_preset` option */
  layoutPreset: string
  gridClass: string
  spans: string[]
  labelEn: string
  labelAr: string
}

export const LAYOUT_DROP_PRESETS: LayoutDropPreset[] = [
  {
    id: 'one',
    layoutPreset: '1',
    gridClass: 'grid-cols-1',
    spans: ['col-span-1'],
    labelEn: 'Full width',
    labelAr: 'عرض كامل',
  },
  {
    id: 'halves',
    layoutPreset: '2',
    gridClass: 'grid-cols-2',
    spans: ['col-span-1', 'col-span-1'],
    labelEn: '50 / 50',
    labelAr: '50 / 50',
  },
  {
    id: 'thirds',
    layoutPreset: '3',
    gridClass: 'grid-cols-3',
    spans: ['col-span-1', 'col-span-1', 'col-span-1'],
    labelEn: 'Thirds',
    labelAr: 'ثلاثة أعمدة',
  },
  {
    id: 'quarters',
    layoutPreset: '4',
    gridClass: 'grid-cols-4',
    spans: ['col-span-1', 'col-span-1', 'col-span-1', 'col-span-1'],
    labelEn: 'Quarters',
    labelAr: 'أربعة أعمدة',
  },
  {
    id: 'oneTwo',
    layoutPreset: '2-left',
    gridClass: 'grid-cols-3',
    spans: ['col-span-1', 'col-span-2'],
    labelEn: '33 / 66',
    labelAr: '33 / 66',
  },
  {
    id: 'twoOne',
    layoutPreset: '2-right',
    gridClass: 'grid-cols-3',
    spans: ['col-span-2', 'col-span-1'],
    labelEn: '66 / 33',
    labelAr: '66 / 33',
  },
  {
    id: 'oneThree',
    layoutPreset: '2-narrow-left',
    gridClass: 'grid-cols-12',
    spans: ['col-span-3', 'col-span-9'],
    labelEn: '25 / 75',
    labelAr: '25 / 75',
  },
  {
    id: 'threeOne',
    layoutPreset: '2-narrow-right',
    gridClass: 'grid-cols-12',
    spans: ['col-span-9', 'col-span-3'],
    labelEn: '75 / 25',
    labelAr: '75 / 25',
  },
  {
    id: 'oneTwoOne',
    layoutPreset: '3-center',
    gridClass: 'grid-cols-12',
    spans: ['col-span-3', 'col-span-6', 'col-span-3'],
    labelEn: 'Sidebar center',
    labelAr: 'وسط عريض',
  },
]

export function columnGridPlacement(layoutPreset: string, columnIndex: number): { col_span: number; col_start: number } {
  const { spans, starts } = presetLayout(layoutPreset)
  const span = spans[columnIndex] ?? spans[0] ?? 12
  const start = starts[columnIndex] ?? starts[0] ?? 1
  return { col_span: span, col_start: start }
}
