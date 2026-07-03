---

description: "Task list template for feature implementation"
---

# Tasks: [FEATURE NAME]

**Input**: Design documents from `/specs/[###-feature-name]/`

**Prerequisites**: plan.md (required), spec.md (required for user stories), research.md, data-model.md, contracts/

**Tests**: Automated tests are MANDATORY. Include unit, integration, contract,
end-to-end, tenant-isolation, RBAC, audit, and security/regression tasks as
applicable to the specification and plan.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

**Product Phase**: [Foundation / Phase 1 Registration-Ticketing-Credentials /
later approved phase from plan.md]

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

- **Single project**: `src/`, `tests/` at repository root
- **Web app**: `backend/src/`, `frontend/src/`
- **Mobile**: `api/src/`, `ios/src/` or `android/src/`
- Paths shown below assume single project - adjust based on plan.md structure

<!--
  ============================================================================
  IMPORTANT: The tasks below are SAMPLE TASKS for illustration purposes only.

  The /speckit-tasks command MUST replace these with actual tasks based on:
  - User stories from spec.md (with their priorities P1, P2, P3...)
  - Feature requirements from plan.md
  - Entities from data-model.md
  - Endpoints from contracts/

  Tasks MUST be organized by user story so each story can be:
  - Implemented independently
  - Tested independently
  - Delivered as an MVP increment

  DO NOT keep these sample tasks in the generated tasks.md file.
  ============================================================================
-->

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

- [ ] T001 Create project structure per implementation plan
- [ ] T002 Initialize [language] project with [framework] dependencies
- [ ] T003 [P] Configure linting and formatting tools

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**⚠️ CRITICAL**: No user story work can begin until this phase is complete

Examples of foundational tasks (adjust based on your project):

- [ ] T004 Setup database schema and migrations framework
- [ ] T005 Implement trusted tenant context and tenant-scoped persistence guards
- [ ] T006 [P] Implement RBAC and least-privilege authorization middleware
- [ ] T007 [P] Implement append-only audit logging foundation
- [ ] T008 [P] Setup versioned API routing, documentation, and error contracts
- [ ] T009 [P] Define external adapter interfaces and test doubles
- [ ] T010 [P] Establish signed credential key management and validation scaffold when credentials are in scope
- [ ] T011 [P] Configure Arabic/English localization and RTL/LTR test support
- [ ] T012 Configure SaaS/on-premise environment and secret management
- [ ] T013 Configure CI test, lint, migration, and API-contract gates
- [ ] T014 Add foundational tenant-isolation, RBAC, and audit integration tests

**Checkpoint**: Foundation ready - user story implementation can now begin in parallel

---

## Phase 3: User Story 1 - [Title] (Priority: P1) 🎯 MVP

**Goal**: [Brief description of what this story delivers]

**Independent Test**: [How to verify this story works on its own]

### Tests for User Story 1 (MANDATORY)

> **NOTE: Write these tests FIRST, ensure they FAIL before implementation**

- [ ] T015 [P] [US1] Contract test for [endpoint] in tests/contract/test_[name].py
- [ ] T016 [P] [US1] Integration test for [user journey] in tests/integration/test_[name].py
- [ ] T017 [P] [US1] Tenant-isolation and RBAC denial tests in tests/security/test_[name].py
- [ ] T018 [P] [US1] Audit-event assertions for security-sensitive actions

### Implementation for User Story 1

- [ ] T019 [P] [US1] Create [Entity1] model in src/models/[entity1].py
- [ ] T020 [P] [US1] Create [Entity2] model in src/models/[entity2].py
- [ ] T021 [US1] Implement [Service] in src/services/[service].py (depends on T019, T020)
- [ ] T022 [US1] Implement [endpoint/feature] in src/[location]/[file].py
- [ ] T023 [US1] Add validation and error handling
- [ ] T024 [US1] Add required audit events and operational logging
- [ ] T025 [US1] Add Arabic/English and white-label behavior where user-visible

**Checkpoint**: At this point, User Story 1 should be fully functional and testable independently

---

## Phase 4: User Story 2 - [Title] (Priority: P2)

**Goal**: [Brief description of what this story delivers]

**Independent Test**: [How to verify this story works on its own]

### Tests for User Story 2 (MANDATORY)

- [ ] T026 [P] [US2] Contract test for [endpoint] in tests/contract/test_[name].py
- [ ] T027 [P] [US2] Integration test for [user journey] in tests/integration/test_[name].py
- [ ] T028 [P] [US2] Tenant-isolation and RBAC denial tests in tests/security/test_[name].py
- [ ] T029 [P] [US2] Audit-event assertions for security-sensitive actions

### Implementation for User Story 2

- [ ] T030 [P] [US2] Create [Entity] model in src/models/[entity].py
- [ ] T031 [US2] Implement [Service] in src/services/[service].py
- [ ] T032 [US2] Implement [endpoint/feature] in src/[location]/[file].py
- [ ] T033 [US2] Integrate with User Story 1 components (if needed)

**Checkpoint**: At this point, User Stories 1 AND 2 should both work independently

---

## Phase 5: User Story 3 - [Title] (Priority: P3)

**Goal**: [Brief description of what this story delivers]

**Independent Test**: [How to verify this story works on its own]

### Tests for User Story 3 (MANDATORY)

- [ ] T034 [P] [US3] Contract test for [endpoint] in tests/contract/test_[name].py
- [ ] T035 [P] [US3] Integration test for [user journey] in tests/integration/test_[name].py
- [ ] T036 [P] [US3] Tenant-isolation and RBAC denial tests in tests/security/test_[name].py
- [ ] T037 [P] [US3] Audit-event assertions for security-sensitive actions

### Implementation for User Story 3

- [ ] T038 [P] [US3] Create [Entity] model in src/models/[entity].py
- [ ] T039 [US3] Implement [Service] in src/services/[service].py
- [ ] T040 [US3] Implement [endpoint/feature] in src/[location]/[file].py

**Checkpoint**: All user stories should now be independently functional

---

[Add more user story phases as needed, following the same pattern]

---

## Phase N: Polish & Cross-Cutting Concerns

**Purpose**: Improvements that affect multiple user stories

- [ ] TXXX [P] Documentation updates in docs/
- [ ] TXXX Code cleanup and refactoring
- [ ] TXXX Performance optimization across all stories
- [ ] TXXX [P] Additional regression tests in tests/unit/
- [ ] TXXX Verify tenant isolation, RBAC, audit coverage, and credential security
- [ ] TXXX Verify PDPL retention/residency and data-minimization controls
- [ ] TXXX Verify Arabic/English, RTL/LTR, and white-label behavior
- [ ] TXXX Verify SaaS/on-premise parity and adapter failure behavior
- [ ] TXXX Run quickstart.md validation

---

## Dependencies & Execution Order

### Phase Dependencies

- **Setup (Phase 1)**: No dependencies - can start immediately
- **Foundational (Phase 2)**: Depends on Setup completion - BLOCKS all user stories
- **User Stories (Phase 3+)**: All depend on Foundational phase completion
  - User stories can then proceed in parallel (if staffed)
  - Or sequentially in priority order (P1 → P2 → P3)
- **Polish (Final Phase)**: Depends on all desired user stories being complete

### Product Delivery Dependencies

- **Foundation**: Tenant isolation, RBAC, audit logging, versioned APIs,
  adapter interfaces, migrations, configuration, and automated CI gates.
- **First product phase**: Registration, ticketing/orders, payment adapter,
  attendee records, and signed credential issue/validate/revoke/reissue.
- **Later phases**: Wallet, kiosk, badge, ACS, identity, marketplace, and other
  extensions MUST depend on the accepted first product phase and MUST NOT
  bypass its contracts or security controls.

### User Story Dependencies

- **User Story 1 (P1)**: Can start after Foundational (Phase 2) - No dependencies on other stories
- **User Story 2 (P2)**: Can start after Foundational (Phase 2) - May integrate with US1 but should be independently testable
- **User Story 3 (P3)**: Can start after Foundational (Phase 2) - May integrate with US1/US2 but should be independently testable

### Within Each User Story

- Required tests MUST be written and FAIL before implementation
- Models before services
- Services before endpoints
- Core implementation before integration
- Story complete before moving to next priority

### Parallel Opportunities

- All Setup tasks marked [P] can run in parallel
- All Foundational tasks marked [P] can run in parallel (within Phase 2)
- Once Foundational phase completes, all user stories can start in parallel (if team capacity allows)
- All tests for a user story marked [P] can run in parallel
- Models within a story marked [P] can run in parallel
- Different user stories can be worked on in parallel by different team members

---

## Parallel Example: User Story 1

```bash
# Launch all tests for User Story 1 together:
Task: "Contract test for [endpoint] in tests/contract/test_[name].py"
Task: "Integration test for [user journey] in tests/integration/test_[name].py"

# Launch all models for User Story 1 together:
Task: "Create [Entity1] model in src/models/[entity1].py"
Task: "Create [Entity2] model in src/models/[entity2].py"
```

---

## Implementation Strategy

### MVP First (User Story 1 Only)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational (CRITICAL - blocks all stories)
3. Complete Phase 3: User Story 1
4. **STOP and VALIDATE**: Test User Story 1 independently
5. Deploy/demo if ready

### Incremental Delivery

1. Complete Setup + Foundational → Foundation ready
2. Add User Story 1 → Test independently → Deploy/Demo (MVP!)
3. Add User Story 2 → Test independently → Deploy/Demo
4. Add User Story 3 → Test independently → Deploy/Demo
5. Each story adds value without breaking previous stories

### Parallel Team Strategy

With multiple developers:

1. Team completes Setup + Foundational together
2. Once Foundational is done:
   - Developer A: User Story 1
   - Developer B: User Story 2
   - Developer C: User Story 3
3. Stories complete and integrate independently

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- Verify tests fail before implementing
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Avoid: vague tasks, same file conflicts, cross-story dependencies that break independence
