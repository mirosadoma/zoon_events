import HttpError from '@/pages/errors/HttpError'

type Props = {
  statusCode?: number
}

export default function ServerError({ statusCode = 500 }: Props) {
  return <HttpError statusCode={statusCode} />
}
