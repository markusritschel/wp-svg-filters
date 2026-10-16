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
 * using them react together. For independent instances, give each element its
 * own copy of the filter and name it in data-svgf-spotlight="<filter id>" or
 * data-svgf-melt="<filter id>"; the light / displacement map is looked up
 * inside that filter. Events are delegated, so elements added later work too.
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

	/* Element under the pointer that uses an interactive filter, and the primitive it drives. */
	function hit(target, kind, sharedId, tag) {
		var el = target && target.closest && target.closest('.fx-' + kind + ', .fx-' + kind + '--bg, [data-svgf-' + kind + ']');
		if (!el) return null;
		var filter = document.getElementById(el.getAttribute('data-svgf-' + kind) || sharedId);
		var node = filter && filter.getElementsByTagName(tag)[0];
		return node ? { el: el, node: node } : null;
	}

	/* Pointer light */
	document.addEventListener('pointermove', function (e) {
		var h = hit(e.target, 'spotlight', 'fx-spotlight', 'fePointLight');
		if (!h) return;
		var r = h.el.getBoundingClientRect();
		h.node.setAttribute('x', (e.clientX - r.left).toFixed(1));
		h.node.setAttribute('y', (e.clientY - r.top).toFixed(1));
	});

	/* Hover melt: one easing state per displacement map */
	var MAX = 45, states = new WeakMap();
	function go(map, v) {
		var s = states.get(map) || { cur: 0, target: 0, raf: 0 };
		states.set(map, s);
		s.target = v;
		cancelAnimationFrame(s.raf);
		if (reduceQuery.matches) { s.cur = v; map.setAttribute('scale', v); return; }
		(function step() {
			s.cur += (s.target - s.cur) * 0.12;
			if (Math.abs(s.target - s.cur) < 0.2) s.cur = s.target;
			map.setAttribute('scale', s.cur.toFixed(1));
			if (s.cur !== s.target) s.raf = requestAnimationFrame(step);
		})();
	}
	function melt(e, v) {
		if (e.pointerType !== 'mouse') return;
		var h = hit(e.target, 'melt', 'fx-melt', 'feDisplacementMap');
		if (h && !(e.relatedTarget && h.el.contains(e.relatedTarget))) go(h.node, v); // enter/leave, not child moves
	}
	document.addEventListener('pointerover', function (e) { melt(e, MAX); });
	document.addEventListener('pointerout', function (e) { melt(e, 0); });
	document.addEventListener('pointerdown', function (e) {
		if (e.pointerType === 'mouse') return;
		var h = hit(e.target, 'melt', 'fx-melt', 'feDisplacementMap');
		if (h) go(h.node, (states.get(h.node) || {}).target ? 0 : MAX);
	});
})();
