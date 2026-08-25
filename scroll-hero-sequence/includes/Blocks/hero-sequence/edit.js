( function ( wp ) {
	const { registerBlockType } = wp.blocks;
	const { InspectorControls } = wp.blockEditor || wp.editor;
	const { PanelBody, SelectControl } = wp.components;
	const { useSelect } = wp.data;
	const { createElement: el, Fragment } = wp.element;

	registerBlockType( 'scroll-hero-sequence/hero-sequence', {
		edit: function Edit( props ) {
			const { attributes, setAttributes } = props;
			const heroes = useSelect( function ( select ) {
				return select( 'core' ).getEntityRecords( 'postType', 'hero_sequence', {
					per_page: -1,
					status: 'publish,draft',
				} );
			}, [] );

			const options = [ { label: '— Select Hero —', value: 0 } ];
			if ( heroes ) {
				heroes.forEach( function ( hero ) {
					options.push( { label: hero.title.rendered, value: hero.id } );
				} );
			}

			return el(
				Fragment,
				null,
				el(
					InspectorControls,
					null,
					el(
						PanelBody,
						{ title: 'Hero Sequence', initialOpen: true },
						el( SelectControl, {
							label: 'Select Hero',
							value: attributes.heroId,
							options: options,
							onChange: function ( val ) {
								setAttributes( { heroId: parseInt( val, 10 ) || 0 } );
							},
						} )
					)
				),
				el(
					'div',
					{ className: 'shs-block-placeholder' },
					attributes.heroId
						? 'Scroll Hero Sequence #' + attributes.heroId
						: 'Select a hero sequence from the sidebar.'
				)
			);
		},
	} );
} )( window.wp );
