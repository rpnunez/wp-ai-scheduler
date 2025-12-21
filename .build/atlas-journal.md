# Atlas Architectural Decision Records (ADR)

**DO NOT DELETE THIS FILE.**
This document serves as the persistent memory for architectural decisions, structural patterns, and refactoring strategies.

## Usage Rules
1. **APPEND ONLY:** When "Atlas" (the Architect Agent) makes a structural change, a new entry MUST be appended to the bottom of this file. Never overwrite existing history.
2. **Context is King:** Do not just say *what* changed. Explain *why* (the context) and *what trade-offs* were accepted (the consequences).
3. **Reference the Past:** Before making a new decision, read the history below to ensure consistency with previous architectural patterns.

---

## Current System Architecture (Snapshot)
*Update this section only when major architectural shifts occur.*
- **Pattern:** [e.g., Modular Monolith, Microservices, Atomic Design]
- **State Management:** [e.g., Redux, Context API, XState]
- **Key Constraints:** [e.g., "No direct DB access from UI components", "Mobile-first CSS"]

---

## Decision Log

### 2024-01-01 - [Example] Extract Authentication Logic
**Context:** Authentication logic was scattered across `NavBar.js`, `Login.js`, and `apiUtils.js`, creating circular dependencies and making it hard to update token logic.
**Decision:** Applied "Separation of Concerns". Created a dedicated `AuthService` class in `src/services/auth/`. Implemented the "Singleton Pattern" for the token manager.
**Consequence:**
* Centralized logic makes security updates easier.
* Decoupled UI from API logic.
* Trade-off: `AuthService` must now be injected or imported into all protected routes, increasing boilerplate slightly.

### 2024-03-15 - [Example] Split Global Utils
**Context:** `utils.js` reached 4,000 lines. Loading it on the marketing landing page cost 150kb of unused JS execution.
**Decision:** Applied "Code Splitting". Sliced `utils.js` into domain-specific files: `dateUtils.js`, `formattingUtils.js`, and `validationUtils.js`.
**Consequence:**
* Reduced bundle size on landing pages by 40%.
* Easier to test individual domains.
* Trade-off: Required updating import paths in 50+ files (automated via codemod).