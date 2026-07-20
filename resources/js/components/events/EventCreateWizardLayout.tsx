import { clsx } from 'clsx'
import FormStepper, { type FormStep } from '@/components/forms/FormStepper'

type Props = {
  steps: FormStep[]
  currentStep: number
  stepTitle: string
  stepDescription?: string
  children: React.ReactNode
  footer: React.ReactNode
}

export default function EventCreateWizardLayout({
  steps,
  currentStep,
  stepTitle,
  stepDescription,
  children,
  footer,
}: Props) {
  return (
    <div className="event-create-wizard">
      <div className="event-create-wizard-shell">
        <aside className="event-create-wizard-aside">
          <FormStepper steps={steps} currentStep={currentStep} variant="vertical" />
        </aside>

        <div className="event-create-wizard-main">
          <header className="event-create-wizard-header">
            <h2 className="event-create-wizard-title">{stepTitle}</h2>
            {stepDescription ? (
              <p className="event-create-wizard-description">{stepDescription}</p>
            ) : null}
          </header>

          <div className="event-create-wizard-body">{children}</div>
        </div>
      </div>

      <footer className={clsx('event-create-wizard-footer')}>{footer}</footer>
    </div>
  )
}
