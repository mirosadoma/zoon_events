import { presetLayout } from '@/components/event-site/LayoutPresetPicker'
import { asElementArray } from '@/lib/sectionElementFactory'

type SectionBlock = {
  content_en: Record<string, unknown>
  content_ar: Record<string, unknown>
  options: Record<string, unknown>
}

export function applyLayoutPresetToSection(block: SectionBlock, layoutPreset: string): SectionBlock {
  const { spans, starts } = presetLayout(layoutPreset)

  const redistribute = (content: Record<string, unknown>) => {
    const elements = asElementArray(content.elements)
    if (elements.length === 0) return content
    return {
      ...content,
      elements: elements.map((el, i) => {
        const next = {
          ...el,
          col_span: spans[i % spans.length] ?? 12,
          col_start: starts[i % starts.length] ?? undefined,
        }
        delete (next as { x_pct?: number }).x_pct
        delete (next as { y_pct?: number }).y_pct
        delete (next as { width_pct?: number }).width_pct
        delete (next as { height_pct?: number }).height_pct
        delete (next as { z_index?: number }).z_index
        return next
      }),
    }
  }

  return {
    ...block,
    options: { ...block.options, layout_preset: layoutPreset, layout_mode: 'grid' },
    content_en: redistribute(block.content_en),
    content_ar: redistribute(block.content_ar),
  }
}
