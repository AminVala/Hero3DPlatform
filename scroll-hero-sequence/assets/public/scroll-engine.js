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

	/**
	 * Collect content animations from the config's scenes and resolve their
	 * target elements inside this hero root. Scrollsequence-parity: animations
	 * are frame-based, with a start/end active window plus "from" tweens
	 * anchored to the start frame and "to" tweens anchored to the end frame.
	 *
	 * @param {Object}      cfg  Parsed config.
	 * @param {HTMLElement} root Hero root element (animation scope).
	 * @return {Array} List of { els, start, end, from, to } records.
	 */
	function gatherContentAnimations( cfg, root ) {
		var scenes = ( cfg && cfg.scenes ) || [];
		var out = [];
		for ( var s = 0; s < scenes.length; s++ ) {
			var anims = scenes[ s ].content_animations || [];
			for ( var a = 0; a < anims.length; a++ ) {
				var anim = anims[ a ];
				if ( ! anim || ! anim.selector ) {
					continue;
				}
				var els;
				try {
					els = root.querySelectorAll( anim.selector );
				} catch ( e ) {
					continue;
				}
				if ( ! els.length ) {
					continue;
				}
				out.push( {
					els: els,
					start: parseInt( anim.start, 10 ) || 0,
					end: parseInt( anim.end, 10 ) || 0,
					from: anim.from || [],
					to: anim.to || []
				} );
			}
		}
		return out;
	}

	function clamp01( v ) {
		return Math.min( 1, Math.max( 0, v ) );
	}

	/**
	 * Build a CSS transform/opacity state for one tween at a given 0..1 phase.
	 * phase 0 = tween's anchored extreme, phase 1 = neutral (identity).
	 */
	function applyTween( state, tween, phase ) {
		var type = tween.type;
		var value = parseFloat( tween.value ) || 0;
		if ( type === 'fade' ) {
			// value = starting opacity; interpolate toward 1 as phase -> 1.
			state.opacity = value + ( 1 - value ) * phase;
		} else if ( type === 'move_vertical' ) {
			state.ty += value * ( 1 - phase );
		} else if ( type === 'move_horizontal' ) {
			state.tx += value * ( 1 - phase );
		} else if ( type === 'scale' ) {
			state.scale = value + ( 1 - value ) * phase;
		}
	}

	/**
	 * Update all content-animation targets for the current frame.
	 */
	function updateContentAnimations( list, frame ) {
		for ( var i = 0; i < list.length; i++ ) {
			var rec = list[ i ];
			var active = frame >= rec.start && frame <= rec.end;
			var state = { opacity: 1, tx: 0, ty: 0, scale: 1 };

			if ( ! active ) {
				// Outside the window the element is hidden (Scrollsequence).
				state.opacity = 0;
			} else {
				var j;
				for ( j = 0; j < rec.from.length; j++ ) {
					var fdur = parseInt( rec.from[ j ].duration, 10 ) || 0;
					var fphase = fdur > 0 ? clamp01( ( frame - rec.start ) / fdur ) : 1;
					applyTween( state, rec.from[ j ], fphase );
				}
				for ( j = 0; j < rec.to.length; j++ ) {
					var tdur = parseInt( rec.to[ j ].duration, 10 ) || 0;
					var tphase = tdur > 0 ? clamp01( ( rec.end - frame ) / tdur ) : 1;
					applyTween( state, rec.to[ j ], tphase );
				}
			}

			var transform =
				'translate(' + state.tx + '%,' + state.ty + '%) scale(' + state.scale + ')';
			for ( var k = 0; k < rec.els.length; k++ ) {
				var el = rec.els[ k ];
				el.style.opacity = String( state.opacity );
				el.style.transform = transform;
				el.style.visibility = state.opacity <= 0.001 ? 'hidden' : 'visible';
			}
		}
	}

	function showAllContentAnimations( list ) {
		for ( var i = 0; i < list.length; i++ ) {
			var els = list[ i ].els;
			for ( var k = 0; k < els.length; k++ ) {
				els[ k ].style.opacity = '1';
				els[ k ].style.transform = 'none';
				els[ k ].style.visibility = 'visible';
			}
		}
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
		var contentAnims = gatherContentAnimations( cfg, root );
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
			showAllContentAnimations( contentAnims );
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
				updateContentAnimations( contentAnims, frameNum );
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
