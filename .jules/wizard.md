## 2024-05-18 - Add Copy ID to Admin Tables

Learning: Users frequently need internal entity IDs for manual lookups, support, or advanced configurations. Exposing them with a one-click copy function significantly improves developer and power-user experience without cluttering the primary UI. Reusing the existing clipboard delegate logic via a unified CSS class ensures the change remains small and maintainable.

Action: For future list views or admin tables, always evaluate if exposing the primary key (ID) as a subtle, copyable badge would add value for power users.
