(() => {
	const prefersReducedMotion = window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;

	const header = document.querySelector(".site-header");
	const menuToggle = document.querySelector("[data-menu-toggle]");
	const nav = document.querySelector("[data-nav]");
	const progressBar = document.querySelector("[data-progress]");

	const clamp = (value, min, max) => Math.max(min, Math.min(max, value));

	const setProgress = () => {
		if (!progressBar) return;
		const max = document.documentElement.scrollHeight - window.innerHeight;
		const progress = max > 0 ? (window.scrollY / max) * 100 : 0;
		progressBar.style.setProperty("--progress", `${clamp(progress, 0, 100)}%`);
	};

	const setHeroParallax = () => {
		if (prefersReducedMotion) return;
		const hero = document.querySelector(".hero");
		if (!hero) return;
		const rect = hero.getBoundingClientRect();
		const visible = rect.top < window.innerHeight && rect.bottom > 0;
		if (!visible) return;
		const offset = (window.scrollY * 0.06) * -1;
		hero.style.setProperty("--hero-y", `${offset}px`);
	};

	const closeMenu = () => {
		if (!header) return;
		header.classList.remove("is-open");
		if (menuToggle) menuToggle.setAttribute("aria-expanded", "false");
	};

	if (menuToggle) {
		menuToggle.addEventListener("click", () => {
			const isOpen = header.classList.toggle("is-open");
			menuToggle.setAttribute("aria-expanded", String(isOpen));
		});
	}

	if (nav) {
		nav.addEventListener("click", (event) => {
			const link = event.target.closest("a[href^=\"#\"]");
			if (!link) return;
			closeMenu();
		});
	}

	document.addEventListener("keydown", (event) => {
		if (event.key === "Escape") closeMenu();
	});

	const smoothScrollWithOffset = (targetId) => {
		const target = document.getElementById(targetId);
		if (!target) return;
		const headerH = header ? header.getBoundingClientRect().height : 0;
		const y = target.getBoundingClientRect().top + window.scrollY - headerH - 12;
		window.scrollTo({ top: y, behavior: prefersReducedMotion ? "auto" : "smooth" });
	};

	document.addEventListener("click", (event) => {
		const anchor = event.target.closest("a[href^=\"#\"]");
		if (!anchor) return;
		const href = anchor.getAttribute("href");
		if (!href || href === "#") return;

		const targetId = href.slice(1);
		if (!targetId) return;

		if (document.getElementById(targetId)) {
			event.preventDefault();
			smoothScrollWithOffset(targetId);
			history.pushState(null, "", href);
		}
	});

	const revealEls = Array.from(document.querySelectorAll(".reveal"));
	if (!prefersReducedMotion && revealEls.length) {
		const io = new IntersectionObserver(
			(entries) => {
				for (const entry of entries) {
					if (!entry.isIntersecting) continue;
					entry.target.classList.add("is-visible");
					io.unobserve(entry.target);
				}
			},
			{ threshold: 0.15, rootMargin: "40px 0px -10% 0px" }
		);
		revealEls.forEach((el) => io.observe(el));
	} else {
		revealEls.forEach((el) => el.classList.add("is-visible"));
	}

	const sections = Array.from(document.querySelectorAll("section[id]"));
	const navLinks = Array.from(document.querySelectorAll("[data-nav] a[href^=\"#\"]"));

	const setActiveNav = (id) => {
		for (const link of navLinks) {
			const isActive = link.getAttribute("href") === `#${id}`;
			if (isActive) link.setAttribute("aria-current", "page");
			else link.removeAttribute("aria-current");
		}
	};

	if (sections.length && navLinks.length) {
		const sectionIO = new IntersectionObserver(
			(entries) => {
				const visible = entries
					.filter((e) => e.isIntersecting)
					.sort((a, b) => (b.intersectionRatio || 0) - (a.intersectionRatio || 0));
				if (!visible.length) return;
				setActiveNav(visible[0].target.id);
			},
			{
				threshold: [0.2, 0.35, 0.5],
				rootMargin: `-${Math.round(parseFloat(getComputedStyle(document.documentElement).getPropertyValue("--header-h")))}px 0px -55% 0px`
			}
		);
		sections.forEach((s) => sectionIO.observe(s));
	}

	const animateCount = (el) => {
		const target = parseFloat(el.getAttribute("data-count") || "0");
		const suffix = el.getAttribute("data-suffix") || "";
		const prefix = el.getAttribute("data-prefix") || "";
		const decimals = parseInt(el.getAttribute("data-decimals") || "0", 10);
		const duration = prefersReducedMotion ? 0 : 900;

		const start = performance.now();
		const startValue = 0;
		const step = (now) => {
			const t = duration === 0 ? 1 : clamp((now - start) / duration, 0, 1);
			const eased = 1 - Math.pow(1 - t, 3);
			const value = startValue + (target - startValue) * eased;
			el.textContent = `${prefix}${value.toFixed(decimals)}${suffix}`;
			if (t < 1) requestAnimationFrame(step);
		};
		requestAnimationFrame(step);
	};

	const countEls = Array.from(document.querySelectorAll("[data-count]"));
	if (countEls.length) {
		const countIO = new IntersectionObserver(
			(entries) => {
				for (const entry of entries) {
					if (!entry.isIntersecting) continue;
					animateCount(entry.target);
					countIO.unobserve(entry.target);
				}
			},
			{ threshold: 0.4 }
		);
		countEls.forEach((el) => countIO.observe(el));
	}

	const pricingToggle = document.querySelector("[data-pricing-toggle]");
	const priceEls = Array.from(document.querySelectorAll("[data-price-monthly]"));
	const pricingNote = document.querySelector("[data-pricing-note]");
	const updatePrices = () => {
		const annual = pricingToggle && pricingToggle.checked;
		for (const el of priceEls) {
			const monthly = el.getAttribute("data-price-monthly");
			const yearly = el.getAttribute("data-price-annual");
			el.textContent = annual ? yearly : monthly;
		}
		if (pricingNote) pricingNote.textContent = annual ? "Billed annually (2 months free)." : "Billed monthly. Cancel anytime.";
	};
	if (pricingToggle) {
		pricingToggle.addEventListener("change", updatePrices);
		updatePrices();
	}

	const faqItems = Array.from(document.querySelectorAll("[data-faq-item]"));
	faqItems.forEach((item) => {
		const btn = item.querySelector("button");
		if (!btn) return;
		btn.addEventListener("click", () => {
			const expanded = item.getAttribute("aria-expanded") === "true";
			for (const other of faqItems) other.setAttribute("aria-expanded", "false");
			item.setAttribute("aria-expanded", expanded ? "false" : "true");
		});
	});

	const onScroll = () => {
		setProgress();
		setHeroParallax();
	};
	window.addEventListener("scroll", onScroll, { passive: true });
	window.addEventListener("resize", onScroll);
	onScroll();
})();
