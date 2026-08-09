/** Companion answer key for a choice option that has a linked free-text input. */
export function linkedTextAnswerKey(fieldKey: string, optionValue: string): string {
  return `${fieldKey}__${optionValue}__linked_text`
}

export function isLinkedTextAnswerKey(key: string): boolean {
  return key.endsWith('__linked_text')
}
