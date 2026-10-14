( function ( wp ) {
	var el = wp.element.createElement;
	var SSR = wp.serverSideRender;
	var useBlockProps = wp.blockEditor.useBlockProps;
	var InspectorControls = wp.blockEditor.InspectorControls;
	var PanelBody = wp.components.PanelBody;
	var TextControl = wp.components.TextControl;
	var Fragment = wp.element.Fragment;
	var fields = window.tccWording || [];

	function wordingPanel( attributes, setAttributes ) {
		var wording = attributes.wording || {};
		var groups = [];
		fields.forEach( function ( f ) { if ( groups.indexOf( f.group ) === -1 ) groups.push( f.group ); } );
		return el( InspectorControls, {},
			el( PanelBody, { title: 'Wording', initialOpen: false },
				el( 'p', { style: { color: '#757575', marginTop: 0 } }, 'Everything the stack says, by season. Empty = the default shown beneath. Curly-brace words are filled in automatically.' )
			),
			groups.map( function ( g ) {
				return el( PanelBody, { key: g, title: g, initialOpen: false },
					fields.filter( function ( f ) { return f.group === g; } ).map( function ( f ) {
						return el( TextControl, {
							key: f.key,
							label: f.label,
							help: 'Default: ' + f.default,
							value: wording[ f.key ] || '',
							placeholder: f.default,
							__nextHasNoMarginBottom: true,
							onChange: function ( v ) {
								var next = Object.assign( {}, wording );
								if ( v ) { next[ f.key ] = v; } else { delete next[ f.key ]; }
								setAttributes( { wording: next } );
							}
						} );
					} )
				);
			} )
		);
	}

	wp.blocks.registerBlockType( 'tilden/cantata-stack', {
		title: 'Cantata stack',
		icon: 'playlist-audio',
		description: 'Every cantata, newest first, with the current one expanded.',
		category: 'widgets',
		attributes: { wording: { type: 'object', default: {} } },
		supports: { html: false, multiple: false, align: [ 'full' ] },
		edit: function ( props ) {
			return el( Fragment, {},
				wordingPanel( props.attributes, props.setAttributes ),
				el( 'div', useBlockProps(), el( SSR, { block: 'tilden/cantata-stack', attributes: props.attributes } ) )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'tilden/cantata-details', {
		title: 'Cantata details',
		icon: 'tickets-alt',
		description: 'The full entry for this cantata post.',
		category: 'widgets',
		supports: { html: false, multiple: false, align: [ 'full' ] },
		edit: function () {
			return el( 'div', useBlockProps(), el( SSR, { block: 'tilden/cantata-details' } ) );
		},
		save: function () { return null; }
	} );

	var SelectControl = wp.components.SelectControl;
	var cantatas = ( window.tccCantatas || [] ).map( function ( c ) { return { value: String( c.value ), label: c.label }; } );

	function cantataPanel( props, extra ) {
		return el( InspectorControls, {},
			el( PanelBody, { title: 'Settings', initialOpen: true },
				el( SelectControl, {
					label: 'Cantata',
					value: String( props.attributes.cantata || 0 ),
					options: cantatas,
					onChange: function ( v ) { props.setAttributes( { cantata: parseInt( v, 10 ) || 0 } ); },
					__nextHasNoMarginBottom: true
				} ),
				extra || null
			)
		);
	}

	wp.blocks.registerBlockType( 'tilden/rehearsal-schedule', {
		title: 'Rehearsal schedule',
		icon: 'calendar-alt',
		description: 'The season’s rehearsals for a cantata, as a timeline.',
		category: 'widgets',
		attributes: { cantata: { type: 'integer', default: 0 } },
		supports: { html: false, multiple: false, align: [ 'wide', 'full' ] },
		edit: function ( props ) {
			return el( Fragment, {},
				cantataPanel( props ),
				el( 'div', useBlockProps(), el( SSR, { block: 'tilden/rehearsal-schedule', attributes: props.attributes } ) )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'tilden/next-rehearsal', {
		title: 'Next rehearsal',
		icon: 'clock',
		description: 'A card for the next rehearsal, with a link to the full schedule.',
		category: 'widgets',
		attributes: { cantata: { type: 'integer', default: 0 }, scheduleUrl: { type: 'string', default: '' } },
		supports: { html: false, multiple: false },
		edit: function ( props ) {
			return el( Fragment, {},
				cantataPanel( props, el( TextControl, {
					label: 'Link to the full schedule',
					value: props.attributes.scheduleUrl || '',
					placeholder: '/singers/schedule/',
					onChange: function ( v ) { props.setAttributes( { scheduleUrl: v } ); },
					__nextHasNoMarginBottom: true
				} ) ),
				el( 'div', useBlockProps(), el( SSR, { block: 'tilden/next-rehearsal', attributes: props.attributes } ) )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'tilden/cantata-listen', {
		title: 'Listening video',
		icon: 'video-alt3',
		description: 'The publisher’s recording of this year’s cantata, for singers.',
		category: 'widgets',
		attributes: { cantata: { type: 'integer', default: 0 } },
		supports: { html: false, multiple: false, align: [ 'wide' ] },
		edit: function ( props ) {
			return el( Fragment, {},
				cantataPanel( props ),
				el( 'div', useBlockProps(), el( SSR, { block: 'tilden/cantata-listen', attributes: props.attributes } ) )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'tilden/backstage-hero', {
		title: 'Backstage hero',
		icon: 'cover-image',
		description: 'This year’s cantata as a tinted band with its cover.',
		category: 'widgets',
		attributes: { cantata: { type: 'integer', default: 0 } },
		supports: { html: false, multiple: false, align: [ 'full' ] },
		edit: function ( props ) {
			return el( Fragment, {},
				cantataPanel( props ),
				el( 'div', useBlockProps(), el( SSR, { block: 'tilden/backstage-hero', attributes: props.attributes } ) )
			);
		},
		save: function () { return null; }
	} );

	var ToggleControl = wp.components.ToggleControl;
	wp.blocks.registerBlockType( 'tilden/page-head', {
		title: 'Backstage page header',
		icon: 'heading',
		description: 'Back link, season eyebrow and the page title. Use with the “Backstage page” template so the title isn’t printed twice.',
		category: 'widgets',
		attributes: { cantata: { type: 'integer', default: 0 }, title: { type: 'string', default: '' }, back: { type: 'boolean', default: true } },
		supports: { html: false, multiple: false },
		edit: function ( props ) {
			return el( Fragment, {},
				cantataPanel( props, el( Fragment, {},
					el( TextControl, { label: 'Title', help: 'Leave blank to use the page’s own title.', value: props.attributes.title || '', onChange: function ( v ) { props.setAttributes( { title: v } ); } } ),
					el( ToggleControl, { label: 'Show “← Backstage” link', checked: !! props.attributes.back, onChange: function ( v ) { props.setAttributes( { back: v } ); } } )
				) ),
				el( 'div', useBlockProps(), el( SSR, { block: 'tilden/page-head', attributes: props.attributes } ) )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'tilden/contact-form', {
		title: 'Contact form',
		icon: 'email',
		description: 'Name, email, phone, what they’re writing about, and a message. Emails the director and keeps a copy under Cantata → Messages.',
		category: 'widgets',
		attributes: { button: { type: 'string', default: 'Send message' }, thanks: { type: 'string', default: '' } },
		supports: { html: false, multiple: false },
		edit: function ( props ) {
			return el( Fragment, {},
				el( InspectorControls, {},
					el( PanelBody, { title: 'Contact form', initialOpen: true },
						el( TextControl, { label: 'Button label', value: props.attributes.button || '', onChange: function ( v ) { props.setAttributes( { button: v } ); } } ),
						el( TextControl, { label: 'Thank-you line', help: 'Shown after a message is sent. Leave blank for the default.', value: props.attributes.thanks || '', onChange: function ( v ) { props.setAttributes( { thanks: v } ); } } ),
						el( 'p', { style: { fontSize: '12px', color: '#757575' } }, 'Where it sends: Cantata → Settings.' )
					)
				),
				el( 'div', useBlockProps(), el( SSR, { block: 'tilden/contact-form', attributes: props.attributes } ) )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'tilden/part-tracks', {
		title: 'Practice tracks (this part)',
		icon: 'playlist-audio',
		description: 'The practice player for the page’s voice part — this year’s tracks with the director’s notes, slow-down, looping and practice tracking; earlier years underneath. Reads the part from the page title unless you set it.',
		category: 'widgets',
		attributes: { part: { type: 'string', default: 'auto' }, past: { type: 'boolean', default: true } },
		supports: { html: false, multiple: false, align: [ 'wide' ] },
		edit: function ( props ) {
			return el( Fragment, {},
				el( InspectorControls, {},
					el( PanelBody, { title: 'Practice tracks', initialOpen: true },
						el( SelectControl, {
							label: 'Part',
							value: props.attributes.part || 'auto',
							options: [
								{ label: 'From the page title', value: 'auto' },
								{ label: 'Soprano', value: 'soprano' }, { label: 'Alto', value: 'alto' }, { label: 'Tenor', value: 'tenor' }, { label: 'Bass', value: 'bass' },
								{ label: 'Accompaniment (performance)', value: 'accompaniment' }, { label: 'All voices (listening)', value: 'listen' }
							],
							onChange: function ( v ) { props.setAttributes( { part: v } ); }
						} ),
						el( ToggleControl, { label: 'Show earlier years underneath', checked: !! props.attributes.past, onChange: function ( v ) { props.setAttributes( { past: v } ); } } ),
						el( 'p', { style: { fontSize: '12px', color: '#757575' } }, 'Tracks are added on each cantata post (Practice tracks box). The preview below is static; the real player runs on the page.' )
					)
				),
				el( 'div', useBlockProps(), el( SSR, { block: 'tilden/part-tracks', attributes: props.attributes } ) )
			);
		},
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'tilden/backstage-signin', {
		title: 'Backstage sign-in',
		icon: 'id-alt',
		description: '“Have you sung with us before?” — returning members confirm their details, new people sign up. Replaces the paper sheet.',
		category: 'widgets',
		attributes: {},
		supports: { html: false, multiple: false },
		edit: function () { return el( 'div', useBlockProps(), el( SSR, { block: 'tilden/backstage-signin', attributes: {} } ) ); },
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'tilden/backstage-dashboard', {
		title: 'Backstage dashboard',
		icon: 'dashboard',
		description: 'The whole Backstage hub: sign-in for new devices, then each person’s own next rehearsal, songs to work on, tracks, documents and team.',
		category: 'widgets',
		attributes: {},
		supports: { html: false, multiple: false, align: [ 'wide' ] },
		edit: function () { return el( 'div', useBlockProps(), el( SSR, { block: 'tilden/backstage-dashboard', attributes: {} } ) ); },
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'tilden/practice', {
		title: 'Practice page',
		icon: 'controls-play',
		description: 'The whole-page practice player: every part, the person’s own part first, with notes laid out under the player.',
		category: 'widgets',
		attributes: {},
		supports: { html: false, multiple: false, align: [ 'wide', 'full' ] },
		edit: function () { return el( 'div', useBlockProps(), el( SSR, { block: 'tilden/practice', attributes: {} } ) ); },
		save: function () { return null; }
	} );

	wp.blocks.registerBlockType( 'tilden/backstage-docs', {
		title: 'Cantata documents',
		icon: 'media-document',
		description: 'Documents from this year’s cantata post, as cards, for one group or all groups.',
		category: 'widgets',
		attributes: { cantata: { type: 'integer', default: 0 }, group: { type: 'string', default: 'all' }, headings: { type: 'boolean', default: true } },
		supports: { html: false, multiple: true },
		edit: function ( props ) {
			return el( Fragment, {},
				cantataPanel( props, el( Fragment, {},
					el( SelectControl, {
						label: 'Which group',
						value: props.attributes.group || 'all',
						options: [ { value: 'all', label: 'All groups, with headings' }, { value: 'everyone', label: 'Everyone' }, { value: 'singers', label: 'Singers' }, { value: 'narrators', label: 'Narrators' }, { value: 'actors', label: 'Actors' }, { value: 'tech', label: 'Tech crew' } ],
						onChange: function ( v ) { props.setAttributes( { group: v } ); },
						__nextHasNoMarginBottom: true
					} ),
					el( ToggleControl, {
						label: 'Show the group heading',
						checked: !! props.attributes.headings,
						onChange: function ( v ) { props.setAttributes( { headings: v } ); },
						__nextHasNoMarginBottom: true
					} )
				) ),
				el( 'div', useBlockProps(), el( SSR, { block: 'tilden/backstage-docs', attributes: props.attributes } ) )
			);
		},
		save: function () { return null; }
	} );
} )( window.wp );
