import type { PropsWithChildren, ReactNode } from 'react'

type FormSectionProps = PropsWithChildren<{
  title: string
  description?: string
  actions?: ReactNode
  style?: React.CSSProperties
}>

export default function FormSection({ title, description, actions, children, style = {} }: FormSectionProps) {
  return (
    <section className="state-panel space-y-4" style={style}>
      <div className="flex flex-wrap items-start justify-between gap-3">
        <div>
          <h2 className="text-lg font-semibold">{title}</h2>
          {description && <p className="mt-1 text-sm text-slate-600 dark:text-slate-300">{description}</p>}
        </div>
        {actions}
      </div>
      {children}
    </section>
  )
}
