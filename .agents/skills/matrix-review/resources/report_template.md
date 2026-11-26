# <PR #<PR_NUMBER> (`<BRANCH_NAME>`) | Subsystem: `<SUBSYSTEM_NAME>`> — Multi-Level Matrix Code Review Report

- **Target**: <PR #<PR_NUMBER> — `<PR_TITLE>` (`<BRANCH_NAME>` @ `<HEAD_SHA>`) | Subsystem `<SUBSYSTEM_NAME>` (`<BRANCH_NAME>` @ `<HEAD_SHA>`)>
- **Base Branch**: `<BASE_BRANCH>` (`<BASE_SHA>`) *(or `N/A — Subsystem Audit on current HEAD`)*
- **Scope**: `<N>` files changed (`+<ADDED>` / `-<DELETED>`) *(or `<N>` subsystem files audited)*
- **Review Mode**: Read-Only Multi-Level Execution-Trace & Matrix Audit (`matrix-review`)
- **Overall Verdict**: **`<REQUEST_CHANGES | APPROVE_WITH_NITS | APPROVE | BROKEN_CRITICAL_PATHS | NEEDS_HARDENING | HEALTHY>`**

---

## 1. Executive Summary

### 1.1 High-Level Assessment
<Concise summary of what the PR or subsystem accomplishes, overall architectural quality, and primary risk areas discovered.>

### 1.2 Defect Tally by Severity

| Severity | Count | Summary of Impact |
| :--- | :---: | :--- |
| **Critical (`[C-01]`..)** | `<N>` | Uncaught runtime fatals, data loss/corruption, security/capability bypasses, or broken core safeguards. |
| **High (`[H-01]`..)** | `<N>` | Functional regressions, premature completion/stalls, migration defects, broken UI/AJAX flows, or failing test suites. |
| **Medium (`[M-01]`..)** | `<N>` | Edge-case race conditions, error-path state leaks, missing cleanup, or scalability caps. |
| **Low (`[L-01]`..)** | `<N>` | Minor performance inefficiencies, dead constants/filters, doc/comment drift, or hygiene nits. |
| **Total** | **`<TOTAL>`** | |

### 1.3 Matrix Verdict Summary

| Matrix Group | Total Cases | `PASS` | `PARTIAL / DEFECT` | `FAIL / DEFECT` | `FAIL / CRITICAL FATAL` |
| :--- | :---: | :---: | :---: | :---: | :---: |
| **Group 1: <Name>** | `<N>` | `<N>` | `<N>` | `<N>` | `<N>` |
| **Group 2: <Name>** | `<N>` | `<N>` | `<N>` | `<N>` | `<N>` |
| **Total** | **`<TOTAL>`** | **`<N>`** | **`<N>`** | **`<N>`** | **`<N>`** |

---

## 2. Severity-Ranked Defects & Suggested Unified Diff Patches

> Every confirmed defect includes exact `file:line` evidence, trace impact, affected matrix IDs, and a concrete suggested fix diff.

### 2.1 Critical Defects

#### `[C-01]` <Short Descriptive Title>
- **Severity**: Critical
- **Affected Matrix Scenarios**: `<IDs>`
- **Location**: `<path/to/file.php:line-range>`
- **Lens**: `<Symbolic Trace | Caller-Callee Contract | Retired-Code/Cross-Path Parity | Orphan Reference Sweep | Prior-Fix Falsification>`
- **Evidence & Root Cause**:
  <Exact code trace showing why and when this fails.>
- **Suggested Fix Patch**:
```diff
diff --git a/<path/to/file.php> b/<path/to/file.php>
--- a/<path/to/file.php>
+++ b/<path/to/file.php>
@@ -<line>,<count> +<line>,<count> @@
- <old code>
+ <new code>
```

---

### 2.2 High Defects

#### `[H-01]` <Short Descriptive Title>
- **Severity**: High
- **Affected Matrix Scenarios**: `<IDs>`
- **Location**: `<path/to/file.php:line-range>`
- **Lens**: `<Lens>`
- **Evidence & Root Cause**:
  <Exact code trace.>
- **Suggested Fix Patch**:
```diff
diff --git a/<path/to/file.php> b/<path/to/file.php>
--- a/<path/to/file.php>
+++ b/<path/to/file.php>
@@ -<line>,<count> +<line>,<count> @@
```

---

### 2.3 Medium Defects

#### `[M-01]` <Short Descriptive Title>
...

---

### 2.4 Low Defects

#### `[L-01]` <Short Descriptive Title>
...

---

### 2.5 Falsified / Downgraded Candidate Hypotheses
<Record candidate issues from initial exploration or matrix notes that were tested via the Adversarial Self-Falsification Gate (e.g., magic methods, inheritance, container bindings) and proven safe or downgraded, with exact `file:line` proof.>

---

## 3. PR Comment & Fix-Commit Verification Audit *(Omit in Non-PR Subsystem Audit Mode)*

| Item / Commit | Claimed Fix or Finding | Verdict (`Verified Fixed` / `Partially Fixed` / `Unresolved / Regression`) | Direct Code Evidence (`file:line`) & Residual Risks |
| :--- | :--- | :--- | :--- |
| **`<Commit/Item 1>`** | <Claim> | **`<Verdict>`** | <Exact `file:line` verification and any linked defect IDs> |

---

## 4. Feature Mechanism Map & Architectural Parity Audit (Tiers A / B / C)

### 4.1 Tier A — Primary / New Architecture Paths
<End-to-end trace of each primary flow, entry point, handler, and storage mechanism.>

### 4.2 Tier B — Hybrid / Partially Wired Mechanisms (Disconnects & Split-Brain Risks)
<Inventory of half-migrated callers, registered-but-undispatched types/hooks/actions, or dual-path state.>

### 4.3 Tier C — Untouched Dependents & Orphan Reference Sweep
<Results of full-repo `git grep` for retired tables, classes, methods, hooks, and options, plus prioritized unification/migration roadmap.>

### 4.4 Retired-Code / Cross-Path Parity Table

| Old Method / Parallel Path Safeguard | New / Compared Location | Parity Status | Notes / Linked Defect |
| :--- | :--- | :--- | :--- |
| `<OldClass::method() guard>` | `<NewClass::method()>` | `Preserved` / `DROPPED` / `Altered` | <Details> |

---

## 5. Complete Scenario Matrix Execution Traces

### Group 1: <Group Name>

#### `<ID-01>`: <Scenario Title>
- **Verdict**: **`<PASS | PARTIAL / DEFECT | FAIL / DEFECT | FAIL / CRITICAL FATAL>`**
- **Traced Call Chain**:
  1. `<Caller::method()>` (`<file.php:line>`) -> `<Callee::method()>` (`<file.php:line>`)
  2. <Step-by-step state, math, and DB/hook transitions>
- **Actual vs. Expected Behavior**:
  <Detailed comparison.>
- **Edge-Case & Defect Findings**: `<None | [C-xx], [H-xx]>`
- **Existing PHPUnit Coverage & Gaps**:
  - Covered by: `<tests/Test_File.php::test_method>` (or `None`)
  - Coverage gap: <What assertions or integration paths are missing.>

---

## 6. Verification & Hygiene Summary
- **Read-Only Integrity**: `git status --short`, `git diff`, and `git diff --cached` status (confirming report saved under `.artifacts/matrix-review/`).
- **Static / Syntax Checks**: `php -l` results across touched files.
- **PHPUnit Suite Execution**: Executed vs. unavailable test suites per `TESTING.md`.
