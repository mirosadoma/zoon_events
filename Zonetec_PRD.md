# ZONETEC

**Event Management & Access Platform**

*Product Requirements Document*

Self-service registration, ticketing, on-site credentialing, wallet passes, and ACS-integrated access control — SaaS and on-premise, built for the GCC market.

**Product:** Zonetec — Event Management SaaS / On-Premise Platform

**Owner:** Runa Technology (Riyadh, KSA)

**Document:** PRD v0.1 — Draft for review

**Date:** 29 June 2026

**Status:** Scoping — v1 anchored on self-registration + ticketing core

# Contents

# 1. Context & Market Landscape

Zonetec is a multi-tenant event management platform for the GCC — with Saudi Arabia as the primary market — that lets organizers run self-service registration and ticketing, then layer on optional on-site services: pass-printing kiosks (and manual desks), pass scanning, Apple/Google Wallet passes, identity verification, and physical access control through turnstiles and security gates driven by Runa's in-house ACS software.

A distinguishing element is a separate venue-owner account type: venue operators register their fixed infrastructure — cameras, security gates, turnstiles, kiosks — and make it rentable to event organizers for the duration of an event, brokered through Zonetec.

## 1.1 Competitive Reference Points

Three reference categories informed this PRD. Findings are summarized; none of the named platforms combine all of Zonetec's intended capabilities, which is the core opportunity.

### White-label, API-first ticketing (vivenu, Ticketmaster)

vivenu positions itself as a headless, API-first ticketing backbone rather than a consumer marketplace.

- Full white-labeling and 100% data ownership — the buyer never leaves the organizer's domain, and the organizer remains merchant of record.

- Open REST APIs, webhooks, and SDKs; configurable pricing tiers (early-bird, capacity-based), reserved seating / GA, box office and POS, reseller tools.

- Multi-tenant architecture designed for steady-state scale across many concurrent events, not one big on-sale.

- Trade-off noted in reviews: no built-in buyer audience (unlike Ticketmaster's marketplace), and surrounding tools — CRM, access-control hardware, finance — must be wired in separately.

Implication for Zonetec: adopt the API-first, white-label, data-ownership posture, but ship the on-site + access-control + identity stack natively rather than leaving it as integration work.

### Self-service check-in & on-demand badge printing (Expo Pass, Whova, Swapcard)

- Attendees scan a QR (emailed or in a wallet pass) or look up their name at an iPad kiosk; the system pulls registration data and prints a badge in seconds via thermal printers — no ink, no pre-sorting.

- Hybrid model: pre-printed branded/sponsor badge shells + on-demand personalization (name, company, title, QR) at check-in.

- Reprints for lost/damaged badges with permission controls; bulk printing for group registrations; walk-up / on-site enrollment for last-minute registrations.

- Optional 2FA at the kiosk to confirm the person checking in is the registered attendee; real-time check-in dashboards and VIP arrival alerts.

### Wallet passes & contactless entry (Apple Wallet, Google Wallet)

- One-tap “Add to Apple/Google Wallet” from the confirmation email/SMS; passes hold a unique QR (and optionally NFC) credential, event details, and an expiry.

- Passes work offline, update automatically when an event changes (room/time), and can be revoked instantly from the dashboard.

- NFC “tap to enter” can be faster than QR at the gate; returning attendees skip re-registration.

### Access control / turnstile integration (Runa in-house ACS)

- Modern turnstiles integrate with access-control software over OSDP (encrypted, bidirectional, tamper-detecting — the current standard) and legacy Wiegand; controllers enforce anti-passback and log every entry/exit event.

- Multi-modal entry: QR / barcode, NFC, RFID, and facial recognition; face used for VIP/staff lanes with a card or QR fallback for throughput and accuracy.

- Fail-safe behavior (gates unlock on fire-alarm signal) and remote health/connectivity monitoring of unattended terminals are expected baseline.

Implication for Zonetec: Runa already runs a production ACS controlling real turnstiles. The opportunity is a clean, documented integration contract between Zonetec credentials and the ACS — not building access hardware from scratch.

### Saudi identity & data-residency context (Nafath, Absher, Yaqeen, PDPL)

- Nafath is the national unified single sign-on / digital-identity gateway (SDAIA / National Information Center), with biometric and OTP verification.

- Yaqeen is the authoritative identity-verification service used for KYC — validating national IDs (CN) and Iqama numbers and returning name, nationality, and status.

- Absher / Tawakkalna expose a digital national ID with a QR code accepted as identity proof; the Tawakkalna Code shares verified data via a temporary code without revealing the raw ID number.

- PDPL (in force since Sept 2023) governs personal and biometric data: explicit consent or legal necessity to process sensitive data, store templates not raw images, define retention, and restrict cross-border transfer — a direct driver of the on-premise deployment option.

Sources: vivenu (vivenu.com, G2, Capterra, FitGap); Expo Pass, Whova, Swapcard, EventPassHero, Ticket Fairy; Alcatraz / Hikvision / Enforce.sg on OSDP & biometric turnstiles; SDAIA, my.gov.sa, SAMA Rulebook, getfocal.ai, it-pillars.com on Nafath/Absher/Yaqeen/PDPL. Researched June 2026; verify API access and regulatory specifics before build.

# 2. Problem Statement

Event organizers in the GCC — corporate, public, VIP, and high-VVIP — currently stitch together separate tools for registration, ticketing, on-site badge printing, wallet passes, identity verification, and physical access control. The handoffs between these systems are where events break: lines form at check-in, credentials don't match the gate, VIP/VVIP vetting is manual and slow, and attendee data is scattered across vendors with unclear PDPL compliance.

At the same time, venues own valuable fixed infrastructure — turnstiles, cameras, security gates, kiosks — that sits idle between events, while organizers re-rent or rebuild the same hardware each time. There is no trusted broker that lets a venue lease vetted, pre-integrated infrastructure to an organizer for the duration of an event.

Cost of not solving it: organizers absorb higher staffing and hardware costs and a worse arrival experience; venues leave infrastructure revenue on the table; and in a market with strict identity and data-residency expectations, fragmented tooling is a compliance liability. No single platform today unifies self-service ticketing, on-site credentialing, ACS-driven access, identity verification, and an infrastructure-rental marketplace — with both SaaS and on-premise delivery.

## 2.1 Target Users (Personas)

| Persona | Description & primary need |
| --- | --- |
| Event Organizer | Corporate / public / VIP / VVIP event owner. Wants to create an event, open self-registration or paid ticketing, and turn on the optional on-site services they need — without integrating five vendors. |
| Attendee | Self-registers, receives a wallet pass or QR, optionally completes identity verification, and enters via kiosk badge print and/or turnstile. Wants a fast, low-friction arrival. |
| Venue Owner | Operates a fixed venue. Registers cameras, security gates, turnstiles, and kiosks as rentable infrastructure and lists availability for organizers to lease through Zonetec. |
| On-site Staff | Mans manual pass desks, monitors kiosks and gates, handles reprints, exceptions, and VVIP fast-tracking from a real-time dashboard. |
| Security / ACS Operator | Maps Zonetec credentials to access zones, monitors gate health and entry logs, manages anti-passback and emergency egress via Runa's ACS. |
| Platform Admin (Runa) | Manages tenants, on-premise deployments, billing, marketplace settlement, and compliance configuration (PDPL, retention, residency). |

# 3. Goals & Non-Goals

## 3.1 Goals

- Unify the event lifecycle. One platform from self-registration through gate entry, so an organizer never integrates a separate ticketing, badging, wallet, identity, and access vendor. Target: a corporate event launchable end-to-end in under 1 day of setup.

- Make self-service the default. Attendees register, pay, get credentialed, and enter with minimal staff. Target: ≥80% of check-ins self-service (kiosk or wallet/turnstile) at events that enable it.

- Cut arrival friction. On-demand badge print or wallet/NFC entry in seconds. Target: median check-in (scan → entry or badge in hand) under 10 seconds; first-scan success ≥95%.

- Right-size assurance per event tier. From open self-registration (public) up to gov-verified + face-capture identity (VVIP) — configurable per event. Target: VVIP identity verification completed pre-event for ≥90% of VVIP attendees.

- Open an infrastructure-rental marketplace. Venue owners monetize idle fixed infrastructure; organizers lease pre-integrated hardware. Target: a measurable share of events using at least one rented venue asset within 2 quarters of marketplace launch.

- Deliver SaaS and on-premise from one codebase. Select clients (government, high-security, data-residency-bound) run on-premise with no loss of core functionality.

## 3.2 Non-Goals (v1)

| Out of scope for v1 | Why |
| --- | --- |
| Public consumer ticketing marketplace / discovery | Zonetec is organizer-owned & white-label (vivenu model), not a Ticketmaster-style demand marketplace. Building an audience network is a separate, later initiative. |
| Manufacturing access hardware or turnstiles | Runa's ACS already drives third-party gates. Zonetec integrates; it does not build hardware. |
| Building a new biometric matching engine | Face capture/verification uses the ACS's existing engine and/or gov identity services. No new in-house 1:N matcher in v1. |
| Full event-app (agenda, networking, sponsor marketplace) | Swapcard/Whova-style engagement features are adjacent; v1 focuses on registration → access. Designed for, not built. |
| Real-time demand-based dynamic pricing engine | v1 supports scheduled/threshold price tiers; a live yield engine is a P2. |
| Hardware logistics / shipping of rented infrastructure | Marketplace brokers availability & access for fixed (installed) venue infrastructure; physical shipping of portable kit is out of scope for v1. |

# 4. Event Tiers & Assurance Model

Zonetec configures capability per event tier. Higher tiers add identity assurance and access controls on top of the lower tiers; an organizer enables only what they need.

| Tier | Registration | Identity assurance | Typical access |
| --- | --- | --- | --- |
| Corporate | Self-registration, optional approval, domain allow-list | Email/OTP; optional name lookup at kiosk | QR/wallet → kiosk badge + optional turnstile |
| Public | Open self-registration or paid ticketing, price tiers | Email/OTP; optional ID for age-gated events | Wallet/QR → turnstile, high-throughput lanes |
| VIP | Invite or upgrade; reserved allocations | Optional gov ID (Nafath/Absher QR) or face enrollment | Dedicated VIP lane; face or NFC fast-track |
| VVIP | Invite-only, host approval, allocation caps | Required: gov-verified (Yaqeen/Nafath) where possible + face capture fallback | Biometric/face lane via ACS; manual concierge desk |

Add-on (any tier): ID verification module — national ID (Nafath/Absher/Yaqeen) where the attendee is a citizen/resident and the integration is available, with face capture + manual review as the fallback for non-residents, guests, or when gov verification is unavailable. This dual approach is the confirmed v1 direction.

# 5. User Stories

## 5.1 Event Organizer

- As an organizer, I want to create an event and choose its tier (corporate/public/VIP/VVIP) so that the right registration, identity, and access defaults are applied automatically.

- As an organizer, I want to open self-registration with a custom, white-labeled form and (optionally) paid tickets with price tiers so that attendees sign themselves up on my brand.

- As an organizer, I want to toggle optional on-site services (kiosk printing, manual desk, wallet passes, turnstile entry, ID verification) per event so that I only pay for and operate what I need.

- As an organizer, I want to rent a venue's installed turnstiles and cameras for my event dates so that I don't have to source and integrate hardware myself.

- As an organizer, I want a live arrival dashboard (check-ins, gate throughput, VVIP arrivals) so that I can manage staffing and flow in real time.

## 5.2 Attendee

- As an attendee, I want to self-register and immediately receive a QR plus a one-tap Apple/Google Wallet pass so that I'm ready to enter without printing anything.

- As an attendee, I want to complete any required identity verification from my phone before the event so that I'm not delayed at the gate.

- As an attendee, I want to scan at a kiosk and have my badge printed on demand (or tap my wallet pass at a turnstile) so that I get in quickly.

- As an attendee whose pass changed, I want my wallet pass to update automatically so that I always have the correct entry details.

- As an attendee, I want a clear notice of what identity data is collected, why, and for how long so that I can consent knowingly (PDPL).

## 5.3 Venue Owner

- As a venue owner, I want to register my fixed infrastructure (gates, turnstiles, cameras, kiosks) with capabilities and capacity so that organizers can discover and rent it.

- As a venue owner, I want to set availability windows and pricing for each asset so that I monetize idle capacity between my own events.

- As a venue owner, I want time-boxed, automatically revoked access for an organizer's event so that they only control my hardware for the rental duration.

- As a venue owner, I want a settlement statement of rentals so that I can reconcile marketplace revenue.

## 5.4 On-site Staff / Security / ACS Operator

- As on-site staff, I want a manual check-in/print desk that mirrors the kiosk flow so that I can handle walk-ups, exceptions, and reprints with permission control.

- As an ACS operator, I want Zonetec credentials mapped to access zones and lanes so that valid attendees release the correct gate and anti-passback is enforced.

- As an ACS operator, I want gate health, connectivity, and entry/exit logs in one view, plus fire-alarm fail-safe behavior, so that I can run access safely.

- As a security operator, I want VVIP face/biometric lanes to fall back to a manual concierge desk on a no-match so that no guest is stranded at the gate.

# 6. Requirements

Priority key:  P0   must-have v1   P1 fast-follow   P2 future / design-for.

Confirmed v1 anchor: self-registration + ticketing core is the must-have foundation; on-site services, ACS integration, marketplace, and on-premise build outward from it.

## 6.1 Registration & Ticketing Core (the v1 anchor)

| Pri | Requirement | Acceptance criteria |
| --- | --- | --- |
| P0 | White-labeled self-registration with configurable forms per event/tier | Given an organizer's event, when an attendee opens the registration link, then the form shows the organizer's branding on the organizer's (sub)domain and captures the configured fields; submission creates an attendee record and a unique credential. |
| P0 | Ticket types, allocations & scheduled price tiers | Organizer can define ticket types, quantities/holds, and time- or capacity-based price tiers; inventory decrements atomically; sold-out and waitlist states handled. |
| P0 | Payments & order management | Supports a regional payment gateway (KSA), confirmation email/SMS with QR, refunds and order edits; organizer is merchant of record (white-label). |
| P0 | Unique, revocable credential per attendee | Each attendee gets a unique QR/credential ID that can be revoked or reissued; revoked credentials fail at scan and gate. |
| P1 | Approval workflows & invite-only allocations | VIP/VVIP events support host approval, invite codes, and per-host allocation caps before a credential is issued. |
| P1 | Group / bulk registration | An organizer or delegate can register a group, and badges/passes can be issued in bulk. |
| P2 | Real-time demand-based dynamic pricing | Live yield engine re-prices against demand (beyond scheduled tiers). |

## 6.2 Wallet Passes & Pass Scanning

| Pri | Requirement | Acceptance criteria |
| --- | --- | --- |
| P0 | Apple Wallet & Google Wallet passes | Confirmation includes a one-tap Add-to-Wallet for both platforms; pass holds the QR credential, event details, and expiry; no third-party app required. |
| P0 | Pass scanning (QR) for check-in & entry | A scanner (kiosk camera, handheld, or staff phone) validates a pass in real time, prevents double-entry, and records the scan; offline-tolerant with later sync. |
| P0 | Dynamic pass update & remote revocation | When an organizer changes event details or revokes a pass, the wallet pass reflects it; revoked passes are rejected at scan/gate. |
| P1 | NFC tap-to-enter at turnstiles | Where hardware supports it, an attendee taps the wallet pass at an NFC reader and the gate releases, faster than QR alignment. |

## 6.3 On-site Kiosk & Badge Printing (+ Manual Desk)

| Pri | Requirement | Acceptance criteria |
| --- | --- | --- |
| P0 | Self-service kiosk check-in | Attendee scans QR or looks up by name/email/ID at a kiosk; the system retrieves their record and proceeds to badge print or entry; branded, touch-friendly UI. |
| P0 | On-demand badge printing | Thermal badge prints on check-in with configured fields (name, company, title, QR, tier); supports pre-printed branded shells + on-demand personalization. |
| P0 | Manual desk mode | Staff-operated flow mirrors the kiosk for walk-ups, exceptions, and on-site enrollment; permissioned reprints for lost/damaged badges. |
| P0 | Drag-and-drop badge designer | Organizer designs badge layouts (logos, fields, tier color/zone) per attendee type without code; preview before print. |
| P1 | Kiosk 2FA at check-in | Optional second factor (e.g., OTP) confirms the person is the registered attendee before badge release. |
| P1 | Remote kiosk/printer health monitoring | Central dashboard shows connectivity, scan rate, and printer status of each unattended station. |

## 6.4 ACS / Security-Gate Integration (Runa in-house, production)

| Pri | Requirement | Acceptance criteria |
| --- | --- | --- |
| P0 | Credential→ACS authorization contract | A documented, secured interface passes a validated Zonetec credential to the ACS, which authorizes the lane/zone and releases the gate; decisions and entries are logged back to Zonetec. |
| P0 | Zone & lane mapping per event | Operator maps tiers/ticket types to access zones and lanes (e.g., VIP lane, staff gate); credentials only open authorized lanes. |
| P0 | Entry/exit logging & anti-passback | Every gate event is logged with timestamp and credential; anti-passback rejects re-entry of the same credential until an exit is read. |
| P0 | Emergency egress / fail-safe | On a fire-alarm or emergency signal, gates fail open per configured zone; behavior is documented and testable. |
| P1 | OSDP-first reader support, Wiegand fallback | Integration supports OSDP (encrypted) readers as default and Wiegand for legacy installs. |
| P1 | Live gate health & throughput dashboard | Operator sees per-gate status, throughput, and alerts in real time. |

## 6.5 Identity Verification (gov-verified + face-capture fallback)

Confirmed v1 direction: both — gov-verified where possible, face capture as fallback. Treat all gov-API specifics as Open Questions until access is confirmed.

| Pri | Requirement | Acceptance criteria |
| --- | --- | --- |
| P0 | Configurable ID-verification add-on per event/tier | Organizer enables ID verification and sets it required (VVIP) or optional (VIP); attendees see consent + purpose + retention notice before any capture (PDPL). |
| P0 | Face capture + manual review fallback | Attendee captures a face image / liveness; on no gov match or non-resident, a permissioned reviewer approves/rejects with an audit trail; verified status attaches to the credential. |
| P0 | Gov identity verification where available | Where the attendee is a KSA citizen/resident and the integration is live, verify via the appropriate gov service (e.g., Nafath SSO / Absher digital-ID QR / Yaqeen) and mark the credential gov-verified. |
| P0 | Biometric data handling per PDPL | Store templates (not raw images) where feasible, enforce a configured retention period, default to edge/on-prem processing for sensitive tiers, and restrict cross-border transfer. |
| P1 | Face lane at the gate for VIP/VVIP | Verified face enrollment releases a dedicated lane via the ACS, with a card/QR or manual-desk fallback on no-match. |
| P2 | Tawakkalna-Code style consented data share | Attendee shares verified attributes via a temporary code without exposing the raw national ID number. |

## 6.6 Venue-Owner Account & Infrastructure-Rental Marketplace

| Pri | Requirement | Acceptance criteria |
| --- | --- | --- |
| P0 | Separate venue-owner account type | A venue owner registers and manages a distinct account with venue profile and an inventory of fixed infrastructure (gates, turnstiles, cameras, kiosks) and their capabilities. |
| P0 | Asset listing, availability & pricing | Owner sets availability windows and rental pricing per asset; organizers discover and request assets for specific event dates. |
| P0 | Time-boxed delegated control + auto-revocation | On an approved rental, the organizer gains scoped control of the assets only for the event window; access auto-revokes at the end; owner retains override. |
| P1 | Marketplace booking, approval & settlement | Rental request → owner approval → confirmation; a settlement statement records charges and payouts. |
| P2 | Camera feed access scoping for rented cameras | Where cameras are rented, feed access is scoped to the event and revoked after, consistent with PDPL/CCTV rules. |

## 6.7 Deployment: SaaS + On-Premise

| Pri | Requirement | Acceptance criteria |
| --- | --- | --- |
| P0 | Multi-tenant SaaS (default) | Tenants are isolated; concurrent events across tenants do not degrade one another; core flows work for all tiers. |
| P0 | On-premise deployment for select clients | A deployable package runs core registration, ticketing, kiosk/printing, ACS integration, and identity (incl. local biometric processing) within the client's environment / KSA residency, with no loss of core functionality. |
| P1 | Configurable data residency & retention | Per-tenant settings for data location and retention windows satisfy PDPL; sensitive/biometric data can be pinned on-prem. |
| P1 | Hybrid sync | On-prem deployments can optionally sync non-sensitive operational data to a central console for support and analytics. |

# 7. Success Metrics

## 7.1 Leading indicators (days–weeks)

| Metric | Success | Stretch |
| --- | --- | --- |
| Self-service check-in share (events with it enabled) | ≥80% | ≥90% |
| Median check-in time (scan → badge/entry) | ≤10 s | ≤5 s |
| First-scan / first-tap success rate | ≥95% | ≥98% |
| Wallet-pass adoption among registrants | ≥50% | ≥70% |
| VVIP identity verification completed pre-event | ≥90% | ≥98% |
| Event end-to-end setup time (corporate) | < 1 day | < 2 hours |

## 7.2 Lagging indicators (weeks–quarters)

- Organizer retention / repeat events on Zonetec.

- Share of events using ≥1 rented venue asset (marketplace traction) within 2 quarters of launch.

- On-site staffing cost per 1,000 attendees vs. organizer's prior baseline.

- Gate incident / tailgating rate and unresolved no-match rate at biometric lanes.

- Number of select clients live on on-premise; zero PDPL/residency findings in audits.

- Support-ticket volume per event (fragmentation removed should reduce it).

# 8. Open Questions

| Question | Owner | Blocking? |
| --- | --- | --- |
| Do we have (or can we obtain) production access to Nafath / Absher digital-ID / Yaqeen for identity verification, and under what terms? | Legal / Biz-dev | Blocking for 6.5 gov path |
| What is the exact credential → ACS interface (protocol, auth, latency budget, offline behavior)? Confirm with Runa ACS team. | Engineering | Blocking for 6.4 |
| PDPL: confirmed retention periods, template-vs-image storage, and cross-border rules for face/biometric data per tier. | Legal | Blocking for 6.5 / on-prem |
| Which KSA payment gateway(s) and settlement model for tickets and for marketplace payouts? | Biz-dev / Finance | Blocking for 6.1 / 6.6 settlement |
| Marketplace economics: commission model, liability for rented hardware, insurance, and dispute handling. | Biz-dev / Legal | Non-blocking for v1 core |
| Badge/kiosk hardware reference list (printer models, kiosk OS) Zonetec officially supports at launch. | Engineering / Ops | Non-blocking |
| On-prem packaging target (container/VM/appliance) and the support/update model for air-gapped clients. | Engineering | Non-blocking for SaaS v1 |
| For rented cameras, what feed-access and CCTV-notice obligations apply, and is feed access in v1 scope at all? | Legal | Non-blocking (P2) |

# 9. Timeline & Phasing

Suggested phasing. Each phase is independently shippable; the v1 anchor is registration + ticketing + the credential model that everything else binds to.

| Phase | Theme | Scope |
| --- | --- | --- |
| Phase 1 | Registration + ticketing core | White-label self-registration, ticket types & scheduled tiers, payments, unique revocable credential, Apple/Google Wallet passes, QR pass scanning. Multi-tenant SaaS. |
| Phase 2 | On-site credentialing | Self-service kiosk check-in, on-demand badge printing + manual desk, badge designer, reprints/2FA, kiosk/printer health monitoring. |
| Phase 3 | Access control + identity | Credential→ACS contract, zone/lane mapping, entry logging & anti-passback, emergency egress; ID-verification add-on (gov-verified + face-capture fallback) with PDPL handling. |
| Phase 4 | Venue marketplace + on-prem | Venue-owner account, asset listing/availability/pricing, time-boxed delegated control & settlement; on-premise deployment package and residency/retention configuration. |

Dependencies & hard constraints: Phase 3 gov identity depends on confirmed Nafath/Absher/Yaqeen access (Open Question 8.1) and the Runa ACS interface (8.2); both should be unblocked during Phase 2 so Phase 3 isn't gated. Any contractual event dates (e.g., a flagship KSA event used as a launch pilot) would pull Phases 1–2 forward and should be named before build planning.

Next artifacts available on request: a design brief for the self-registration + kiosk flows, an engineering ticket breakdown for Phase 1, or a one-page stakeholder pitch for Runa leadership.
