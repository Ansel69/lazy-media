/*
 * Client-side search.
 *
 * The deployed site is static, so /search/<term>/ has no PHP behind it. This
 * rebuilds the results grid from search-index.json, a file the exporter writes
 * at build time. Markup and classes match lazymedia_card() exactly so a result
 * looks identical to a server-rendered one.
 */
(function () {
	'use strict';

	var container = document.querySelector('[data-lm-search-results]');

	if (!container) {
		return;
	}

	var status = document.querySelector('[data-lm-search-status]');
	var root = getSiteRoot();
	var index = null;
	var loading = false;

	// The exporter rewrites this to a root-relative path, so it stays correct
	// on /search/, on /search/<term>/, and on any page the script is loaded on.
	var data = window.lazymediaData || {};
	var indexUrl = data.searchIndex || root + 'search-index.json';

	/**
	 * Root the site is served from, with a trailing slash.
	 *
	 * The search page lives at <root>/search/, so paths are built from the root
	 * rather than from the current directory. Resolving against the current
	 * directory turns a search submitted from /search/ into
	 * /search/search/<term>/, and /search/ into /search/search-index.json.
	 *
	 * @return {string} Site root.
	 */
	function getSiteRoot() {
		var link = document.querySelector('link[rel="canonical"]');
		var url = link ? link.href : window.location.href;

		try {
			return new URL(url).origin + '/';
		} catch (error) {
			return '/';
		}
	}

	/**
	 * Escape text before it goes into innerHTML.
	 *
	 * @param {string} value Raw string.
	 * @return {string} Safe string.
	 */
	function esc(value) {
		return String(value === null || value === undefined ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	/**
	 * Fetch and cache the index.
	 *
	 * @return {Promise} Resolves with the index, or an empty list on failure.
	 */
	function loadIndex() {
		if (index) {
			return Promise.resolve(index);
		}

		if (loading) {
			return new Promise(function (resolve) {
				window.setTimeout(function () {
					loadIndex().then(resolve);
				}, 60);
			});
		}

		loading = true;

		return fetch(indexUrl, { credentials: 'same-origin' })
			.then(function (response) {
				if (!response.ok) {
					throw new Error('HTTP ' + response.status);
				}

				return response.json();
			})
			.then(function (data) {
				index = Array.isArray(data) ? data : data.posts || [];
				loading = false;
				return index;
			})
			.catch(function () {
				loading = false;
				index = [];
				return index;
			});
	}

	/**
	 * Score one entry against the query terms.
	 *
	 * Title matches outweigh excerpt and category matches, and a prefix match
	 * beats a word that only appears mid-string, so typing "gam" surfaces
	 * "Game" before an article that merely mentions gaming in its body.
	 *
	 * @param {object} entry  Index entry.
	 * @param {string[]} terms Lowercased query terms.
	 * @return {number} Score, 0 meaning no match.
	 */
	function score(entry, terms) {
		var title = (entry.title || '').toLowerCase();
		var excerpt = (entry.excerpt || '').toLowerCase();
		var category = (entry.category || '').toLowerCase();
		var tags = (entry.tags || []).join(' ').toLowerCase();
		var total = 0;

		terms.forEach(function (term) {
			if (title.indexOf(term) === 0) {
				total += 12;
			} else if (title.indexOf(term) !== -1) {
				total += 8;
			}

			if (category.indexOf(term) !== -1) {
				total += 4;
			}

			if (tags.indexOf(term) !== -1) {
				total += 3;
			}

			if (excerpt.indexOf(term) !== -1) {
				total += 1;
			}
		});

		return total;
	}

	/**
	 * One result card, matching lazymedia_card() markup.
	 *
	 * @param {object} entry Index entry.
	 * @return {string} HTML.
	 */
	function renderCard(entry) {
		var meta = [];

		if (entry.date) {
			meta.push('<time datetime="' + esc(entry.date) + '">' + esc(entry.dateLabel || entry.date) + '</time>');
		}

		if (entry.readTime) {
			meta.push('<span class="lm-card__dot" aria-hidden="true"></span>');
			meta.push('<span class="lm-card__time">' + esc(entry.readTime) + ' menit baca</span>');
		}

		var badge = entry.category
			? '<span class="lm-card__badge">' + esc(entry.category) + '</span>'
			: '';

		var play = entry.isVideo
			? '<span class="lm-card__play" aria-label="Video"><svg viewBox="0 0 24 24" width="14" height="14" aria-hidden="true" focusable="false"><path d="M8 5.5v13l11-6.5-11-6.5Z" fill="currentColor"></path></svg></span>'
			: '';

		var image = entry.thumbnail
			? '<img src="' + esc(entry.thumbnail) + '" alt="" loading="lazy" decoding="async" width="600" height="338">'
			: '';

		return (
			'<article class="lm-card lm-card--default lm-card--excerpt" data-lm-card>' +
			'<a class="lm-card__thumb" href="' + esc(entry.url) + '" tabindex="-1" aria-hidden="true">' +
			image +
			badge +
			play +
			'</a>' +
			'<div class="lm-card__body">' +
			'<h3 class="lm-card__title"><a href="' + esc(entry.url) + '">' + esc(entry.title) + '</a></h3>' +
			'<p class="lm-card__excerpt">' + esc(entry.excerpt || '') + '</p>' +
			'<div class="lm-card__meta">' + meta.join('') + '</div>' +
			'</div>' +
			'</article>'
		);
	}

	/**
	 * Run a search and paint the results.
	 *
	 * @param {string} term Raw query.
	 */
	function run(term) {
		var query = String(term || '').trim();

		if (query.length < 2) {
			container.innerHTML = '';
			status.textContent = query ? 'Ketik minimal 2 huruf.' : '';
			return;
		}

		if (status) {
			status.textContent = 'Mencari…';
		}

		loadIndex().then(function (posts) {
			var terms = query.toLowerCase().split(/\s+/).filter(Boolean);

			var hits = posts
				.map(function (entry) {
					return { entry: entry, weight: score(entry, terms) };
				})
				.filter(function (hit) {
					return hit.weight > 0;
				})
				.sort(function (a, b) {
					if (b.weight !== a.weight) {
						return b.weight - a.weight;
					}

					return String(b.entry.date || '').localeCompare(String(a.entry.date || ''));
				})
				.map(function (hit) {
					return hit.entry;
				});

			if (!hits.length) {
				container.innerHTML =
					'<p class="lm-empty">Tidak ada artikel yang cocok. Coba kata kunci lain.</p>';

				if (status) {
					status.textContent = 'Tidak ada artikel yang cocok untuk "' + query + '".';
				}

				return;
			}

			container.innerHTML = hits.map(renderCard).join('');

			if (status) {
				status.textContent = hits.length + ' artikel ditemukan untuk "' + query + '".';
			}
		});
	}

	/**
	 * Read the term from the URL, supporting /search/<term>/.
	 *
	 * @return {string} Search term.
	 */
	function termFromUrl() {
		var fromQuery = new URLSearchParams(window.location.search).get('s');

		if (fromQuery) {
			return fromQuery;
		}

		var match = window.location.pathname.match(/\/search\/([^/]+)\/?$/);

		return match ? decodeURIComponent(match[1].replace(/\+/g, ' ')) : '';
	}

	// Any search form on the page drives the same handler.
	document.querySelectorAll('[data-lm-searchform]').forEach(function (form) {
		form.addEventListener('submit', function (event) {
			event.preventDefault();

			var input = form.querySelector('.lm-searchform__input');
			var term = input ? input.value.trim() : '';

			// Built from the site root so submitting from /search/ or
			// /search/<term>/ never produces a doubled path.
			if (window.history && window.history.replaceState) {
				window.history.replaceState({}, '', root + 'search/' + encodeURIComponent(term) + '/');
			}

			run(term);
		});
	});

	var initial = termFromUrl();

	if (initial) {
		var field = document.querySelector('.lm-searchform__input');

		if (field) {
			field.value = initial;
		}

		run(initial);
	} else if (status) {
		status.textContent = 'Ketik kata kunci untuk mencari artikel.';
	}
})();