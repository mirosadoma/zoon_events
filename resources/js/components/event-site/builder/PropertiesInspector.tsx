import type { ReactNode } from 'react'
import { Layers, Globe2, Pencil } from 'lucide-react'
import BuilderPanelTabs from './BuilderPanelTabs'

export type PropertiesTabId = 'content' | 'layout' | 'style' | 'structure' | 'site'

export type PropertiesTab = {
  id: PropertiesTabId
  label: string
}

type Props = {
  locale: 'en' | 'ar'
  /** Label of the current selection (block / element type). */
  selectionLabel: string
  hasSelection: boolean
  /** True when Content/Layout/Style tabs are active for the selection. */
  editingSelection: boolean
  tabs: PropertiesTab[]
  activeTab: PropertiesTabId
  onTabChange: (tab: PropertiesTabId) => void
  /** Jump to layers / site without clearing selection. */
  onOpenStructure?: () => void
  onOpenSite?: () => void
  /** Return from Layers/Site back to the selected element's tabs. */
  onEditSelection?: () => void
  children: ReactNode
}

/**
 * Context-aware Properties panel chrome:
 * - No selection → Layers / Site tabs
 * - Selection → Content / Layout / Style (+ quick links to Layers / Site)
 */
export default function PropertiesInspector({
  locale,
  selectionLabel,
  hasSelection,
  editingSelection,
  tabs,
  activeTab,
  onTabChange,
  onOpenStructure,
  onOpenSite,
  onEditSelection,
  children,
}: Props) {
  const isAr = locale === 'ar'

  return (
    <div className="flex h-full min-h-0 flex-col overflow-hidden">
      <div className="shrink-0 border-b border-white/10 px-4 py-3">
        <div className="flex items-center justify-between gap-2">
          <div className="min-w-0">
            <h3 className="text-sm font-bold text-white/90">{isAr ? 'الخصائص' : 'Properties'}</h3>
            <p className="truncate text-[11px] text-white/45">{selectionLabel}</p>
          </div>
          <div className="flex shrink-0 items-center gap-1">
            {hasSelection && !editingSelection && onEditSelection && (
              <button
                type="button"
                title={isAr ? 'تعديل العنصر' : 'Edit element'}
                onClick={onEditSelection}
                className="rounded-md border border-violet-400/40 bg-violet-500/15 p-1.5 text-violet-200 transition hover:bg-violet-500/25"
              >
                <Pencil className="h-3.5 w-3.5" />
              </button>
            )}
            {hasSelection && editingSelection && onOpenStructure && (
              <button
                type="button"
                title={isAr ? 'الهيكل' : 'Layers'}
                onClick={onOpenStructure}
                className="rounded-md border border-white/10 bg-white/5 p-1.5 text-white/60 transition hover:border-violet-400/40 hover:bg-violet-500/15 hover:text-violet-200"
              >
                <Layers className="h-3.5 w-3.5" />
              </button>
            )}
            {hasSelection && editingSelection && onOpenSite && (
              <button
                type="button"
                title={isAr ? 'إعدادات الموقع' : 'Site settings'}
                onClick={onOpenSite}
                className="rounded-md border border-white/10 bg-white/5 p-1.5 text-white/60 transition hover:border-violet-400/40 hover:bg-violet-500/15 hover:text-violet-200"
              >
                <Globe2 className="h-3.5 w-3.5" />
              </button>
            )}
          </div>
        </div>
      </div>

      <BuilderPanelTabs
        tabs={tabs}
        activeTab={activeTab}
        onTabChange={(id) => onTabChange(id as PropertiesTabId)}
      >
        <div className="builder-inspector min-h-full">{children}</div>
      </BuilderPanelTabs>
    </div>
  )
}

/** Build tabs for the current inspector context. */
export function buildPropertiesTabs(args: {
  locale: 'en' | 'ar'
  hasSelection: boolean
  showLayout: boolean
}): PropertiesTab[] {
  const isAr = args.locale === 'ar'
  if (!args.hasSelection) {
    return [
      { id: 'structure', label: isAr ? 'الهيكل' : 'Layers' },
      { id: 'site', label: isAr ? 'الموقع' : 'Site' },
    ]
  }
  const tabs: PropertiesTab[] = [{ id: 'content', label: isAr ? 'محتوى' : 'Content' }]
  if (args.showLayout) {
    tabs.push({ id: 'layout', label: isAr ? 'تخطيط' : 'Layout' })
  }
  tabs.push({ id: 'style', label: isAr ? 'تنسيق' : 'Style' })
  return tabs
}
