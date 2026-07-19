import { clsx } from 'clsx'
import { Check } from 'lucide-react'
import { useLocale } from '@/hooks/useLocale'

export type FormStep = {
  key: string
  label: string
}

type Props = {
  steps: FormStep[]
  currentStep: number
  className?: string
  variant?: 'horizontal' | 'vertical'
}

export default function FormStepper({
  steps,
  currentStep,
  className = '',
  variant = 'horizontal',
}: Props) {
  const { t } = useLocale()

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
                    {isComplete ? t('formStepperDone') : isCurrent ? t('formStepperInProgress') : t('formStepperPending')}
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
