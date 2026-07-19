import type { PropsWithChildren } from 'react'

export default function PageContent({ children }: PropsWithChildren) {
  return <div className="ta-page-content">{children}</div>
}
