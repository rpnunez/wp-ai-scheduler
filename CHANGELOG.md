
### Changed
- Admin History: Replaced full page reload with AJAX table reload when retrying failed generations to improve user flow.
- Optimized bulk scheduling flow in the Planner to correctly stagger `next_run` datetimes based on the selected interval.
- When scheduling multiple topics with a frequency of "Once", the planner will now default to staggering the topics daily to prevent simultaneous server/API load.
