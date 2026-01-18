## 2024-05-24 - [Sensitive Data Visibility]
**Vulnerability:** Displaying API keys (Unsplash Access Key) in plain text inputs within the admin dashboard.
**Learning:** Even if data is stored securely (or insecurely), displaying secrets in plain text `type="text"` fields exposes them to "shoulder surfing" and accidental disclosure via screenshots or screen sharing.
**Prevention:** Always use `type="password"` for input fields containing sensitive data like API keys, secrets, or tokens. Consider adding a "toggle visibility" feature for UX if needed, but default to hidden.
