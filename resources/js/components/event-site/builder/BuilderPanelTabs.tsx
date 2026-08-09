import { useState, type ReactNode } from 'react'

type Tab = { id: string; label: string; icon?: ReactNode }

type Props = {
  tabs: Tab[]
  activeTab: string
  onTabChange: (id: string) => void
  children: ReactNode
  /** vertical tab list instead of horizontal bar */
  verticalTabs?: boolean
}

export default function BuilderPanelTabs({ tabs, activeTab, onTabChange, children, verticalTabs = false }: Props) {
  if (verticalTabs) {
    return (
      <div className="flex h-full min-h-0 flex-col">
        <div className="builder-tabs-vertical shrink-0 max-h-[148px] overflow-y-auto overscroll-contain border-b border-white/10 p-2">
          <div className="flex flex-col gap-0.5">
            {tabs.map((tab) => (
              <button
                key={tab.id}
                type="button"
                onClick={() => onTabChange(tab.id)}
                className={`flex items-center gap-2 rounded-lg px-3 py-2 text-start text-[10px] font-semibold uppercase tracking-wide transition ${
                  activeTab === tab.id
                    ? 'bg-violet-600/25 text-violet-200 ring-1 ring-violet-400/35'
                    : 'text-white/50 hover:bg-white/5 hover:text-white/80'
                }`}
              >
                {tab.icon}
                <span className="truncate">{tab.label}</span>
              </button>
            ))}
          </div>
        </div>
        <div className="min-h-0 flex-1 overflow-y-auto overscroll-contain">{children}</div>
      </div>
    )
  }

  return (
    <div className="flex h-full min-h-0 flex-col">
      <div className="builder-tabs-scroll shrink-0 overflow-x-auto overflow-y-hidden border-b border-white/10">
        <div className="flex min-w-max">
          {tabs.map((tab) => (
            <button
              key={tab.id}
              type="button"
              onClick={() => onTabChange(tab.id)}
              className={`flex shrink-0 items-center justify-center gap-1.5 border-b-2 px-3 py-3 text-[10px] font-semibold uppercase tracking-wide transition whitespace-nowrap ${
                activeTab === tab.id
                  ? 'border-violet-400 text-violet-300'
                  : 'border-transparent text-white/45 hover:text-white/70'
              }`}
            >
              {tab.icon}
              <span>{tab.label}</span>
            </button>
          ))}
        </div>
      </div>
      <div className="builder-panel-scroll min-h-0 flex-1 overflow-y-auto overscroll-contain">{children}</div>
    </div>
  )
}

export function BuilderInspectorSection({ title, children }: { title: string; children: ReactNode }) {
  return (
    <div className="border-b border-white/10">
      <div className="bg-white/[0.03] px-4 py-2 text-[11px] font-semibold uppercase tracking-wider text-white/50">
        {title}
      </div>
      <div className="builder-inspector p-4">{children}</div>
    </div>
  )
}

export function useBuilderTab(defaultTab: string) {
  return useState(defaultTab)
}
