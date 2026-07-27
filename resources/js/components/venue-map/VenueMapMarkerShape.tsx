import { Circle, Group, Path, Rect } from 'react-konva'
import type Konva from 'konva'
import type { ZoneShapeType } from '@/components/venue-map/types'

export function isMarkerShape(shapeType: string | null | undefined): boolean {
  return shapeType === 'pillar' || shapeType === 'person'
}

export function isPointRadiusShape(shapeType: string | null | undefined): boolean {
  return shapeType === 'circle'
    || shapeType === 'ellipse'
    || isMarkerShape(shapeType)
}

/** Person silhouette path in a 24×24 viewBox, origin at center. */
const PERSON_PATH = [
  'M 0 -9.5',
  'a 3.6 3.6 0 1 1 0 7.2',
  'a 3.6 3.6 0 1 1 0 -7.2',
  'M -5.2 0.5',
  'c 0 -2.4 2.2 -3.6 5.2 -3.6',
  's 5.2 1.2 5.2 3.6',
  'v 2.2',
  'c 0 1.1 -0.9 2 -2 2',
  'h -1.1',
  'v 6.8',
  'c 0 1 -0.8 1.8 -1.8 1.8',
  'h -0.6',
  'c -1 0 -1.8 -0.8 -1.8 -1.8',
  'v -6.8',
  'h -1.1',
  'c -1.1 0 -2 -0.9 -2 -2',
  'z',
].join(' ')

type MarkerShapeProps = {
  shapeType: Extract<ZoneShapeType, 'pillar' | 'person'>
  x: number
  y: number
  radiusPx: number
  rotation?: number
  fill: string
  opacity: number
  stroke: string
  strokeWidth: number
  draggable?: boolean
  listening?: boolean
  shapeRef?: (node: Konva.Group | null) => void
  onMouseEnter?: (event: Konva.KonvaEventObject<MouseEvent>) => void
  onMouseMove?: (event: Konva.KonvaEventObject<MouseEvent>) => void
  onMouseLeave?: (event: Konva.KonvaEventObject<MouseEvent>) => void
  onClick?: (event: Konva.KonvaEventObject<MouseEvent>) => void
  onDragEnd?: (event: Konva.KonvaEventObject<DragEvent>) => void
  onTransformEnd?: (event: Konva.KonvaEventObject<Event>) => void
}

export default function VenueMapMarkerShape({
  shapeType,
  x,
  y,
  radiusPx,
  rotation = 0,
  fill,
  opacity,
  stroke,
  strokeWidth,
  draggable = false,
  listening = true,
  shapeRef,
  onMouseEnter,
  onMouseMove,
  onMouseLeave,
  onClick,
  onDragEnd,
  onTransformEnd,
}: MarkerShapeProps) {
  const size = Math.max(8, radiusPx * 2)

  return (
    <Group
      ref={shapeRef}
      x={x}
      y={y}
      rotation={rotation}
      draggable={draggable}
      listening={listening}
      onMouseEnter={onMouseEnter}
      onMouseMove={onMouseMove}
      onMouseLeave={onMouseLeave}
      onClick={onClick}
      onDragEnd={onDragEnd}
      onTransformEnd={onTransformEnd}
    >
      {/* Invisible hit target so the whole marker is draggable/selectable. */}
      <Circle radius={size * 0.55} fill="rgba(0,0,0,0)" listening={listening} />

      {shapeType === 'pillar' ? (
        <>
          <Rect
            x={-size * 0.22}
            y={-size * 0.48}
            width={size * 0.44}
            height={size * 0.96}
            cornerRadius={size * 0.06}
            fill={fill}
            opacity={opacity}
            stroke={stroke}
            strokeWidth={strokeWidth}
            listening={false}
          />
          <Rect
            x={-size * 0.28}
            y={-size * 0.52}
            width={size * 0.56}
            height={size * 0.12}
            cornerRadius={size * 0.04}
            fill={fill}
            opacity={Math.min(opacity + 0.15, 1)}
            stroke={stroke}
            strokeWidth={Math.max(1, strokeWidth - 0.5)}
            listening={false}
          />
          <Rect
            x={-size * 0.28}
            y={size * 0.4}
            width={size * 0.56}
            height={size * 0.12}
            cornerRadius={size * 0.04}
            fill={fill}
            opacity={Math.min(opacity + 0.1, 1)}
            stroke={stroke}
            strokeWidth={Math.max(1, strokeWidth - 0.5)}
            listening={false}
          />
        </>
      ) : (
        <>
          <Circle
            radius={size * 0.42}
            fill={fill}
            opacity={Math.min(opacity * 0.35, 0.45)}
            stroke={stroke}
            strokeWidth={1}
            dash={[4, 3]}
            listening={false}
          />
          <Path
            data={PERSON_PATH}
            scaleX={size / 24}
            scaleY={size / 24}
            fill={fill}
            opacity={Math.min(opacity + 0.25, 1)}
            stroke={stroke}
            strokeWidth={Math.max(0.6, (strokeWidth * 24) / size)}
            lineJoin="round"
            lineCap="round"
            listening={false}
          />
        </>
      )}
    </Group>
  )
}
