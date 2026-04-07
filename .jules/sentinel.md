## 2026-04-07 - Secure Settings AJAX nonces and error handling
**Vulnerability:** Default die() on nonce failures breaking JSON APIs, and potential leakage of sensitive API error details in test connection endpoint.
**Learning:** Default WordPress check_ajax_referer behavior returns an HTML response on failure, and direct exposure of AI API error messages may leak stack traces or internal mechanics.
**Prevention:** Always pass false as the 3rd parameter to check_ajax_referer and explicitly return a JSON error, and log detailed errors internally while returning generic messages to the client.
