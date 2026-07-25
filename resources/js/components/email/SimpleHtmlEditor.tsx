import { useState, useRef, useCallback, useEffect, type MouseEvent as ReactMouseEvent } from 'react'
import { Bold, Italic, Link as LinkIcon, List, ListOrdered, Palette, ImagePlus, Columns2 } from 'lucide-react'
import { useLocale } from '@/hooks/useLocale'

export type PlaceholderStyle = {
  backgroundColor: string
  color: string
  fontSize: string
  fontFamily: string
  bold: boolean
  italic: boolean
}

type ImageFrame = {
  top: number
  left: number
  width: number
  height: number
}

type ResizeCorner = 'nw' | 'ne' | 'sw' | 'se'

type ResizeSession = {
  corner: ResizeCorner
  startX: number
  startY: number
  startWidth: number
  startHeight: number
}

type MoveSession = {
  img: HTMLImageElement
  table: HTMLTableElement | null
  startX: number
  startY: number
  active: boolean
}

type DropIndicator = {
  top: number
  left: number
  width: number
  height: number
  orientation: 'horizontal' | 'vertical'
}

type DropResolution =
  | { mode: 'beside'; targetImg: HTMLImageElement; side: 'before' | 'after' }
  | { mode: 'line'; reference: ChildNode | null; place: 'before' | 'append' }

const MIN_IMAGE_SIZE = 40
const IMAGE_MOVE_THRESHOLD = 6

const DEFAULT_PLACEHOLDER_STYLE: PlaceholderStyle = {
  backgroundColor: 'transparent',
  color: 'inherit',
  fontSize: '1em',
  fontFamily: 'inherit',
  bold: false,
  italic: false,
}

const FONT_OPTIONS = [
  { value: 'inherit', label: 'Inherit' },
  { value: 'Arial, Helvetica, sans-serif', label: 'Arial' },
  { value: 'Georgia, serif', label: 'Georgia' },
  { value: 'Times New Roman, Times, serif', label: 'Times New Roman' },
  { value: 'Tahoma, Geneva, sans-serif', label: 'Tahoma' },
  { value: 'Verdana, Geneva, sans-serif', label: 'Verdana' },
  { value: 'Courier New, Courier, monospace', label: 'Courier New' },
  { value: 'Cairo, Tahoma, sans-serif', label: 'Cairo' },
  { value: 'Tajawal, Tahoma, sans-serif', label: 'Tajawal' },
]

const FONT_SIZE_OPTIONS = [
  '0.75em',
  '0.875em',
  '1em',
  '1.125em',
  '1.25em',
  '1.5em',
  '1.75em',
  '2em',
  '12px',
  '14px',
  '16px',
  '18px',
  '20px',
  '24px',
]

type Props = {
  value: string
  onChange: (value: string) => void
  placeholder?: string
  availablePlaceholders?: string[]
  onInsertPlaceholder?: (placeholder: string) => void
  onUploadImage?: (file: File) => Promise<string>
  contentLocale?: 'en' | 'ar'
}

function buildPlaceholderInlineStyle(style: PlaceholderStyle): string {
  const bgTransparent = !style.backgroundColor || style.backgroundColor === 'transparent'
  const parts = [
    `font-size: ${style.fontSize}`,
    `font-family: ${style.fontFamily}`,
    `font-weight: ${style.bold ? '700' : '400'}`,
    `font-style: ${style.italic ? 'italic' : 'normal'}`,
    `color: ${style.color && style.color !== 'inherit' ? style.color : 'inherit'}`,
  ]

  if (bgTransparent) {
    parts.push('background-color: transparent')
    parts.push('background-image: none')
    parts.push('padding: 0')
  } else {
    parts.push(`background-color: ${style.backgroundColor}`)
    parts.push('padding: 0 0.15em')
    parts.push('border-radius: 2px')
  }

  return parts.join('; ')
}

function escapeHtml(value: string): string {
  return value
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
}

function tokenKey(token: string): string {
  return token.replace(/^\{\{|\}\}$/g, '')
}

function createEmailImageElement(url: string): HTMLImageElement {
  const img = document.createElement('img')
  img.className = 'email-template-image'
  img.src = url
  img.alt = ''
  img.draggable = false
  img.style.cssText = 'max-width:100%;height:auto;display:block;margin:0;border:0;'
  return img
}

function createImageCell(img: HTMLImageElement): HTMLTableCellElement {
  const td = document.createElement('td')
  td.setAttribute('align', 'left')
  td.setAttribute('valign', 'top')
  const widthAttr = img.getAttribute('width')
  const width = widthAttr && /^\d+$/.test(widthAttr) ? widthAttr : null
  td.style.cssText = width
    ? `padding:0 6px;vertical-align:top;width:${width}px;`
    : 'padding:0 6px;vertical-align:top;'
  if (width) {
    td.setAttribute('width', width)
  }
  td.appendChild(img)
  return td
}

function createImageBlockTable(img: HTMLImageElement): HTMLTableElement {
  const table = document.createElement('table')
  table.className = 'email-image-block'
  table.setAttribute('role', 'presentation')
  table.setAttribute('width', '100%')
  table.setAttribute('cellpadding', '0')
  table.setAttribute('cellspacing', '0')
  table.setAttribute('border', '0')
  table.style.cssText = 'width:100%;margin:12px 0;border-collapse:collapse;'

  const tr = document.createElement('tr')
  const td = document.createElement('td')
  td.setAttribute('align', 'left')
  td.setAttribute('valign', 'top')
  td.style.cssText = 'padding:0;vertical-align:top;'
  td.appendChild(img)
  tr.appendChild(td)
  table.appendChild(tr)
  return table
}

function createImageRowTable(images: HTMLImageElement[]): HTMLTableElement {
  const table = document.createElement('table')
  table.className = 'email-image-row'
  table.setAttribute('role', 'presentation')
  table.setAttribute('cellpadding', '0')
  table.setAttribute('cellspacing', '0')
  table.setAttribute('border', '0')
  table.style.cssText = 'width:auto;max-width:100%;margin:12px 0;border-collapse:collapse;'

  const tr = document.createElement('tr')
  images.forEach((img) => {
    tr.appendChild(createImageCell(img))
  })
  table.appendChild(tr)
  return table
}

function findImageLayoutTable(img: HTMLImageElement): HTMLTableElement | null {
  return img.closest('table.email-image-row, table.email-image-block') as HTMLTableElement | null
}

function getMovableImageUnit(img: HTMLImageElement): HTMLTableElement | null {
  const table = findImageLayoutTable(img)
  if (!table) {
    return null
  }

  if (table.classList.contains('email-image-row')) {
    const images = table.querySelectorAll('img')
    if (images.length <= 1) {
      return table
    }

    // Pull this image out of the row into its own block, then move that block.
    const cell = img.closest('td')
    const block = createImageBlockTable(img)
    cell?.remove()

    const remaining = table.querySelectorAll('img')
    if (remaining.length === 0) {
      table.replaceWith(block)
    } else if (remaining.length === 1) {
      const only = remaining[0] as HTMLImageElement
      const single = createImageBlockTable(only)
      table.replaceWith(single)
      single.after(block)
    } else {
      table.after(block)
    }

    return block
  }

  return table
}

function resolveDropTarget(
  root: HTMLElement,
  clientY: number,
  dragging: HTMLElement,
): { reference: ChildNode | null; place: 'before' | 'append' } {
  const blocks = Array.from(root.children).filter((child) => child !== dragging)

  for (const block of blocks) {
    if (!(block instanceof HTMLElement)) continue
    const rect = block.getBoundingClientRect()
    const midpoint = rect.top + rect.height / 2
    if (clientY < midpoint) {
      return { reference: block, place: 'before' }
    }
  }

  return { reference: null, place: 'append' }
}

function findBesideDropTarget(
  root: HTMLElement,
  clientX: number,
  clientY: number,
  draggingTable: HTMLElement,
): { targetImg: HTMLImageElement; side: 'before' | 'after' } | null {
  const candidates = Array.from(root.querySelectorAll<HTMLImageElement>('img.email-template-image, img'))
    .filter((img) => !draggingTable.contains(img))

  let best: { targetImg: HTMLImageElement; side: 'before' | 'after'; score: number } | null = null

  for (const img of candidates) {
    const table = findImageLayoutTable(img)
    const hitTarget = table ?? img
    const rect = hitTarget.getBoundingClientRect()
    const padded = {
      left: rect.left - 12,
      right: rect.right + 12,
      top: rect.top - 8,
      bottom: rect.bottom + 8,
    }

    if (
      clientX < padded.left
      || clientX > padded.right
      || clientY < padded.top
      || clientY > padded.bottom
    ) {
      continue
    }

    const imgRect = img.getBoundingClientRect()
    const side: 'before' | 'after' = clientX < imgRect.left + imgRect.width / 2 ? 'before' : 'after'
    const centerX = imgRect.left + imgRect.width / 2
    const centerY = imgRect.top + imgRect.height / 2
    const score = Math.hypot(clientX - centerX, clientY - centerY)

    if (!best || score < best.score) {
      best = { targetImg: img, side, score }
    }
  }

  return best ? { targetImg: best.targetImg, side: best.side } : null
}

function resolveImageDrop(
  root: HTMLElement,
  clientX: number,
  clientY: number,
  draggingTable: HTMLElement,
): DropResolution {
  const beside = findBesideDropTarget(root, clientX, clientY, draggingTable)
  if (beside) {
    return { mode: 'beside', targetImg: beside.targetImg, side: beside.side }
  }

  const line = resolveDropTarget(root, clientY, draggingTable)
  return { mode: 'line', reference: line.reference, place: line.place }
}

function mergeImageBeside(
  draggedImg: HTMLImageElement,
  targetImg: HTMLImageElement,
  side: 'before' | 'after',
): HTMLTableElement | null {
  if (draggedImg === targetImg) {
    return findImageLayoutTable(targetImg)
  }

  const draggedUnit = findImageLayoutTable(draggedImg)
  const targetTable = findImageLayoutTable(targetImg)

  if (targetTable?.classList.contains('email-image-row')) {
    const targetCell = targetImg.closest('td')
    if (!targetCell) {
      return null
    }
    const newCell = createImageCell(draggedImg)
    if (side === 'before') {
      targetCell.before(newCell)
    } else {
      targetCell.after(newCell)
    }
    if (draggedUnit && draggedUnit !== targetTable && !draggedUnit.querySelector('img')) {
      draggedUnit.remove()
    }
    return targetTable
  }

  const ordered = side === 'before' ? [draggedImg, targetImg] : [targetImg, draggedImg]
  const row = createImageRowTable(ordered)

  if (targetTable) {
    targetTable.replaceWith(row)
  } else {
    targetImg.replaceWith(row)
  }

  if (draggedUnit && draggedUnit.isConnected && !draggedUnit.querySelector('img')) {
    draggedUnit.remove()
  }

  return row
}

function clearBesideDropHighlights(root: HTMLElement): void {
  root.querySelectorAll('.is-drop-beside-target').forEach((el) => {
    el.classList.remove('is-drop-beside-target')
  })
}

function insertBlockAfterSelection(root: HTMLElement, node: HTMLElement): void {
  const selection = window.getSelection()
  if (!selection || selection.rangeCount === 0 || !root.contains(selection.anchorNode)) {
    root.appendChild(node)
    return
  }

  const range = selection.getRangeAt(0)
  let anchor: Node | null = range.startContainer
  if (anchor.nodeType === Node.TEXT_NODE) {
    anchor = anchor.parentNode
  }

  const block = (anchor as Element | null)?.closest?.('p,div,table,li,h1,h2,h3,h4,h5,h6')
  if (block && root.contains(block)) {
    if (block.tagName.toLowerCase() === 'table' && (
      block.classList.contains('email-image-block') || block.classList.contains('email-image-row')
    )) {
      block.after(node)
    } else {
      block.after(node)
    }
    return
  }

  range.collapse(false)
  range.insertNode(node)
}

function normalizeEditorImages(root: HTMLElement): void {
  const images = Array.from(root.querySelectorAll<HTMLImageElement>('img'))
  images.forEach((img) => {
    img.classList.add('email-template-image')
    img.draggable = false
    if (!img.style.display) {
      img.style.display = 'block'
    }

    if (findImageLayoutTable(img)) {
      return
    }

    const table = createImageBlockTable(img.cloneNode(true) as HTMLImageElement)
    const parent = img.parentElement
    if (parent?.tagName.toLowerCase() === 'p') {
      const children = Array.from(parent.childNodes)
      const meaningfulSiblings = children.some((node) => {
        if (node === img) return false
        if (node.nodeType === Node.ELEMENT_NODE && (node as Element).tagName.toLowerCase() === 'br') return false
        if (node.nodeType === Node.TEXT_NODE && !(node.textContent || '').trim()) return false
        return true
      })

      if (!meaningfulSiblings) {
        parent.replaceWith(table)
        return
      }

      const before = document.createElement('p')
      const after = document.createElement('p')
      let reachedImage = false
      children.forEach((node) => {
        if (node === img) {
          reachedImage = true
          return
        }
        if (!reachedImage) {
          before.appendChild(node)
        } else {
          after.appendChild(node)
        }
      })

      parent.replaceWith(before, table, ...(after.hasChildNodes() ? [after] : []))
      return
    }

    img.replaceWith(table)
  })

  root.querySelectorAll('.isSelectedEnd, .is-selected').forEach((el) => {
    el.classList.remove('isSelectedEnd', 'is-selected')
  })
}

function readStyleFromElement(el: HTMLElement): PlaceholderStyle {
  const cs = el.style
  const bg = (cs.backgroundColor || '').trim().toLowerCase()
  const transparent = bg === '' || bg === 'transparent' || bg === 'rgba(0, 0, 0, 0)'

  return {
    backgroundColor: transparent ? 'transparent' : (cs.backgroundColor || 'transparent'),
    color: cs.color || 'inherit',
    fontSize: cs.fontSize || '1em',
    fontFamily: cs.fontFamily || 'inherit',
    bold: cs.fontWeight === '700' || cs.fontWeight === 'bold',
    italic: cs.fontStyle === 'italic',
  }
}

export default function SimpleHtmlEditor({
  value,
  onChange,
  placeholder,
  availablePlaceholders = [],
  onInsertPlaceholder,
  onUploadImage,
  contentLocale = 'en',
}: Props) {
  const { t, locale: pageLocale } = useLocale()
  const editorRef = useRef<HTMLDivElement>(null)
  const editorShellRef = useRef<HTMLDivElement>(null)
  const imageInputRef = useRef<HTMLInputElement>(null)
  const placeholderMenuRef = useRef<HTMLDivElement>(null)
  const selectedImageRef = useRef<HTMLImageElement | null>(null)
  const resizeSessionRef = useRef<ResizeSession | null>(null)
  const moveSessionRef = useRef<MoveSession | null>(null)
  const imageInsertModeRef = useRef<'block' | 'beside'>('block')
  const [showPlaceholders, setShowPlaceholders] = useState(false)
  const [showStylePanel, setShowStylePanel] = useState(false)
  const [uploadingImage, setUploadingImage] = useState(false)
  const [imageFrame, setImageFrame] = useState<ImageFrame | null>(null)
  const [imageSize, setImageSize] = useState<{ width: number; height: number } | null>(null)
  const [dropIndicator, setDropIndicator] = useState<DropIndicator | null>(null)
  const [isMovingImage, setIsMovingImage] = useState(false)
  /** null = style target is all placeholders */
  const [styleTarget, setStyleTarget] = useState<string | null>(null)
  const [stylesByToken, setStylesByToken] = useState<Record<string, PlaceholderStyle>>({})
  const [draftStyle, setDraftStyle] = useState<PlaceholderStyle>(DEFAULT_PLACEHOLDER_STYLE)
  const contentDirection = contentLocale === 'ar' ? 'rtl' : 'ltr'
  const resolvedPlaceholder = placeholder
    ?? (pageLocale === 'ar' ? 'اكتب محتوى البريد…' : 'Enter email content…')

  useEffect(() => {
    if (editorRef.current) {
      editorRef.current.innerHTML = value
      normalizeEditorImages(editorRef.current)
      const normalized = editorRef.current.innerHTML
      if (normalized !== value) {
        onChange(normalized)
      }
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  useEffect(() => {
    if (!showPlaceholders) return

    const handlePointerDown = (event: MouseEvent) => {
      if (!placeholderMenuRef.current?.contains(event.target as Node)) {
        setShowPlaceholders(false)
      }
    }

    document.addEventListener('mousedown', handlePointerDown)
    return () => document.removeEventListener('mousedown', handlePointerDown)
  }, [showPlaceholders])

  const syncHtml = useCallback(() => {
    if (!editorRef.current) return

    const selected = selectedImageRef.current
    selected?.classList.remove('is-selected')
    editorRef.current.querySelectorAll('.isSelectedEnd').forEach((el) => {
      el.classList.remove('isSelectedEnd')
    })
      onChange(editorRef.current.innerHTML)
    if (selected && editorRef.current.contains(selected)) {
      selected.classList.add('is-selected')
    }
  }, [onChange])

  const clearImageSelection = useCallback(() => {
    selectedImageRef.current?.classList.remove('is-selected')
    selectedImageRef.current = null
    setImageFrame(null)
    setImageSize(null)
  }, [])

  const refreshImageFrame = useCallback(() => {
    const img = selectedImageRef.current
    const shell = editorShellRef.current
    const editor = editorRef.current
    if (!img || !shell || !editor || !editor.contains(img)) {
      clearImageSelection()
      return
    }

    const shellRect = shell.getBoundingClientRect()
    const imgRect = img.getBoundingClientRect()
    setImageFrame({
      top: imgRect.top - shellRect.top + shell.scrollTop,
      left: imgRect.left - shellRect.left + shell.scrollLeft,
      width: imgRect.width,
      height: imgRect.height,
    })
    setImageSize({
      width: Math.round(img.offsetWidth || imgRect.width),
      height: Math.round(img.offsetHeight || imgRect.height),
    })
  }, [clearImageSelection])

  const applyImageSize = useCallback((
    img: HTMLImageElement,
    width: number,
    height: number,
    options: { sync?: boolean } = {},
  ) => {
    const nextWidth = Math.max(MIN_IMAGE_SIZE, Math.round(width))
    const nextHeight = Math.max(MIN_IMAGE_SIZE, Math.round(height))

    img.setAttribute('width', String(nextWidth))
    img.setAttribute('height', String(nextHeight))
    img.setAttribute('draggable', 'false')
    img.style.width = `${nextWidth}px`
    img.style.height = `${nextHeight}px`
    img.style.maxWidth = `${nextWidth}px`
    img.style.display = 'block'
    img.style.margin = '0'
    img.style.borderStyle = 'none'
    img.classList.add('email-template-image')

    const cell = img.closest('td')
    if (cell && img.closest('table.email-image-row')) {
      cell.setAttribute('width', String(nextWidth))
      cell.style.width = `${nextWidth}px`
      cell.style.padding = '0 6px'
      cell.style.verticalAlign = 'top'
    }

    if (options.sync !== false) {
      syncHtml()
    }
    refreshImageFrame()
  }, [refreshImageFrame, syncHtml])

  const selectImage = useCallback((img: HTMLImageElement) => {
    selectedImageRef.current?.classList.remove('is-selected')
    selectedImageRef.current = img
    img.setAttribute('draggable', 'false')
    img.classList.add('email-template-image', 'is-selected')
    refreshImageFrame()
  }, [refreshImageFrame])

  useEffect(() => {
    if (!imageFrame) return

    const shell = editorShellRef.current
    const handleReposition = () => refreshImageFrame()
    window.addEventListener('resize', handleReposition)
    shell?.addEventListener('scroll', handleReposition)

    return () => {
      window.removeEventListener('resize', handleReposition)
      shell?.removeEventListener('scroll', handleReposition)
    }
  }, [imageFrame, refreshImageFrame])

  const beginImageResize = useCallback((corner: ResizeCorner, event: ReactMouseEvent) => {
    event.preventDefault()
    event.stopPropagation()
    moveSessionRef.current = null
    setDropIndicator(null)
    setIsMovingImage(false)
    const img = selectedImageRef.current
    if (!img) return

    resizeSessionRef.current = {
      corner,
      startX: event.clientX,
      startY: event.clientY,
      startWidth: img.offsetWidth || img.getBoundingClientRect().width,
      startHeight: img.offsetHeight || img.getBoundingClientRect().height,
    }
    document.body.style.cursor = `${corner}-resize`
    document.body.style.userSelect = 'none'
  }, [])

  const beginImageMove = useCallback((event: ReactMouseEvent) => {
    if (event.button !== 0) return
    event.preventDefault()
    event.stopPropagation()

    const img = selectedImageRef.current
    const root = editorRef.current
    if (!img || !root || !root.contains(img)) return

    moveSessionRef.current = {
      img,
      table: null,
      startX: event.clientX,
      startY: event.clientY,
      active: false,
    }
  }, [])

  useEffect(() => {
    const clearMoveUi = () => {
      const session = moveSessionRef.current
      session?.table?.classList.remove('is-dragging')
      const root = editorRef.current
      if (root) {
        clearBesideDropHighlights(root)
      }
      moveSessionRef.current = null
      setDropIndicator(null)
      setIsMovingImage(false)
      document.body.style.removeProperty('cursor')
      document.body.style.removeProperty('user-select')
    }

    const updateDropIndicator = (
      root: HTMLElement,
      shell: HTMLElement,
      drop: DropResolution,
    ) => {
      clearBesideDropHighlights(root)
      const shellRect = shell.getBoundingClientRect()

      if (drop.mode === 'beside') {
        drop.targetImg.classList.add('is-drop-beside-target')
        const rect = drop.targetImg.getBoundingClientRect()
        const lineLeft = drop.side === 'before'
          ? rect.left - shellRect.left + shell.scrollLeft - 2
          : rect.right - shellRect.left + shell.scrollLeft
        setDropIndicator({
          top: rect.top - shellRect.top + shell.scrollTop,
          left: lineLeft,
          width: 3,
          height: Math.max(24, rect.height),
          orientation: 'vertical',
        })
        return
      }

      if (drop.place === 'before' && drop.reference instanceof HTMLElement) {
        const rect = drop.reference.getBoundingClientRect()
        setDropIndicator({
          top: rect.top - shellRect.top + shell.scrollTop - 1,
          left: 16,
          width: Math.max(40, shell.clientWidth - 32),
          height: 3,
          orientation: 'horizontal',
        })
        return
      }

      const dragging = moveSessionRef.current?.table
      const last = Array.from(root.children).filter((child) => child !== dragging).at(-1)
      if (last instanceof HTMLElement) {
        const rect = last.getBoundingClientRect()
        setDropIndicator({
          top: rect.bottom - shellRect.top + shell.scrollTop + 2,
          left: 16,
          width: Math.max(40, shell.clientWidth - 32),
          height: 3,
          orientation: 'horizontal',
        })
      } else {
        setDropIndicator({
          top: 16,
          left: 16,
          width: Math.max(40, shell.clientWidth - 32),
          height: 3,
          orientation: 'horizontal',
        })
      }
    }

    const handlePointerMove = (event: PointerEvent) => {
      const resizeSession = resizeSessionRef.current
      if (resizeSession) {
        const img = selectedImageRef.current
        if (!img) return

        const dx = event.clientX - resizeSession.startX
        const dy = event.clientY - resizeSession.startY
        let nextWidth = resizeSession.startWidth
        let nextHeight = resizeSession.startHeight

        if (resizeSession.corner === 'se') {
          nextWidth = resizeSession.startWidth + dx
          nextHeight = resizeSession.startHeight + dy
        } else if (resizeSession.corner === 'sw') {
          nextWidth = resizeSession.startWidth - dx
          nextHeight = resizeSession.startHeight + dy
        } else if (resizeSession.corner === 'ne') {
          nextWidth = resizeSession.startWidth + dx
          nextHeight = resizeSession.startHeight - dy
        } else {
          nextWidth = resizeSession.startWidth - dx
          nextHeight = resizeSession.startHeight - dy
        }

        applyImageSize(img, nextWidth, nextHeight, { sync: false })
        return
      }

      const moveSession = moveSessionRef.current
      const root = editorRef.current
      const shell = editorShellRef.current
      if (!moveSession || !root || !shell) return

      const dx = event.clientX - moveSession.startX
      const dy = event.clientY - moveSession.startY
      if (!moveSession.active) {
        if (Math.hypot(dx, dy) < IMAGE_MOVE_THRESHOLD) {
          return
        }

        if (!root.contains(moveSession.img)) {
          clearMoveUi()
          return
        }

        const table = getMovableImageUnit(moveSession.img)
        if (!table) {
          clearMoveUi()
          return
        }
        if (!root.contains(table)) {
          root.appendChild(table)
        }

        moveSession.table = table
        moveSession.active = true
        table.classList.add('is-dragging')
        setIsMovingImage(true)
        setImageFrame(null)
        document.body.style.cursor = 'grabbing'
        document.body.style.userSelect = 'none'
        selectImage(moveSession.img)
        syncHtml()
      }

      if (!moveSession.table) return

      const drop = resolveImageDrop(root, event.clientX, event.clientY, moveSession.table)
      updateDropIndicator(root, shell, drop)
    }

    const handlePointerUp = (event: PointerEvent) => {
      if (resizeSessionRef.current) {
        resizeSessionRef.current = null
        document.body.style.removeProperty('cursor')
        document.body.style.removeProperty('user-select')
        syncHtml()
        refreshImageFrame()
        return
      }

      const moveSession = moveSessionRef.current
      const root = editorRef.current
      if (!moveSession || !root) {
        clearMoveUi()
        return
      }

      if (moveSession.active && moveSession.table) {
        const drop = resolveImageDrop(root, event.clientX, event.clientY, moveSession.table)

        if (drop.mode === 'beside') {
          moveSession.table.classList.remove('is-dragging')
          mergeImageBeside(moveSession.img, drop.targetImg, drop.side)
        } else if (drop.place === 'before' && drop.reference) {
          root.insertBefore(moveSession.table, drop.reference)
          moveSession.table.classList.remove('is-dragging')
        } else {
          root.appendChild(moveSession.table)
          moveSession.table.classList.remove('is-dragging')
        }

        syncHtml()

        if (root.contains(moveSession.img)) {
          selectImage(moveSession.img)
        } else {
          const fallback = root.querySelector('img.email-template-image, img')
          if (fallback instanceof HTMLImageElement) {
            selectImage(fallback)
          }
        }
      }

      clearMoveUi()
      requestAnimationFrame(() => refreshImageFrame())
    }

    document.addEventListener('pointermove', handlePointerMove)
    document.addEventListener('pointerup', handlePointerUp)
    return () => {
      document.removeEventListener('pointermove', handlePointerMove)
      document.removeEventListener('pointerup', handlePointerUp)
    }
  }, [applyImageSize, refreshImageFrame, selectImage, syncHtml])

  const execCommand = useCallback((command: string, commandValue?: string) => {
    document.execCommand(command, false, commandValue)
    syncHtml()
  }, [syncHtml])

  const handleInput = useCallback(() => {
    syncHtml()
    if (selectedImageRef.current) {
      refreshImageFrame()
    }
  }, [refreshImageFrame, syncHtml])

  const styleForToken = useCallback((token: string): PlaceholderStyle => {
    return stylesByToken[token] ?? DEFAULT_PLACEHOLDER_STYLE
  }, [stylesByToken])

  const insertPlaceholder = useCallback((token: string) => {
    const styleSource = (styleTarget === null || styleTarget === token)
      ? draftStyle
      : styleForToken(token)
    const style = buildPlaceholderInlineStyle(styleSource)
    const key = tokenKey(token)
    const span = `<span class="email-placeholder" data-placeholder="${escapeHtml(key)}" contenteditable="false" style="${style}">${escapeHtml(token)}</span>&nbsp;`
    execCommand('insertHTML', span)
    setShowPlaceholders(false)
    setStylesByToken((current) => ({ ...current, [token]: styleSource }))
    onInsertPlaceholder?.(token)
  }, [draftStyle, execCommand, onInsertPlaceholder, styleForToken, styleTarget])

  const insertImageHtml = useCallback((url: string, mode: 'block' | 'beside' = 'block') => {
    const root = editorRef.current
    if (!root) return

    root.focus()
    const img = createEmailImageElement(url)

    if (mode === 'beside' && selectedImageRef.current && root.contains(selectedImageRef.current)) {
      const selected = selectedImageRef.current
      const table = findImageLayoutTable(selected)

      if (table?.classList.contains('email-image-row')) {
        const row = table.querySelector('tr')
        row?.appendChild(createImageCell(img))
      } else if (table?.classList.contains('email-image-block')) {
        const existing = selected.cloneNode(true) as HTMLImageElement
        existing.classList.remove('is-selected')
        const rowTable = createImageRowTable([existing, img])
        table.replaceWith(rowTable)
      } else {
        const existing = selected.cloneNode(true) as HTMLImageElement
        existing.classList.remove('is-selected')
        const block = findImageLayoutTable(selected)
        const rowTable = createImageRowTable([existing, img])
        if (block) {
          block.replaceWith(rowTable)
        } else {
          selected.replaceWith(rowTable)
        }
      }
    } else {
      const table = createImageBlockTable(img)
      insertBlockAfterSelection(root, table)
    }

    selectImage(img)
    syncHtml()
    imageInsertModeRef.current = 'block'
  }, [selectImage, syncHtml])

  const handleImageFile = useCallback(async (file: File | null) => {
    if (!file || !onUploadImage) return
    setUploadingImage(true)
    try {
      const url = await onUploadImage(file)
      if (url) {
        insertImageHtml(url, imageInsertModeRef.current)
      }
    } finally {
      setUploadingImage(false)
      imageInsertModeRef.current = 'block'
      if (imageInputRef.current) {
        imageInputRef.current.value = ''
      }
    }
  }, [insertImageHtml, onUploadImage])

  const handleInsertImageClick = useCallback(() => {
    imageInsertModeRef.current = 'block'
    if (onUploadImage) {
      imageInputRef.current?.click()
      return
    }
    const url = prompt(t('emailEditorEnterImageUrl'))
    if (url?.trim()) {
      insertImageHtml(url.trim(), 'block')
    }
  }, [insertImageHtml, onUploadImage, t])

  const handleInsertImageBesideClick = useCallback(() => {
    if (!selectedImageRef.current) {
      return
    }
    imageInsertModeRef.current = 'beside'
    if (onUploadImage) {
      imageInputRef.current?.click()
      return
    }
    const url = prompt(t('emailEditorEnterImageUrl'))
    if (url?.trim()) {
      insertImageHtml(url.trim(), 'beside')
    }
  }, [insertImageHtml, onUploadImage, t])

  const wrapBareTokens = useCallback((root: HTMLElement, tokens: string[], styleFor: (token: string) => string) => {
    if (tokens.length === 0) return
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT)
    const textNodes: Text[] = []
    while (walker.nextNode()) {
      textNodes.push(walker.currentNode as Text)
    }

    const pattern = new RegExp(`(${tokens.map((tok) => tok.replace(/[{}]/g, '\\$&')).join('|')})`, 'g')
    textNodes.forEach((textNode) => {
      if (textNode.parentElement?.closest('span.email-placeholder')) return
      const text = textNode.nodeValue ?? ''
      if (!pattern.test(text)) return
      pattern.lastIndex = 0

      const frag = document.createDocumentFragment()
      let last = 0
      let match: RegExpExecArray | null
      while ((match = pattern.exec(text)) !== null) {
        if (match.index > last) {
          frag.appendChild(document.createTextNode(text.slice(last, match.index)))
        }
        const token = match[0]
        const span = document.createElement('span')
        span.className = 'email-placeholder'
        span.contentEditable = 'false'
        span.dataset.placeholder = tokenKey(token)
        span.setAttribute('style', styleFor(token))
        span.textContent = token
        frag.appendChild(span)
        last = match.index + token.length
      }
      if (last < text.length) {
        frag.appendChild(document.createTextNode(text.slice(last)))
      }
      textNode.parentNode?.replaceChild(frag, textNode)
    })
  }, [])

  const applyStyle = useCallback((target: string | null, style: PlaceholderStyle) => {
    const root = editorRef.current
    if (!root) return

    const inline = buildPlaceholderInlineStyle(style)
    const nodes = Array.from(root.querySelectorAll<HTMLElement>('span.email-placeholder'))

    nodes.forEach((node) => {
      const text = (node.textContent || '').trim()
      const key = node.dataset.placeholder || tokenKey(text)
      const matches = target === null || text === target || `{{${key}}}` === target || key === tokenKey(target)
      if (!matches) return
      node.setAttribute('style', inline)
      node.setAttribute('contenteditable', 'false')
      node.dataset.placeholder = tokenKey(target ?? text)
      if (target && text !== target && !text.includes('{{')) {
        // keep existing text (already replaced values in preview aren't in editor)
      }
    })

    const tokensToWrap = target ? [target] : availablePlaceholders
    wrapBareTokens(root, tokensToWrap, (token) => (
      target === null || token === target ? inline : buildPlaceholderInlineStyle(styleForToken(token))
    ))

    if (target) {
      setStylesByToken((current) => ({ ...current, [target]: style }))
    } else {
      const next: Record<string, PlaceholderStyle> = {}
      availablePlaceholders.forEach((token) => {
        next[token] = style
      })
      setStylesByToken(next)
    }

    syncHtml()
  }, [availablePlaceholders, styleForToken, syncHtml, wrapBareTokens])

  const selectStyleTarget = (token: string | null) => {
    setStyleTarget(token)
    if (token) {
      setDraftStyle(stylesByToken[token] ?? DEFAULT_PLACEHOLDER_STYLE)
    } else {
      setDraftStyle(DEFAULT_PLACEHOLDER_STYLE)
    }
  }

  const updateDraft = <K extends keyof PlaceholderStyle>(key: K, next: PlaceholderStyle[K]) => {
    setDraftStyle((current) => ({ ...current, [key]: next }))
  }

  const handleEditorClick = (event: ReactMouseEvent<HTMLDivElement>) => {
    const imgTarget = (event.target as HTMLElement).closest('img') as HTMLImageElement | null
    if (imgTarget && editorRef.current?.contains(imgTarget)) {
      selectImage(imgTarget)
      setShowPlaceholders(false)
      return
    }

    clearImageSelection()

    const target = (event.target as HTMLElement).closest('span.email-placeholder') as HTMLElement | null
    if (!target) return
    const text = (target.textContent || '').trim()
    const token = availablePlaceholders.find((item) => item === text)
      ?? (target.dataset.placeholder ? `{{${target.dataset.placeholder}}}` : null)
    if (!token || !availablePlaceholders.includes(token)) return

    setShowStylePanel(true)
    setShowPlaceholders(false)
    setStyleTarget(token)
    setDraftStyle(readStyleFromElement(target))
  }

  const bgIsTransparent = !draftStyle.backgroundColor || draftStyle.backgroundColor === 'transparent'

  return (
    <div className="overflow-hidden rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)]" data-email-editor>
      <div className="flex flex-wrap items-center gap-1 border-b border-[var(--border)] bg-[var(--surface)] p-2">
        <button
          type="button"
          onClick={() => execCommand('bold')}
          className="rounded p-1.5 text-[var(--muted)] transition hover:bg-[var(--surface-elevated)] hover:text-[var(--ink)]"
          title={t('emailEditorBold')}
        >
          <Bold size={16} />
        </button>
        <button
          type="button"
          onClick={() => execCommand('italic')}
          className="rounded p-1.5 text-[var(--muted)] transition hover:bg-[var(--surface-elevated)] hover:text-[var(--ink)]"
          title={t('emailEditorItalic')}
        >
          <Italic size={16} />
        </button>
        <div className="mx-1 h-5 w-px bg-[var(--border)]" />
        <button
          type="button"
          onClick={() => execCommand('insertUnorderedList')}
          className="rounded p-1.5 text-[var(--muted)] transition hover:bg-[var(--surface-elevated)] hover:text-[var(--ink)]"
          title={t('emailEditorBulletList')}
        >
          <List size={16} />
        </button>
        <button
          type="button"
          onClick={() => execCommand('insertOrderedList')}
          className="rounded p-1.5 text-[var(--muted)] transition hover:bg-[var(--surface-elevated)] hover:text-[var(--ink)]"
          title={t('emailEditorNumberedList')}
        >
          <ListOrdered size={16} />
        </button>
        <button
          type="button"
          onClick={() => {
            const url = prompt(t('emailEditorEnterUrl'))
            if (url) {
              execCommand('createLink', url)
            }
          }}
          className="rounded p-1.5 text-[var(--muted)] transition hover:bg-[var(--surface-elevated)] hover:text-[var(--ink)]"
          title={t('emailEditorInsertLink')}
        >
          <LinkIcon size={16} />
        </button>
        <button
          type="button"
          onClick={handleInsertImageClick}
          disabled={uploadingImage}
          className="rounded p-1.5 text-[var(--muted)] transition hover:bg-[var(--surface-elevated)] hover:text-[var(--ink)] disabled:opacity-50"
          title={t('emailEditorInsertImage')}
        >
          <ImagePlus size={16} />
        </button>
        <button
          type="button"
          onClick={handleInsertImageBesideClick}
          disabled={uploadingImage || !imageFrame}
          className="rounded p-1.5 text-[var(--muted)] transition hover:bg-[var(--surface-elevated)] hover:text-[var(--ink)] disabled:opacity-50"
          title={t('emailEditorInsertImageBeside')}
        >
          <Columns2 size={16} />
        </button>
        <input
          ref={imageInputRef}
          type="file"
          accept="image/*"
          className="hidden"
          onChange={(e) => void handleImageFile(e.target.files?.[0] ?? null)}
        />

        {availablePlaceholders.length > 0 && (
          <>
            <div className="mx-1 h-5 w-px bg-[var(--border)]" />
            <div className="relative" ref={placeholderMenuRef}>
              <button
                type="button"
                onClick={() => {
                  setShowPlaceholders((open) => !open)
                  setShowStylePanel(false)
                }}
                className="rounded bg-[var(--brand-soft)] px-2.5 py-1 text-xs font-medium text-[var(--brand)] transition hover:bg-[var(--brand)]/10"
              >
                {t('emailTemplateInsertPlaceholder')}
              </button>
              {showPlaceholders && (
                <div className="absolute start-0 top-full z-10 mt-1 min-w-[200px] rounded-lg border border-[var(--border)] bg-[var(--surface-elevated)] shadow-lg">
                  {availablePlaceholders.map((token) => (
                    <button
                      key={token}
                      type="button"
                      onClick={() => insertPlaceholder(token)}
                      className="block w-full px-3 py-2 text-start text-sm text-[var(--ink)] transition hover:bg-[var(--surface)]"
                    >
                      {token}
                    </button>
                  ))}
                </div>
              )}
            </div>
            <button
              type="button"
              onClick={() => {
                setShowStylePanel((open) => !open)
                setShowPlaceholders(false)
              }}
              className={`inline-flex items-center gap-1 rounded px-2.5 py-1 text-xs font-medium transition ${
                showStylePanel
                  ? 'bg-[var(--brand)] text-white'
                  : 'bg-[var(--surface-elevated)] text-[var(--muted)] hover:text-[var(--ink)]'
              }`}
              title={t('emailPlaceholderStyle')}
            >
              <Palette size={14} />
              {t('emailPlaceholderStyle')}
            </button>
          </>
        )}
      </div>

      {showStylePanel && availablePlaceholders.length > 0 && (
        <div className="space-y-3 border-b border-[var(--border)] bg-[var(--surface)] p-3">
          <div>
            <p className="mb-2 text-xs font-medium text-[var(--ink)]">{t('emailPlaceholderTarget')}</p>
            <div className="flex flex-wrap gap-1.5">
              <button
                type="button"
                onClick={() => selectStyleTarget(null)}
                className={`rounded-md px-2.5 py-1 text-xs font-medium transition ${
                  styleTarget === null
                    ? 'bg-[var(--brand)] text-white'
                    : 'border border-[var(--border)] text-[var(--muted)] hover:text-[var(--ink)]'
                }`}
              >
                {t('emailPlaceholderTargetAll')}
              </button>
              {availablePlaceholders.map((token) => (
                <button
                  key={token}
                  type="button"
                  onClick={() => selectStyleTarget(token)}
                  className={`rounded-md px-2.5 py-1 font-mono text-xs transition ${
                    styleTarget === token
                      ? 'bg-[var(--brand)] text-white'
                      : 'border border-[var(--border)] text-[var(--muted)] hover:text-[var(--ink)]'
                  }`}
                >
                  {token}
                </button>
              ))}
            </div>
            <p className="mt-2 text-xs text-[var(--muted)]">
              {styleTarget
                ? t('emailPlaceholderStyleHintOne', { token: styleTarget })
                : t('emailPlaceholderStyleHint')}
            </p>
          </div>

          <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <label className="space-y-1 text-xs">
              <span className="font-medium text-[var(--ink)]">{t('emailPlaceholderFontFamily')}</span>
              <select
                value={draftStyle.fontFamily}
                onChange={(e) => updateDraft('fontFamily', e.target.value)}
                className="w-full rounded-md border border-[var(--border)] bg-[var(--surface-elevated)] px-2 py-1.5 text-sm text-[var(--ink)]"
              >
                {FONT_OPTIONS.map((font) => (
                  <option key={font.value} value={font.value}>{font.label}</option>
                ))}
              </select>
            </label>

            <label className="space-y-1 text-xs">
              <span className="font-medium text-[var(--ink)]">{t('emailPlaceholderFontSize')}</span>
              <select
                value={draftStyle.fontSize}
                onChange={(e) => updateDraft('fontSize', e.target.value)}
                className="w-full rounded-md border border-[var(--border)] bg-[var(--surface-elevated)] px-2 py-1.5 text-sm text-[var(--ink)]"
              >
                {FONT_SIZE_OPTIONS.map((size) => (
                  <option key={size} value={size}>{size}</option>
                ))}
              </select>
            </label>

            <label className="space-y-1 text-xs">
              <span className="font-medium text-[var(--ink)]">{t('emailPlaceholderColor')}</span>
              <div className="flex items-center gap-2">
                <input
                  type="color"
                  value={draftStyle.color === 'inherit' ? '#111827' : draftStyle.color}
                  onChange={(e) => updateDraft('color', e.target.value)}
                  className="h-8 w-10 cursor-pointer rounded border border-[var(--border)] bg-transparent"
                />
                <button
                  type="button"
                  className="text-xs text-[var(--brand)]"
                  onClick={() => updateDraft('color', 'inherit')}
                >
                  {t('emailPlaceholderInherit')}
                </button>
              </div>
            </label>

            <div className="space-y-1 text-xs">
              <span className="font-medium text-[var(--ink)]">{t('emailPlaceholderBackground')}</span>
              <div className="flex flex-wrap items-center gap-2">
                <label className="inline-flex items-center gap-1.5 text-[var(--ink)]">
                  <input
                    type="checkbox"
                    checked={bgIsTransparent}
                    onChange={(e) => updateDraft('backgroundColor', e.target.checked ? 'transparent' : '#eff6ff')}
                  />
                  {t('emailPlaceholderTransparent')}
                </label>
                {!bgIsTransparent && (
                  <input
                    type="color"
                    value={draftStyle.backgroundColor}
                    onChange={(e) => updateDraft('backgroundColor', e.target.value)}
                    className="h-8 w-10 cursor-pointer rounded border border-[var(--border)] bg-transparent"
                  />
                )}
              </div>
            </div>

            <div className="flex items-end gap-2">
              <button
                type="button"
                onClick={() => updateDraft('bold', !draftStyle.bold)}
                className={`rounded-md border px-3 py-1.5 text-sm font-bold transition ${
                  draftStyle.bold
                    ? 'border-[var(--brand)] bg-[var(--brand-soft)] text-[var(--brand)]'
                    : 'border-[var(--border)] text-[var(--muted)]'
                }`}
              >
                B
              </button>
              <button
                type="button"
                onClick={() => updateDraft('italic', !draftStyle.italic)}
                className={`rounded-md border px-3 py-1.5 text-sm italic transition ${
                  draftStyle.italic
                    ? 'border-[var(--brand)] bg-[var(--brand-soft)] text-[var(--brand)]'
                    : 'border-[var(--border)] text-[var(--muted)]'
                }`}
              >
                I
              </button>
            </div>
          </div>

          <div className="flex flex-wrap items-center gap-2">
            <span
              className="rounded px-2 py-1 text-sm"
              style={{
                fontSize: draftStyle.fontSize,
                fontFamily: draftStyle.fontFamily === 'inherit' ? undefined : draftStyle.fontFamily,
                fontWeight: draftStyle.bold ? 700 : 400,
                fontStyle: draftStyle.italic ? 'italic' : 'normal',
                color: draftStyle.color === 'inherit' ? undefined : draftStyle.color,
                backgroundColor: bgIsTransparent ? 'transparent' : draftStyle.backgroundColor,
                backgroundImage: bgIsTransparent
                  ? 'linear-gradient(45deg, #e5e7eb 25%, transparent 25%), linear-gradient(-45deg, #e5e7eb 25%, transparent 25%), linear-gradient(45deg, transparent 75%, #e5e7eb 75%), linear-gradient(-45deg, transparent 75%, #e5e7eb 75%)'
                  : undefined,
                backgroundSize: bgIsTransparent ? '8px 8px' : undefined,
                backgroundPosition: bgIsTransparent ? '0 0, 0 4px, 4px -4px, -4px 0' : undefined,
                outline: '1px dashed color-mix(in srgb, var(--brand) 45%, transparent)',
              }}
            >
              {styleTarget ?? '{{name}}'}
            </span>
            <button
              type="button"
              onClick={() => applyStyle(styleTarget, draftStyle)}
              className="rounded-md bg-[var(--brand)] px-3 py-1.5 text-xs font-medium text-white transition hover:opacity-90"
            >
              {styleTarget ? t('emailPlaceholderApplyOne') : t('emailPlaceholderApplyAll')}
            </button>
            <button
              type="button"
              onClick={() => {
                setDraftStyle(DEFAULT_PLACEHOLDER_STYLE)
                applyStyle(styleTarget, DEFAULT_PLACEHOLDER_STYLE)
              }}
              className="rounded-md border border-[var(--border)] px-3 py-1.5 text-xs text-[var(--muted)] transition hover:text-[var(--ink)]"
            >
              {t('emailPlaceholderResetStyle')}
            </button>
          </div>
        </div>
      )}

      <div ref={editorShellRef} className="relative">
      <div
        ref={editorRef}
        contentEditable
        onInput={handleInput}
          onClick={handleEditorClick}
          suppressContentEditableWarning
        className="min-h-[300px] p-4 text-sm text-[var(--ink)] focus:outline-none"
          data-placeholder={resolvedPlaceholder}
          dir={contentDirection}
          lang={contentLocale}
        style={{
          fontFamily: 'system-ui, -apple-system, sans-serif',
            direction: contentDirection,
            unicodeBidi: 'isolate',
            textAlign: contentLocale === 'ar' ? 'right' : 'left',
          }}
        />

        {dropIndicator && (
          <div
            className="pointer-events-none absolute z-30 rounded-full bg-[var(--brand)] shadow"
            style={{
              top: dropIndicator.top,
              left: dropIndicator.left,
              width: dropIndicator.width,
              height: dropIndicator.height,
            }}
            aria-hidden
          />
        )}

        {imageFrame && !isMovingImage && (
          <div
            className="pointer-events-none absolute z-20"
            style={{
              top: imageFrame.top,
              left: imageFrame.left,
              width: imageFrame.width,
              height: imageFrame.height,
            }}
            aria-hidden
          >
            <div
              className="pointer-events-auto absolute inset-0 cursor-grab border-2 border-[var(--brand)] active:cursor-grabbing"
              onMouseDown={beginImageMove}
            />
            {([
              { corner: 'nw' as const, className: '-left-1.5 -top-1.5 cursor-nwse-resize' },
              { corner: 'ne' as const, className: '-right-1.5 -top-1.5 cursor-nesw-resize' },
              { corner: 'sw' as const, className: '-bottom-1.5 -left-1.5 cursor-nesw-resize' },
              { corner: 'se' as const, className: '-bottom-1.5 -right-1.5 cursor-nwse-resize' },
            ]).map(({ corner, className }) => (
              <button
                key={corner}
                type="button"
                tabIndex={-1}
                aria-label={corner}
                className={`pointer-events-auto absolute z-10 h-3.5 w-3.5 rounded-sm border-2 border-[var(--brand)] bg-white shadow-sm ${className}`}
                onMouseDown={(event) => beginImageResize(corner, event)}
              />
            ))}
            {imageSize && (
              <div className="pointer-events-none absolute start-0 top-full mt-1 rounded bg-[var(--ink)] px-1.5 py-0.5 text-[10px] font-medium text-white shadow">
                {t('emailEditorImageSize', {
                  width: String(imageSize.width),
                  height: String(imageSize.height),
                })}
              </div>
            )}
          </div>
        )}
      </div>

      {imageFrame && !isMovingImage && (
        <p className="border-t border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs text-[var(--muted)]">
          {t('emailEditorDragImage')} — {t('emailEditorResizeImage')} — {t('emailEditorInsertImageBeside')}
        </p>
      )}
      {isMovingImage && (
        <p className="border-t border-[var(--border)] bg-[var(--surface)] px-4 py-2 text-xs text-[var(--muted)]">
          {dropIndicator?.orientation === 'vertical'
            ? t('emailEditorDropBesideHint')
            : t('emailEditorDropImageHint')}
        </p>
      )}
    </div>
  )
}
