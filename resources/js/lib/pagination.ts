export type PaginationMeta = {
  page: number
  per_page: number
  total: number
  last_page: number
}

export const defaultPagination: PaginationMeta = {
  page: 1,
  per_page: 25,
  total: 0,
  last_page: 1,
}

export function withPage(query: Record<string, string>, page: number): Record<string, string> {
  if (page > 1) {
    query.page = String(page)
  }

  return query
}
