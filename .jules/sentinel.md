## 2024-05-23 - [XSS Prevention]
**Vulnerability:** Unescaped output of `get_permalink()` and `get_edit_post_link()` in `href` attributes.
**Learning:** Even trusted WordPress functions that return URLs should be escaped with `esc_url()` when outputting to HTML attributes to prevent potential XSS if the URL is manipulated via filters.
**Prevention:** Always use `esc_url()` for URL outputs in HTML attributes.
