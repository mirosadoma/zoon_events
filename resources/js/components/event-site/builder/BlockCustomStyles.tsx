import { blockScopeClass } from '@/lib/siteBlockStyle'

type Props = {
  blockId: string
  customClass?: string
  customCss?: string
  children: React.ReactNode
  className?: string
}

export default function BlockCustomStyles({ blockId, customClass, customCss, children, className }: Props) {
  const scope = blockScopeClass(blockId)
  const extraClass = customClass?.trim() || ''
  const css = customCss?.trim() || ''

  return (
    <div className={[scope, extraClass, className].filter(Boolean).join(' ')}>
      {css && (
        <style>
          {`.${scope} { ${css} }`}
        </style>
      )}
      {children}
    </div>
  )
}
