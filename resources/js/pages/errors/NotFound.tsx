import HttpError from '@/pages/errors/HttpError'

type Props = {
  statusCode?: number
}

export default function NotFound({ statusCode = 404 }: Props) {
  return <HttpError statusCode={statusCode} />
}
