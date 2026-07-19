import { CopyIcon } from 'lucide-react'
import { useLocale } from '@/hooks/useLocale'

type Props = {
  onClick: () => void
  className?: string
  compact?: boolean
}

export default function CopyRegistrationLinkButton({
  onClick,
  className = 'ta-table-action',
  compact = false,
}: Props) {
  const { locale, t } = useLocale()

  return (
    <button
      type="button"
      className={className}
      onClick={onClick}
      title={t('copyRegistrationLinkTitle')}
    >
      <CopyIcon className={compact ? 'h-4 w-4' : 'mx-2 h-4 w-4'} />
      {compact ? null : t('copyRegistrationLinkButton')}
    </button>
  )
}
