( function ( $ ) {
	'use strict';

	$( function () {
		var frame;

		$( '#shs-select-master' ).on( 'click', function ( e ) {
			e.preventDefault();

			if ( frame ) {
				frame.open();
				return;
			}

			frame = wp.media( {
				title: shsAdmin.i18n.selectMaster,
				button: { text: 'Select' },
				multiple: false,
				library: { type: 'image' },
			} );

			frame.on( 'select', function () {
				var attachment = frame.state().get( 'selection' ).first().toJSON();
				$( '#shs_master_image_id' ).val( attachment.id );
				$( '.shs-master-preview' ).html(
					'<img src="' + attachment.url + '" alt="" />'
				);
			} );

			frame.open();
		} );

		// ---- Frame sequence management -------------------------------------

		function i18n( key, fallback ) {
			return ( window.shsAdmin && shsAdmin.i18n && shsAdmin.i18n[ key ] ) || fallback;
		}

		function syncStrip( $strip ) {
			var ids = [];
			$strip.find( '.shs-frame-strip__item' ).each( function ( i ) {
				var id = $( this ).attr( 'data-id' );
				if ( id ) {
					ids.push( id );
				}
				$( this ).find( '.shs-frame-strip__num' ).text(
					( '00' + ( i + 1 ) ).slice( -3 )
				);
			} );

			var inputId = $strip.attr( 'data-input' );
			$( '#' + inputId ).val( ids.join( ',' ) );

			var stripId = $strip.attr( 'id' );
			var $count = $( '.shs-frames__count[data-strip="' + stripId + '"]' );
			var max = parseInt( $count.attr( 'data-max' ) || '0', 10 );
			$count.text( ids.length + ' / ' + max );
		}

		function itemMarkup( attachment ) {
			var url =
				( attachment.sizes && attachment.sizes.thumbnail && attachment.sizes.thumbnail.url ) ||
				attachment.url;
			return (
				'<figure class="shs-frame-strip__item" data-id="' + attachment.id + '" draggable="true">' +
				'<span class="shs-frame-strip__num"></span>' +
				'<img src="' + url + '" alt="" />' +
				'<button type="button" class="shs-frame-strip__remove" aria-label="remove">&times;</button>' +
				'</figure>'
			);
		}

		var frameFrames = {};

		$( '.shs-add-frames' ).on( 'click', function ( e ) {
			e.preventDefault();
			var stripId = $( this ).attr( 'data-strip' );
			var max = parseInt( $( this ).attr( 'data-max' ) || '0', 10 );
			var $strip = $( '#' + stripId );

			if ( frameFrames[ stripId ] ) {
				frameFrames[ stripId ].open();
				return;
			}

			var mediaFrame = wp.media( {
				title: i18n( 'selectFrames', 'Select Frame Sequence' ),
				button: { text: i18n( 'addFrames', 'Add Frames' ) },
				multiple: 'add',
				library: { type: 'image' },
			} );

			mediaFrame.on( 'select', function () {
				var selection = mediaFrame.state().get( 'selection' ).toJSON();
				var existing = {};
				$strip.find( '.shs-frame-strip__item' ).each( function () {
					existing[ $( this ).attr( 'data-id' ) ] = true;
				} );

				var hitCap = false;
				selection.forEach( function ( attachment ) {
					if ( existing[ attachment.id ] ) {
						return;
					}
					if ( $strip.find( '.shs-frame-strip__item' ).length >= max ) {
						hitCap = true;
						return;
					}
					$strip.append( itemMarkup( attachment ) );
					existing[ attachment.id ] = true;
				} );

				syncStrip( $strip );
				if ( hitCap ) {
					window.alert( i18n( 'frameCap', 'Frame limit reached for your plan.' ) );
				}
			} );

			frameFrames[ stripId ] = mediaFrame;
			mediaFrame.open();
		} );

		$( '.shs-frame-strip' ).on( 'click', '.shs-frame-strip__remove', function ( e ) {
			e.preventDefault();
			var $strip = $( this ).closest( '.shs-frame-strip' );
			$( this ).closest( '.shs-frame-strip__item' ).remove();
			syncStrip( $strip );
		} );

		$( '.shs-clear-frames' ).on( 'click', function ( e ) {
			e.preventDefault();
			if ( ! window.confirm( i18n( 'confirmClear', 'Remove all frames from this set?' ) ) ) {
				return;
			}
			var $strip = $( '#' + $( this ).attr( 'data-strip' ) );
			$strip.empty();
			syncStrip( $strip );
		} );

		var dragEl = null;
		$( '.shs-frame-strip' ).on( 'dragstart', '.shs-frame-strip__item', function ( e ) {
			dragEl = this;
			( e.originalEvent || e ).dataTransfer.effectAllowed = 'move';
			$( this ).addClass( 'shs-frame-strip__item--dragging' );
		} );
		$( '.shs-frame-strip' ).on( 'dragend', '.shs-frame-strip__item', function () {
			$( this ).removeClass( 'shs-frame-strip__item--dragging' );
			dragEl = null;
		} );
		$( '.shs-frame-strip' ).on( 'dragover', '.shs-frame-strip__item', function ( e ) {
			e.preventDefault();
			if ( ! dragEl || dragEl === this ) {
				return;
			}
			var rect = this.getBoundingClientRect();
			var clientX = ( e.originalEvent || e ).clientX;
			var before = clientX < rect.left + rect.width / 2;
			if ( before ) {
				this.parentNode.insertBefore( dragEl, this );
			} else {
				this.parentNode.insertBefore( dragEl, this.nextSibling );
			}
		} );
		$( '.shs-frame-strip' ).on( 'drop', '.shs-frame-strip__item', function ( e ) {
			e.preventDefault();
			syncStrip( $( this ).closest( '.shs-frame-strip' ) );
		} );

		var previousSlug = $( '#shs_template_slug' ).val();
		$( '#shs_template_slug' ).on( 'change', function () {
			var slug = $( this ).val();
			var postId = $( '#post_ID' ).val();
			if ( ! postId || ! slug ) return;

			// Applying a template overwrites the current config (overlays, scenes).
			var warn = ( shsAdmin.i18n && shsAdmin.i18n.confirmTemplate ) ||
				'Applying a template will replace the current storyboard and overlays. Continue?';
			if ( ! window.confirm( warn ) ) {
				$( this ).val( previousSlug );
				return;
			}
			previousSlug = slug;

			fetch( shsAdmin.restUrl + 'heroes/' + postId + '/apply-template', {
				method: 'POST',
				headers: {
					'Content-Type': 'application/json',
					'X-WP-Nonce': shsAdmin.nonce,
				},
				body: JSON.stringify( { slug: slug } ),
			} )
				.then( function ( r ) {
					return r.json();
				} )
				.then( function () {
					window.location.reload();
				} );
		} );
	} );
} )( jQuery );
