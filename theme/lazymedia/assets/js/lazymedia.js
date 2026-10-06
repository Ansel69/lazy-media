/*
 * Lazy Media theme behaviour.
 *
 * Everything here is progressive enhancement: navigation, the hero slider, and
 * the copy-link button all work without JavaScript. Nothing here fetches from a
 * server except the static search index.
 */
(function () {
	'use strict';

	/**
	 * Run a callback once the DOM is parsed.
	 *
	 * @param {Function} fn Callback.
	 */
	function ready(fn) {
		if (document.readyState !== 'loading') {
			fn();
		} else {
			document.addEventListener('DOMContentLoaded', fn);
		}
	}

	/* ====================================================================
	 * Mobile navigation
	 * ==================================================================== */

	ready(function () {
		var toggle = document.querySelector('[data-lm-nav-toggle]');
		var nav = document.getElementById('lm-nav');

		if (!toggle || !nav) {
			return;
		}

		/**
		 * Open or close the mobile drawer.
		 *
		 * @param {boolean} open Desired state.
		 */
		function setOpen(open) {
			toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
			nav.classList.toggle('is-open', open);
		}

		toggle.addEventListener('click', function () {
			setOpen(toggle.getAttribute('aria-expanded') !== 'true');
		});

		// The dropdown is hover-only on pointer devices; touch needs a tap.
		nav.addEventListener('click', function (event) {
			var link = event.target.closest('.lm-has-children > .lm-nav__link');

			if (!link) {
				return;
			}

			var item = link.parentElement;

			// Only intercept inside the drawer, where the CSS hides sub-menus.
			if (!nav.classList.contains('is-open')) {
				return;
			}

			if (item.classList.contains('is-open')) {
				item.classList.remove('is-open');
				link.setAttribute('aria-expanded', 'false');
				return;
			}

			event.preventDefault();
			item.classList.add('is-open');
			link.setAttribute('aria-expanded', 'true');
		});

		document.addEventListener('keydown', function (event) {
			if (event.key === 'Escape' && nav.classList.contains('is-open')) {
				setOpen(false);
				toggle.focus();
			}
		});

		// Keep --lm-header-offset in sync so the drawer and sticky header agree.
		var header = document.querySelector('[data-lm-header]');

		/**
		 * Publish the header height as a custom property.
		 */
		function syncOffset() {
			if (!header) {
				return;
			}

			document.documentElement.style.setProperty(
				'--lm-header-offset',
				header.offsetHeight + 'px'
			);
		}

		syncOffset();
		window.addEventListener('resize', syncOffset);
	});

	/* ====================================================================
	 * Hero slider
	 * ==================================================================== */

	ready(function () {
		var track = document.querySelector('[data-lm-hero-track]');

		if (!track) {
			return;
		}

		var slides = Array.prototype.slice.call(track.querySelectorAll('.lm-hero__slide'));
		var dots = Array.prototype.slice.call(document.querySelectorAll('[data-lm-hero-dot]'));

		if (slides.length < 2) {
			return;
		}

		var current = 0;
		var timer = null;
		var DELAY = 6000;

		/**
		 * Show one slide and move the active dot.
		 *
		 * @param {number} index Slide index.
		 */
		function goTo(index) {
			current = (index + slides.length) % slides.length;

			slides.forEach(function (slide, i) {
				slide.classList.toggle('is-active', i === current);
			});

			dots.forEach(function (dot, i) {
				dot.classList.toggle('is-active', i === current);
				dot.setAttribute('aria-selected', i === current ? 'true' : 'false');
			});
		}

		/**
		 * Restart the auto-advance timer.
		 */
		function restart() {
			if (timer) {
				window.clearInterval(timer);
			}

			timer = window.setInterval(function () {
				goTo(current + 1);
			}, DELAY);
		}

		goTo(0);

		dots.forEach(function (dot, i) {
			dot.addEventListener('click', function () {
				goTo(i);
				restart();
			});
		});

		var hero = document.querySelector('[data-lm-hero]');

		if (hero) {
			hero.addEventListener('mouseenter', function () {
				if (timer) {
					window.clearInterval(timer);
					timer = null;
				}
			});

			hero.addEventListener('mouseleave', restart);
		}

		// Do not animate a tab nobody is looking at.
		document.addEventListener('visibilitychange', function () {
			if (document.hidden) {
				if (timer) {
					window.clearInterval(timer);
					timer = null;
				}
			} else {
				restart();
			}
		});
	});

	/* ====================================================================
	 * Copy link
	 * ==================================================================== */

	ready(function () {
		var buttons = document.querySelectorAll('[data-lm-copy-link]');

		Array.prototype.forEach.call(buttons, function (button) {
			button.addEventListener('click', function () {
				var url = button.getAttribute('data-lm-copy-link');
				var label = button.textContent;

				function done(text) {
					button.textContent = text;
					window.setTimeout(function () {
						button.textContent = label;
					}, 1800);
				}

				if (navigator.clipboard && navigator.clipboard.writeText) {
					navigator.clipboard.writeText(url).then(
						function () {
							done('Tersalin');
						},
						function () {
							done('Gagal');
						}
					);
					return;
				}

				// Fallback for browsers without the async clipboard API.
				var field = document.createElement('textarea');

				field.value = url;
				field.setAttribute('readonly', '');
				field.style.position = 'absolute';
				field.style.left = '-9999px';
				document.body.appendChild(field);
				field.select();

				try {
					document.execCommand('copy');
					done('Tersalin');
				} catch (error) {
					done('Gagal');
				}

				document.body.removeChild(field);
			});
		});
	});

	/* ====================================================================
	 * Reading progress
	 *
	 * Measured against the article body rather than the whole page, so the
	 * bar is full when the reader reaches the end of the text, not the end
	 * of the related posts and footer.
	 * ==================================================================== */

	ready(function () {
		var progress = document.querySelector('[data-lm-progress]');
		var body = document.querySelector('[data-lm-article-body]');

		if (!progress || !body) {
			return;
		}

		var queued = false;

		/**
		 * Publish how much of the body has scrolled past the viewport bottom.
		 */
		function update() {
			queued = false;

			var rect = body.getBoundingClientRect();
			var ratio = rect.height > 0 ? (window.innerHeight - rect.top) / rect.height : 1;

			ratio = Math.min(1, Math.max(0, ratio));
			progress.style.setProperty('--lm-progress', ratio.toFixed(4));
		}

		/**
		 * Coalesce scroll events into one update per frame.
		 */
		function queue() {
			if (!queued) {
				queued = true;
				window.requestAnimationFrame(update);
			}
		}

		window.addEventListener('scroll', queue, { passive: true });
		window.addEventListener('resize', queue);
		update();
	});

	/* ====================================================================
	 * Table of contents scroll-spy
	 * ==================================================================== */

	ready(function () {
		var toc = document.querySelector('[data-lm-toc]');

		if (!toc || !('IntersectionObserver' in window)) {
			return;
		}

		var links = Array.prototype.slice.call(toc.querySelectorAll('.lm-toc__link'));
		var byId = {};
		var headings = [];

		links.forEach(function (link) {
			var id = decodeURIComponent(link.getAttribute('href').slice(1));
			var heading = document.getElementById(id);

			if (heading) {
				byId[id] = link;
				headings.push(heading);
			}
		});

		if (!headings.length) {
			return;
		}

		var visible = {};

		/**
		 * Mark the first heading currently in the reading zone as active.
		 */
		function activate() {
			var current = null;

			for (var i = 0; i < headings.length; i++) {
				if (visible[headings[i].id]) {
					current = headings[i].id;
					break;
				}
			}

			if (!current) {
				return;
			}

			links.forEach(function (link) {
				var on = link === byId[current];

				link.classList.toggle('is-active', on);

				if (on) {
					link.setAttribute('aria-current', 'location');
				} else {
					link.removeAttribute('aria-current');
				}
			});
		}

		var observer = new IntersectionObserver(
			function (entries) {
				entries.forEach(function (entry) {
					visible[entry.target.id] = entry.isIntersecting;
				});
				activate();
			},
			// The reading zone: below the sticky header, top 40% of the screen.
			{ rootMargin: '-15% 0px -60% 0px' }
		);

		headings.forEach(function (heading) {
			observer.observe(heading);
		});
	});

	/* ====================================================================
	 * Search form guard
	 *
	 * On the static export there is no PHP handler, so submit the query in the
	 * hash-free form the client search reads instead.
	 * ==================================================================== */

	ready(function () {
		var forms = document.querySelectorAll('[data-lm-searchform]');

		Array.prototype.forEach.call(forms, function (form) {
			form.addEventListener('submit', function (event) {
				// Let the server handle it when one exists.
				if (!document.body.hasAttribute('data-lm-static')) {
					return;
				}

				event.preventDefault();

				var input = form.querySelector('.lm-searchform__input');
				var term = input ? input.value.trim() : '';

				window.location.href = 'search/' + encodeURIComponent(term) + '/';
			});
		});
	});
})();