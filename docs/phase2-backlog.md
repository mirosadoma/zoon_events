# Phase 2 Backlog

Items deferred from Phase 1. These features already have partial implementations in the codebase (Badge/Kiosk/Scanner pages exist) and need enhancement to reach product-level quality.

## 1. Registration Page Builder
- Drag-and-drop canvas for building event registration pages (like JotForm builder)
- Support brand theme customization
- Embedding code generation for inline embedding in customer websites
- Current: `resources/js/pages/tenant/registration/Builder.tsx` (field-based builder)

## 2. Badge Designer Addon
- Visual drag-and-drop designer like Canva
- Template system with layers, text fields, images, QR codes
- Current: `resources/js/pages/tenant/badge-templates/Designer.tsx` + `BadgePreviewCanvas` component

## 3. Kiosk and Badge Desk Addon
- Kiosk provides registration page for walk-ups
- Thin-client app connecting QR code reader + printer
- Scan badge → print flow
- Current: `resources/js/pages/tenant/kiosk/` (Index, Detail, Mode pages)

## 4. Scanner Add-on
- App for scanner device or mobile phones
- Scan pass for entry/exit, register attendance
- Register/record zones (entry/exit) for analytics
- Current: `resources/js/pages/tenant/checkin/Scanner.tsx` + QR camera component
