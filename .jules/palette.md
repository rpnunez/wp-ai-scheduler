## 2024-05-23 - Accessibility of Admin Filter Dropdowns
**Learning:** Filter dropdowns (select elements) in admin toolbars often lack visible labels for design compactness, but frequently miss `aria-label` attributes, making them inaccessible to screen readers.
**Action:** When creating or auditing filter bars, ensure every `<select>` element has a descriptive `aria-label` (e.g., "Filter by Status") if no visible `<label>` is present.
