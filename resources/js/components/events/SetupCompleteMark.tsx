import { clsx } from 'clsx'
import { Check } from 'lucide-react'
import { useLocale } from '@/hooks/useLocale'

type Props = {
  completed?: boolean
  className?: string
}

export default function SetupCompleteMark({ completed = false, className = '' }: Props) {
  const { locale, t } = useLocale()

  if (!completed) {
    return null
  }

  const label = t('setupCompleteMark')

  return (
    <span
      className={clsx(
        'inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800 dark:bg-emerald-950/50 dark:text-emerald-200',
        className,
      )}
      title={label}
    >
      <Check className="h-3.5 w-3.5" aria-hidden="true" />
      {label}
    </span>
  )
}
