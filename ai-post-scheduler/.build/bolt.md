## 2026-05-30 - Memory Cache Repo Aggregates
**Learning:** Repositories calling heavy GROUP BY stats were introducing severe N+1 queries when fetching schedule lists because the lists load dynamically without internal caching in repositories. We fixed this by introducing `AIPS_Cache` memory array layer per request inside the repos.
**Action:** Always wrap heavy GROUP BY or `COUNT(*)` DB aggregate queries in `AIPS_Cache` caching when placing them in Repositories, especially if they map to items that could be loaded in a loop by Controllers or Services.
