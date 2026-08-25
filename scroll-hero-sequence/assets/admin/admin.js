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
