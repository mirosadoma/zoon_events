/** Open a blank print window synchronously (call before awaiting fetch). */
export function openBlankPrintWindow(): Window | null {
  if (typeof window === 'undefined') {
    return null
  }

  // Do not use noopener here — it makes window.open() return null in many browsers,
  // and we need the Window handle to write the badge HTML after the API responds.
  return window.open('', '_blank', 'width=720,height=900')
}

/** Write badge HTML into an already-opened print window and trigger print. */
export function writeBadgePrintDocument(popup: Window | null, printHtml: string | null | undefined): boolean {
  if (!popup || !printHtml) {
    popup?.close()
    return false
  }

  popup.document.open()
  popup.document.write(printHtml)
  popup.document.close()
  return true
}

/** Open a browser print dialog for a badge HTML document. */
export function openBadgePrintWindow(printHtml: string | null | undefined): boolean {
  return writeBadgePrintDocument(openBlankPrintWindow(), printHtml)
}
