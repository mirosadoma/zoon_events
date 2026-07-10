import type { PropsWithChildren } from 'react'

export default function FormActions({ children }: PropsWithChildren) {
  return <div className="flex flex-wrap gap-3 pt-2">{children}</div>
}
