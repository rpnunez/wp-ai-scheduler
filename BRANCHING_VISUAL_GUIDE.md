# Branching Strategy Visual Guide

This document provides visual diagrams and quick reference for the branching strategy.

## Branch Structure Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                         MAIN BRANCH                              │
│  Production-Ready | Stable | Protected | Releases Only           │
│                                                                   │
│  v1.7.0 ──────────────── v1.8.0 ──────────────── v1.9.0         │
└─────────────┬─────────────────┬───────────────────┬─────────────┘
              │                 │                   │
              │  Release PRs    │   Release PRs     │
              │  (Tested)       │   (Tested)        │
              │                 │                   │
┌─────────────┴─────────────────┴───────────────────┴─────────────┐
│                         DEV BRANCH                                │
│  Active Development | Integration | Protected | Features Merge    │
│                                                                   │
│  ●─────●─────●─────●─────●─────●─────●─────●─────●─────●       │
└──┬──────┬─────────┬──────┬────────────────────────────┬─────────┘
   │      │         │      │                            │
   │      │         │      │                            │
Feature  Feature  Feature  │                          Feature
Branch   Branch   Branch   │                          Branch
   A        B        C     │                             N
   ↓        ↓        ↓     │                             ↓
  ●─●      ●─●      ●─●    │                            ●─●
(work)   (work)   (work)   │                          (work)
                           │
                           └── Hotfix (also goes to main)
```

## Feature Development Flow

```
┌──────────────────────────────────────────────────────────────────┐
│                      FEATURE WORKFLOW                             │
└──────────────────────────────────────────────────────────────────┘

1. CREATE BRANCH FROM DEV
   
   dev ───●───●───●───●
           ↓
           └──●  feature/new-feature
              │
              
2. DEVELOP & COMMIT

   dev ───●───●───●───●
           ↓
           └──●───●───●  feature/new-feature
                  ↑   ↑
              (work) (work)

3. KEEP UPDATED WITH DEV

   dev ───●───●───●───●───●───●
           ↓           ↗       
           └──●───●───●───●  feature/new-feature
                  ↑   ↑   ↑
              (work) (merge dev) (work)

4. CREATE PR TO DEV

   dev ───●───●───●───●───●───●
           ↓           ↗       ↑
           └──●───●───●───●────┘ PR #123
                  (Feature Ready)

5. MERGE TO DEV

   dev ───●───●───●───●───●───●───⦿
                                  ↑
                           (Feature merged)

6. EVENTUALLY RELEASE TO MAIN

   main ──●───────────────────────⦿  (via release PR)
                                  ↑
                           (v1.8.0 with feature)
```

## Release Flow

```
┌──────────────────────────────────────────────────────────────────┐
│                      RELEASE WORKFLOW                             │
└──────────────────────────────────────────────────────────────────┘

1. ACCUMULATE FEATURES IN DEV

   dev ───●───●───●───●───●───●───●
         feat  feat  fix  feat  fix
          A     B         C

2. WHEN READY, CREATE RELEASE PR

   main ──●───────────────────────────
           ↑                          ↑
           │      (Testing)           │
           │                          │
   dev ────●───●───●───●───●───●─────┘ PR: "release v1.8.0"
          feat  feat  fix  feat  fix
           A     B         C

3. REVIEW, TEST, APPROVE

   ┌─────────────────────────────┐
   │   RELEASE PR CHECKLIST      │
   │ ☑ All tests pass            │
   │ ☑ Manual testing done       │
   │ ☑ Docs updated              │
   │ ☑ Changelog updated         │
   │ ☑ 2+ approvals              │
   └─────────────────────────────┘

4. MERGE TO MAIN

   main ──●──────────────────────⦿  v1.8.0
                                 ↑
                        (All features released)
   
   dev ────●───●───●───●───●───●
          feat  feat  fix  feat  fix

5. TAG RELEASE

   main ──●──────────────────────⦿ ← v1.8.0 tag
```

## Hotfix Flow

```
┌──────────────────────────────────────────────────────────────────┐
│                       HOTFIX WORKFLOW                             │
└──────────────────────────────────────────────────────────────────┘

1. CRITICAL BUG IN PRODUCTION

   main ──●───────────────⦿  v1.8.0
                          ↑
                    (Bug discovered!)

2. CREATE HOTFIX FROM MAIN

   main ──●───────────────⦿  v1.8.0
                          ↓
                          └──● hotfix/critical-fix
                             │
                             ● (fix)

3. CREATE TWO PRs

   main ──●───────────────⦿ ← PR #1: hotfix → main (URGENT)
                          ↓
                          └──●───●
                                 ↓
   dev ────●───●───●───●─────────┘ PR #2: hotfix → dev (sync)

4. MERGE TO MAIN FIRST

   main ──●───────────────⦿───⦿  v1.8.1 (hotfix)
                              ↑
                          (Fix deployed)

5. BACKPORT TO DEV

   dev ────●───●───●───●───⦿
                           ↑
                    (Same fix in dev)
```

## Branch Protection Comparison

```
┌──────────────────────────────────────────────────────────────────┐
│                    BRANCH PROTECTION RULES                        │
└──────────────────────────────────────────────────────────────────┘

╔═══════════════════════╤══════════════╤══════════════════════╗
║ Rule                  │ main         │ dev                  ║
╠═══════════════════════╪══════════════╪══════════════════════╣
║ Require PR            │ ✅ Yes       │ ✅ Yes               ║
║ Required Approvals    │ 2+           │ 1+                   ║
║ Dismiss Stale Reviews │ ✅ Yes       │ ✅ Yes               ║
║ Require Status Checks │ ✅ All tests │ ✅ Basic tests       ║
║ Must be Up-to-Date    │ ✅ Yes       │ ⚠️  Optional        ║
║ Require Conversations │ ✅ Yes       │ ✅ Yes               ║
║ Include Admins        │ ✅ Yes       │ ❌ No (flexibility)  ║
║ Restrict Push         │ ✅ Strict    │ ⚠️  Team only       ║
║ Allow Force Push      │ ❌ No        │ ✅ Maintainers only  ║
║ Allow Deletions       │ ❌ No        │ ❌ No                ║
╚═══════════════════════╧══════════════╧══════════════════════╝
```

## Workflow Automation

```
┌──────────────────────────────────────────────────────────────────┐
│                    AUTOMATED WORKFLOWS                            │
└──────────────────────────────────────────────────────────────────┘

WEEKLY AUTOMATIONS:
│
├─ Monday 00:00 UTC: Feature Agent
│  ├─ Scans codebase
│  ├─ Generates feature-report.md
│  └─ Creates PR → dev (if changes)
│
└─ Sunday 00:00 UTC: Feature Analysis
   ├─ Generates comprehensive analysis
   ├─ Creates dated folder
   └─ Creates PR → dev (if changes)

ON-DEMAND WORKFLOWS:
│
├─ Create Release PR
│  ├─ Input: version, notes
│  ├─ Creates PR: dev → main
│  └─ Adds release checklist
│
└─ Sync Branches
   ├─ Syncs dev with main
   ├─ Fast-forward if possible
   └─ Creates PR if conflicts

CONTINUOUS:
│
├─ CI/CD on Push/PR
│  ├─ Runs PHPUnit tests
│  ├─ Code quality checks
│  └─ Generates reports
│
└─ Branch Protection
   ├─ Enforces reviews
   ├─ Requires tests
   └─ Prevents direct pushes
```

## Decision Tree: Which Branch?

```
                    START: I want to...
                            │
        ┌───────────────────┼───────────────────┐
        │                   │                   │
    Add a new          Fix a bug          Critical
    feature?           (non-urgent)?      production bug?
        │                   │                   │
        ↓                   ↓                   ↓
    ┌──────┐            ┌──────┐           ┌──────┐
    │ dev  │            │ dev  │           │ main │
    └──────┘            └──────┘           └──────┘
        │                   │                   │
        ↓                   ↓                   ↓
 feature/name           fix/description    hotfix/critical
        │                   │                   │
        ↓                   ↓                   ↓
    PR → dev            PR → dev          PR → main (urgent)
                                          PR → dev (sync)


                    CREATE RELEASE?
                            │
                            ↓
                    Use workflow:
                    release-pr.yml
                            │
                            ↓
                    PR: dev → main
                            │
                            ↓
                    Review & Test
                            │
                            ↓
                    Merge & Tag
```

## Quick Reference Card

```
╔════════════════════════════════════════════════════════════════╗
║                    QUICK REFERENCE                             ║
╠════════════════════════════════════════════════════════════════╣
║                                                                ║
║  BRANCH PURPOSE:                                               ║
║  • main  = Production (stable, tested, released)              ║
║  • dev   = Development (active, integration, features)        ║
║                                                                ║
║  TARGET FOR YOUR PR:                                           ║
║  • New feature      → dev                                     ║
║  • Bug fix          → dev                                     ║
║  • Refactoring      → dev                                     ║
║  • Documentation    → dev                                     ║
║  • Critical hotfix  → main (then backport to dev)            ║
║                                                                ║
║  CREATE BRANCH FROM:                                           ║
║  • Features  → dev                                            ║
║  • Hotfixes  → main                                           ║
║                                                                ║
║  WORKFLOWS:                                                    ║
║  • gh workflow run release-pr.yml    (create release)         ║
║  • gh workflow run sync-branches.yml (sync after hotfix)      ║
║                                                                ║
║  KEEP UPDATED:                                                 ║
║  • git merge dev     (update feature with latest dev)         ║
║  • git rebase dev    (cleaner history, use carefully)         ║
║                                                                ║
╚════════════════════════════════════════════════════════════════╝
```

## Timeline Example

```
Week 1: Active Development
─────────────────────────────
Day 1: Feature A starts (PR #101 → dev)
Day 2: Feature B starts (PR #102 → dev)
Day 3: Feature A merges to dev
Day 4: Bug fix (PR #103 → dev)
Day 5: Feature B merges to dev
Day 6: Feature C starts (PR #104 → dev)
Day 7: Bug fix merges to dev

Week 2: Testing & Release
──────────────────────────
Day 1: Feature C continues
Day 2: QA testing on dev
Day 3: Feature C merges to dev
Day 4: Final testing on dev
Day 5: Create release PR (dev → main)
Day 6: Review & approve release PR
Day 7: Merge to main, tag v1.8.0

Week 3: Hotfix Example
──────────────────────
Day 1: Critical bug found in v1.8.0
Day 2: Hotfix branch created from main
Day 3: Fix developed and tested
Day 4: PR to main (urgent review)
Day 5: Merge to main, tag v1.8.1
Day 6: Backport to dev
Day 7: Resume normal development
```

## Documentation Map

```
START HERE: README.md
│
├─ Want to contribute?
│  └─ CONTRIBUTING.md ────→ Development guidelines
│                           ├─ BRANCHING_STRATEGY.md (this guide)
│                           └─ TESTING.md
│
├─ Have an existing PR?
│  └─ PR_MIGRATION_GUIDE.md ─→ How to retarget
│
├─ Are you a maintainer?
│  └─ BRANCH_MANAGEMENT.md ──→ Release procedures
│     └─ DEV_BRANCH_SETUP.md → Initial setup
│
└─ Want an overview?
   └─ BRANCHING_IMPLEMENTATION_SUMMARY.md
```

## Color Legend

```
● = Commit
⦿ = Release/Merge
─ = Branch timeline
↓ = Fork/branch
↑ = Merge
↗ = Pull from
```

## Resources

- [BRANCHING_STRATEGY.md](./BRANCHING_STRATEGY.md) - Complete strategy
- [CONTRIBUTING.md](./CONTRIBUTING.md) - Contribution guide
- [BRANCH_MANAGEMENT.md](./BRANCH_MANAGEMENT.md) - For maintainers
- [PR_MIGRATION_GUIDE.md](./PR_MIGRATION_GUIDE.md) - Update existing PRs

---

**Visual Guide Version**: 1.0  
**Last Updated**: 2026-02-10  
**Part of**: Branching Strategy Implementation
