# Principal Software Engineer 90-Day Issues By Milestone

## Purpose

This document maps the 90-day roadmap milestones to the draft issue backlog. It preserves the milestone structure from the roadmap while keeping the issues sequenced in the recommended execution order.

## Recommended Execution Order Reference

The recommended issue order from the backlog is:

1. Issue 1
2. Issue 13
3. Issue 14
4. Issue 10
5. Issue 7
6. Issue 8
7. Issue 4
8. Issue 5
9. Issue 2
10. Issue 11
11. Issue 6
12. Issue 9
13. Issue 15
14. Issue 3
15. Issue 12

## Phase 1: Foundation And Safety Rails

### Milestone 1.1: Architecture Baseline And Cut Lines

**Milestone Intent**

- Define the canonical execution boundaries for generation, scheduling, and legacy compatibility.

**Issues In Recommended Order**

1. **Issue 1**
   Architecture: define canonical generation boundary and legacy adapter strategy

**Why These Issues Belong Here**

- This milestone is about deciding architectural cut lines before implementation begins.
- Issue 1 is the decision-making and boundary-setting issue that unlocks the rest of the roadmap.

### Milestone 1.2: Observability Baseline

**Milestone Intent**

- Establish a measurable baseline for reliability and traceability before core refactors begin.

**Issues In Recommended Order**

1. **Issue 13**
   Observability: add correlation IDs across scheduler, generation, and notifications
2. **Issue 14**
   Observability: publish baseline metrics for scheduler and generation reliability

**Why These Issues Belong Here**

- Correlation IDs are the first traceability primitive required for safe refactors.
- Baseline metrics are needed before performance and reliability claims can be validated later in the plan.

### Milestone 1.3: Frontend Scheduling Contract

**Milestone Intent**

- Stop further spread of duplicated scheduling UI logic and establish a single schedule interaction contract.

**Issues In Recommended Order**

1. **Issue 10**
   Frontend: extract schedule modal controller from admin.js into page-level module

**Why These Issues Belong Here**

- This milestone is specifically about stabilizing the schedule UI path early.
- Issue 10 creates the initial extracted controller that future frontend work builds on.

## Phase 2: Core Refactor Execution

### Milestone 2.1: Context-Only Generation Core

**Milestone Intent**

- Move generation internals to a single context-driven execution model.

**Issues In Recommended Order**

1. **Issue 7**
   Generation: move core execution to context-only internals via legacy adapter

**Why These Issues Belong Here**

- This is the direct execution issue for the milestone.
- It converts the design decision from Milestone 1.1 into an implementation seam.

### Milestone 2.2: Generation Lifecycle State Model

**Milestone Intent**

- Make generation progress, retries, and recoverable failures explicit.

**Issues In Recommended Order**

1. **Issue 8**
   Generation: implement explicit generation lifecycle states and transitions

**Why These Issues Belong Here**

- This milestone is exactly the transition from implicit generation flow to explicit stateful behavior.

### Milestone 2.3: Queue-Backed Scheduler v1

**Milestone Intent**

- Replace direct cron execution with queue-backed, idempotent, bounded work processing.

**Issues In Recommended Order**

1. **Issue 4**
   Scheduler: introduce queue-backed generation jobs with idempotency keys
2. **Issue 5**
   Scheduler: implement worker locking, pacing, and bounded retries

**Why These Issues Belong Here**

- Issue 4 establishes the queue and idempotent job model.
- Issue 5 hardens the worker behavior so the queue is operationally safe.

### Milestone 2.4: Repository And Transaction Boundary Completion

**Milestone Intent**

- Complete the most important controller-to-service-to-repository cleanup and standardize cross-page admin action handling where it supports the refactor.

**Issues In Recommended Order**

1. **Issue 2**
   Architecture: complete repository migration for high-value controller flows
2. **Issue 11**
   Frontend: standardize admin action state handling for loading, error, and success states

**Why These Issues Belong Here**

- Issue 2 completes the backend boundary cleanup called for in this milestone.
- Issue 11 is not a repository issue, but it is the main Phase 2 frontend support task that standardizes admin action behavior while the deeper backend refactors land.
- Grouping Issue 11 here keeps the overall issue sequence aligned with the recommended order while preserving milestone-based planning.

## Phase 3: Hardening, Rollout, And Optimization

### Milestone 3.1: Scheduler Recovery And Throughput Features

**Milestone Intent**

- Build production-safe recovery and throughput behavior on top of the queue foundation.

**Issues In Recommended Order**

1. **Issue 6**
   Scheduler: add missed-run recovery and safe catch-up processing
2. **Issue 9**
   Generation: support partial recovery for content/image split failures

**Why These Issues Belong Here**

- Issue 6 enables safe scheduler recovery after missed cron windows.
- Issue 9 enables recoverable partial generation behavior that complements the scheduler’s new bounded retry and recovery model.

### Milestone 3.2: Frontend Decomposition And UX Reliability

**Milestone Intent**

- Extend the modular UI pattern beyond scheduling and improve reliability of admin interactions.

**Issues In Recommended Order**

1. **Issue 12**
   Frontend: plan and execute second admin page extraction after scheduling stabilization

**Why These Issues Belong Here**

- This milestone is explicitly about proving the extraction pattern on a second admin page after the scheduling module is stable.

### Milestone 3.3: Observability And Operational Readiness

**Milestone Intent**

- Make the new queue and generation architecture diagnosable and supportable in production.

**Issues In Recommended Order**

1. **Issue 15**
   Observability: extend system status with queue health and operator runbook

**Why These Issues Belong Here**

- This issue makes the queue and generation changes operationally usable for maintainers and site operators.

### Milestone 3.4: PSR-4 Strangler Start For New Code

**Milestone Intent**

- Ensure the roadmap leaves behind a sustainable path for future modular development.

**Issues In Recommended Order**

1. **Issue 3**
   Architecture: start PSR-4 strangler migration for new refactor modules

**Why These Issues Belong Here**

- This is the direct implementation issue for the milestone and should happen late, after the refactor seams are proven.

## Milestone-To-Issue Summary Table

| Overall Order | Phase | Milestone | Issue | Title |
| --- | --- | --- | --- | --- |
| 1 | 1 | Milestone 1.1 | Issue 1 | Architecture: define canonical generation boundary and legacy adapter strategy |
| 2 | 1 | Milestone 1.2 | Issue 13 | Observability: add correlation IDs across scheduler, generation, and notifications |
| 3 | 1 | Milestone 1.2 | Issue 14 | Observability: publish baseline metrics for scheduler and generation reliability |
| 4 | 1 | Milestone 1.3 | Issue 10 | Frontend: extract schedule modal controller from admin.js into page-level module |
| 5 | 2 | Milestone 2.1 | Issue 7 | Generation: move core execution to context-only internals via legacy adapter |
| 6 | 2 | Milestone 2.2 | Issue 8 | Generation: implement explicit generation lifecycle states and transitions |
| 7 | 2 | Milestone 2.3 | Issue 4 | Scheduler: introduce queue-backed generation jobs with idempotency keys |
| 8 | 2 | Milestone 2.3 | Issue 5 | Scheduler: implement worker locking, pacing, and bounded retries |
| 9 | 2 | Milestone 2.4 | Issue 2 | Architecture: complete repository migration for high-value controller flows |
| 10 | 2 | Milestone 2.4 | Issue 11 | Frontend: standardize admin action state handling for loading, error, and success states |
| 11 | 3 | Milestone 3.1 | Issue 6 | Scheduler: add missed-run recovery and safe catch-up processing |
| 12 | 3 | Milestone 3.1 | Issue 9 | Generation: support partial recovery for content/image split failures |
| 13 | 3 | Milestone 3.3 | Issue 15 | Observability: extend system status with queue health and operator runbook |
| 14 | 3 | Milestone 3.4 | Issue 3 | Architecture: start PSR-4 strangler migration for new refactor modules |
| 15 | 3 | Milestone 3.2 | Issue 12 | Frontend: plan and execute second admin page extraction after scheduling stabilization |

## Notes On Ordering

- The file preserves the milestone structure from the roadmap.
- Within each milestone, issues are listed in the same order they appear in the backlog’s recommended execution list.
- Issue 11 is grouped under Milestone 2.4 because it is the only Phase 2 frontend standardization task and supports stabilization while backend boundary cleanup lands.
- Issue 12 appears last because the backlog recommended it after the PSR-4 strangler start, even though it belongs to Milestone 3.2.
