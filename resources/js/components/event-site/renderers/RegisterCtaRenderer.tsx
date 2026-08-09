type Props = {
  content: Record<string, unknown>
  options: Record<string, unknown>
  refs: Record<string, unknown>
  locale: 'en' | 'ar'
}

export default function RegisterCtaRenderer({ content, options }: Props) {
  const title = typeof content.title === 'string' ? content.title : ''
  const buttonText = typeof content.button_text === 'string' ? content.button_text : 'Register'
  const style = typeof options.style === 'string' ? options.style : 'prominent'

  if (style === 'inline') {
    return (
      <section className="py-8 px-6">
        <div className="max-w-4xl mx-auto flex items-center justify-center gap-4">
          {title && <span className="text-lg">{title}</span>}
          <button
            type="button"
            className="button-primary"
          >
            {buttonText}
          </button>
        </div>
      </section>
    )
  }

  return (
    <section className="py-16 px-6 bg-primary text-primary-foreground">
      <div className="max-w-4xl mx-auto text-center">
        {title && (
          <h2 className="text-3xl font-bold mb-6">{title}</h2>
        )}
        <button
          type="button"
          className="px-8 py-4 text-lg font-semibold bg-background text-foreground rounded-lg hover:bg-background/90 transition-colors"
        >
          {buttonText}
        </button>
      </div>
    </section>
  )
}
