## 2024-05-23 - Clipboard Interaction in Browsers
**Learning:** `navigator.clipboard.writeText` is the modern standard but requires a secure context (HTTPS) and user interaction. For older browsers or non-secure contexts (often local dev), a fallback using `document.execCommand('copy')` with a temporary textarea is necessary.
**Action:** When implementing "Copy to Clipboard" features, always wrap the modern API in a feature check and provide the textarea/execCommand fallback to ensure functionality across all environments.
