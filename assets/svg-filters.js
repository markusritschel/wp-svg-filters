/**
 * SVG Filter Kit — interactive behaviour.
 *
 * - Pauses all SMIL filter animations under prefers-reduced-motion
 *   and while the tab is hidden.
 * - Enables refractive glass (.glass--liquid) in Chromium.
 * - .fx-spotlight: moves the point light to the pointer.
 * - .fx-melt: eases the displacement on hover (tap on touch screens).
 *
 * Note: .fx-spotlight and .fx-melt share one filter per page, so all elements
 * using them react together. Duplicate the SVG file with a new ID for
 * independent instances.
 */
(function () {
	'use strict';

	var defs = document.getElementById('svgf-defs');
	var reduceQuery = window.matchMedia('(prefers-reduced-motion: reduce)');

	/* Motion control */
	function syncAnimations() {
		if (!defs || !defs.pauseAnimations) return;
		if (reduceQuery.matches || document.hidden) defs.pauseAnimations();
		else defs.unpauseAnimations();
	}
	syncAnimations();
	document.addEventListener('visibilitychange', syncAnimations);
	if (reduceQuery.addEventListener) reduceQuery.addEventListener('change', syncAnimations);

	/* Refractive glass support */
	var brands = (navigator.userAgentData && navigator.userAgentData.brands) || [];
	if (brands.some(function (b) { return /Chromium/i.test(b.brand); })) {
		document.documentElement.classList.add('svgf-backdrop-url');
	}

	/* Pointer light */
	var light = document.getElementById('fx-spot-light');
	if (light) {
		document.querySelectorAll('.fx-spotlight, .fx-spotlight--bg').forEach(function (el) {
			el.addEventListener('pointermove', function (e) {
				var r = el.getBoundingClientRect();
				light.setAttribute('x', (e.clientX - r.left).toFixed(1));
				light.setAttribute('y', (e.clientY - r.top).toFixed(1));
			});
		});
	}

	/* Hover melt */
	var map = document.getElementById('fx-melt-map');
	if (map) {
		var cur = 0, target = 0, raf = 0, MAX = 45;
		var step = function () {
			cur += (target - cur) * 0.12;
			if (Math.abs(target - cur) < 0.2) cur = target;
			map.setAttribute('scale', cur.toFixed(1));
			if (cur !== target) raf = requestAnimationFrame(step);
		};
		var go = function (v) {
			target = v;
			cancelAnimationFrame(raf);
			if (reduceQuery.matches) { cur = v; map.setAttribute('scale', v); }
			else step();
		};
		document.querySelectorAll('.fx-melt, .fx-melt--bg').forEach(function (el) {
			el.addEventListener('pointerenter', function (e) { if (e.pointerType === 'mouse') go(MAX); });
			el.addEventListener('pointerleave', function (e) { if (e.pointerType === 'mouse') go(0); });
			el.addEventListener('pointerdown', function (e) { if (e.pointerType !== 'mouse') go(target ? 0 : MAX); });
		});
	}
})();
