import type { PropsWithChildren } from 'react'

export default function PageContent({ children }: PropsWithChildren) {
  return <div className="space-y-6">{children}</div>
}
