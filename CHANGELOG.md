
## [Unreleased]
### Changed
- Admin History: Replaced full page reload with AJAX table reload when retrying failed generations to improve user flow.
- Refactored multiple admin UI actions to update DOM tables dynamically without a full page reload for a smoother user experience.
### Refactored
- Extracted statistical and analytical methods from `AIPS_History_Repository` into a dedicated `AIPS_History_Stats_Repository` to resolve "God Object" anti-pattern and improve separation of concerns, maintaining strict backward compatibility.
