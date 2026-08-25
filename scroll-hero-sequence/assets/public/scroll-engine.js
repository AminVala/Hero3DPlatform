/**
 * Scroll Hero Sequence — frontend engine (vanilla JS, no GSAP dependency).
 *
 * Design goals:
 *  - 60fps: scroll work is throttled through requestAnimationFrame.
 *  - No flicker: every frame image is preloaded before it can be shown.
 *  - Accessible: overlays are toggled (aria-hidden) instead of rebuilt.
 *  - Responsive: a mobile frame set + disable option are honored.
 *  - Resilient: prefers-reduced-motion and Page Visibility are handled.
 */
( function () {
	'use strict';

	function prefersReducedMotion() {
		return window.matchMedia( '(prefers-reduced-motion: reduce)' ).matches;
	}

	function isMobileViewport() {
		return window.matchMedia( '(max-width: 782px)' ).matches;
	}

	function isLowPowerDevice() {
		var cores = navigator.hardwareConcurrency || 4;
		return cores <= 4;
	}

	function frameFromProgress( progress, total ) {
		return Math.min( total, Math.max( 1, Math.round( progress * ( total - 1 ) ) + 1 ) );
	}

	function resolveFrameUrl( frameUrls, frameNumber, lockZone, masterUrl ) {
		if ( lockZone && frameNumber >= lockZone.start_frame && frameNumber <= lockZone.end_frame ) {
			return masterUrl || frameUrls[ lockZone.start_frame ] || frameUrls[ frameNumber ] || '';
		}
		return frameUrls[ frameNumber ] || masterUrl || '';
	}

	/**
	 * Toggle overlay visibility without rebuilding the DOM.
	 * Overlays are server-rendered; we only add/remove the visible class
	 * and flip aria-hidden so screen readers are not disrupted per frame.
	 */
	function updateOverlays( overlayEls, currentFrame ) {
		for ( var i = 0; i < overlayEls.length; i++ ) {
			var el = overlayEls[ i ];
			var trigger = parseInt( el.getAttribute( 'data-frame' ) || '0', 10 );
			var visible = currentFrame >= trigger;
			if ( visible ) {
				if ( ! el.classList.contains( 'shs-overlay--visible' ) ) {
					el.classList.add( 'shs-overlay--visible' );
					el.setAttribute( 'aria-hidden', 'false' );
				}
			} else if ( el.classList.contains( 'shs-overlay--visible' ) ) {
				el.classList.remove( 'shs-overlay--visible' );
				el.setAttribute( 'aria-hidden', 'true' );
			}
		}
	}

	function showAllOverlays( overlayEls ) {
		for ( var i = 0; i < overlayEls.length; i++ ) {
			overlayEls[ i ].classList.add( 'shs-overlay--visible' );
			overlayEls[ i ].setAttribute( 'aria-hidden', 'false' );
		}
	}

	function preloadAll( frameUrls, masterUrl, onReady ) {
		var urls = [];
		Object.keys( frameUrls ).forEach( function ( key ) {
			if ( frameUrls[ key ] && urls.indexOf( frameUrls[ key ] ) === -1 ) {
				urls.push( frameUrls[ key ] );
			}
		} );
		if ( masterUrl && urls.indexOf( masterUrl ) === -1 ) {
			urls.push( masterUrl );
		}

		var remaining = urls.length;
		if ( remaining === 0 ) {
			onReady();
			return;
		}

		urls.forEach( function ( url ) {
			var img = new Image();
			var done = function () {
				remaining -= 1;
				if ( remaining <= 0 ) {
					onReady();
				}
			};
			img.onload = done;
			img.onerror = done;
			img.src = url;
		} );
	}

	function initHero( root ) {
		var raw = root.getAttribute( 'data-config' );
		if ( ! raw ) {
			return;
		}

		var data;
		try {
			data = JSON.parse( raw );
		} catch ( e ) {
			return;
		}

		var frameImg = root.querySelector( '.shs-hero__frame' );
		var overlayRoot = root.querySelector( '.shs-hero__overlays' );
		if ( ! frameImg || ! overlayRoot ) {
			return;
		}

		var overlayEls = overlayRoot.querySelectorAll( '.shs-overlay' );

		var cfg = data.config || {};
		var scrollCfg = cfg.scroll_config || {};
		var masterUrl = data.master_url || data.poster_url || '';
		var lockZone = data.lock_zone || { start_frame: 14, end_frame: 24 };

		// Choose desktop vs mobile frame set.
		var mobile = isMobileViewport();
		var frameUrls = data.frame_urls || {};
		var total = data.total_frames || 24;
		if ( mobile && data.frame_urls_mobile && Object.keys( data.frame_urls_mobile ).length ) {
			frameUrls = data.frame_urls_mobile;
			total = data.total_frames_mobile || total;
		}

		var pinDuration = scrollCfg.pin_duration || '300%';

		// Static fallbacks: reduced motion, mobile-disabled, or low-power mobile.
		var forceStatic =
			prefersReducedMotion() ||
			( scrollCfg.mobile_disable && mobile ) ||
			( mobile && isLowPowerDevice() );

		if ( forceStatic ) {
			if ( masterUrl ) {
				frameImg.src = masterUrl;
			}
			root.classList.add( 'shs-hero--static' );
			showAllOverlays( overlayEls );
			return;
		}

		var section = document.createElement( 'div' );
		section.className = 'shs-hero__pin';
		section.style.height = pinDuration;
		root.insertBefore( section, root.firstChild );

		var ticking = false;
		var paused = false;
		var lastFrame = -1;

		function paint() {
			ticking = false;
			if ( paused ) {
				return;
			}

			var rect = root.getBoundingClientRect();
			var viewH = window.innerHeight || 1;
			var totalScrollable = rect.height - viewH;
			var scrolled = -rect.top;
			var progress = totalScrollable > 0 ? scrolled / totalScrollable : 0;
			progress = Math.min( 1, Math.max( 0, progress ) );

			var frameNum = frameFromProgress( progress, total );
			if ( frameNum !== lastFrame ) {
				lastFrame = frameNum;
				var url = resolveFrameUrl( frameUrls, frameNum, lockZone, masterUrl );
				if ( url && frameImg.src !== url ) {
					frameImg.src = url;
				}
				updateOverlays( overlayEls, frameNum );
			}
		}

		function requestPaint() {
			if ( ! ticking ) {
				ticking = true;
				window.requestAnimationFrame( paint );
			}
		}

		// Preload everything, then wire up scroll.
		preloadAll( frameUrls, masterUrl, function () {
			root.classList.add( 'shs-hero--ready' );
			window.addEventListener( 'scroll', requestPaint, { passive: true } );
			window.addEventListener( 'resize', requestPaint, { passive: true } );
			requestPaint();
		} );

		// Pause work when the tab/section is hidden.
		document.addEventListener( 'visibilitychange', function () {
			paused = document.hidden;
			if ( ! paused ) {
				requestPaint();
			}
		} );
	}

	function boot() {
		var roots = document.querySelectorAll( '.shs-hero[data-config]' );
		for ( var i = 0; i < roots.length; i++ ) {
			initHero( roots[ i ] );
		}
	}

	if ( document.readyState === 'loading' ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
