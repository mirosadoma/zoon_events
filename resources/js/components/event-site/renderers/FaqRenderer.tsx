import { useState } from 'react'
import { ChevronDown } from 'lucide-react'

type FaqItem = {
  question: string
  answer: string
}

type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
}

export default function FaqRenderer({ content, options }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const items: FaqItem[] = Array.isArray(content.items) ? content.items : []
  const collapsible = options.collapsible === true

  const [openIndex, setOpenIndex] = useState<number | null>(null)

  const toggleItem = (index: number) => {
    setOpenIndex(openIndex === index ? null : index)
  }

  return (
    <section className="py-16 px-6">
      <div className="max-w-3xl mx-auto">
        {title && (
          <h2 className="text-3xl font-bold mb-8 text-center">{title}</h2>
        )}

        <div className="space-y-4">
          {items.map((item, index) => {
            const isOpen = !collapsible || openIndex === index

            return (
              <div key={index} className="border rounded-lg overflow-hidden">
                <button
                  type="button"
                  onClick={() => collapsible && toggleItem(index)}
                  className={`
                    w-full flex items-center justify-between p-4 text-start font-medium
                    ${collapsible ? 'hover:bg-muted/50 cursor-pointer' : 'cursor-default'}
                  `}
                >
                  <span>{item.question}</span>
                  {collapsible && (
                    <ChevronDown
                      className={`w-5 h-5 transition-transform ${isOpen ? 'rotate-180' : ''}`}
                    />
                  )}
                </button>

                {isOpen && (
                  <div className="px-4 pb-4 text-muted-foreground">
                    {item.answer}
                  </div>
                )}
              </div>
            )
          })}
        </div>
      </div>
    </section>
  )
}
