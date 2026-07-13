import { clsx } from 'clsx'
import { Check } from 'lucide-react'

export type FormStep = {
  key: string
  label: string
}

type Props = {
  steps: FormStep[]
  currentStep: number
  className?: string
  variant?: 'horizontal' | 'vertical'
  locale?: 'en' | 'ar'
}

export default function FormStepper({
  steps,
  currentStep,
  className = '',
  variant = 'horizontal',
  locale = 'en',
}: Props) {
  const statusCopy = {
    done: locale === 'ar' ? 'مكتملة' : 'Done',
    current: locale === 'ar' ? 'قيد التنفيذ' : 'In progress',
    pending: locale === 'ar' ? 'قادمة' : 'Pending',
  }
  return (
    <nav
      aria-label="Progress"
      className={clsx(
        'event-setup-stepper',
        variant === 'vertical' && 'event-setup-stepper-vertical',
        className,
      )}
    >
      <ol className={clsx('event-setup-stepper-list', variant === 'vertical' && 'event-setup-stepper-list-vertical')}>
        {steps.map((step, index) => {
          const isComplete = index < currentStep
          const isCurrent = index === currentStep

          return (
            <li
              key={step.key}
              className={clsx(
                'event-setup-stepper-item',
                isComplete && 'event-setup-stepper-item-complete',
                isCurrent && 'event-setup-stepper-item-current',
              )}
            >
              <span className="event-setup-stepper-marker" aria-hidden="true">
                {isComplete ? <Check className="h-3.5 w-3.5" /> : index + 1}
              </span>
              <span className="event-setup-stepper-copy">
                <span className="event-setup-stepper-label">{step.label}</span>
                {variant === 'vertical' ? (
                  <span className="event-setup-stepper-subtle">
                    {isComplete ? statusCopy.done : isCurrent ? statusCopy.current : statusCopy.pending}
                  </span>
                ) : null}
              </span>
            </li>
          )
        })}
      </ol>
    </nav>
  )
}
