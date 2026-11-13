/**
 * AI Post Scheduler - Frontend Components Script
 * Lightweight interaction enhancement for FAQ accordions and interactive elements.
 */
document.addEventListener('DOMContentLoaded', function() {
	// Optional smooth accordion toggle animation support
	var faqs = document.querySelectorAll('.aips-faq-item');
	if (faqs.length > 0) {
		faqs.forEach(function(faq) {
			faq.addEventListener('toggle', function() {
				if (faq.open) {
					faq.classList.add('is-open');
				} else {
					faq.classList.remove('is-open');
				}
			});
		});
	}
});
