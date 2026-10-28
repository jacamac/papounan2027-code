/**
 * Click tracking: clicks on [data-trk], the calendar's bac:book event and the
 * papounan:track event (other plugins) become Matomo events (category
 * « Navigation » unless whitelisted otherwise). Never blocks or delays the
 * navigation; does nothing when Matomo is blocked or not loaded.
 */
( function () {
	'use strict';

	var CATEGORY = 'Navigation';
	var CATEGORIES = { navigation: 'Navigation', guide: 'Guide' }; // data-trk-cat / detail.cat whitelist.
	var DEFAULT_ACTION = 'vers-reservation';
	var ROOM_CLASS = 'papounan-room-'; // Body class output by click-tracking.php on room pages.
	var VALID = /^[a-z0-9-]+$/;
	var DEBUG_KEY = 'pap.analytics.debug'; // Same switch as the booking engine.

	function room() {
		var classes = document.body ? document.body.classList : [];
		for ( var i = 0; i < classes.length; i++ ) {
			if ( 0 === classes[ i ].indexOf( ROOM_CLASS ) ) {
				var slug = classes[ i ].slice( ROOM_CLASS.length );
				return VALID.test( slug ) ? slug : '';
			}
		}
		return '';
	}

	function category( key ) {
		return 'string' === typeof key && Object.prototype.hasOwnProperty.call( CATEGORIES, key ) ? CATEGORIES[ key ] : CATEGORY;
	}

	function debug( entry ) {
		try {
			if ( '1' === window.localStorage.getItem( DEBUG_KEY ) ) {
				console.debug( '[papounan tracking]', entry );
			}
		} catch {
			// No storage: no debug.
		}
	}

	// cat: already mapped; ctx: optional, appended after the room slug.
	function track( cat, action, placement, ctx ) {
		try {
			var paq = window._paq;
			if ( ! paq || 'function' !== typeof paq.push ) {
				return;
			}
			var name = placement;
			var slug = room();
			if ( slug ) {
				name += ' · ' + slug;
			}
			if ( 'string' === typeof ctx && VALID.test( ctx ) ) {
				name += ' · ' + ctx;
			}
			var entry = [ 'trackEvent', cat, action, name ];
			paq.push( entry );
			debug( entry );
		} catch {
			// Tracker broken or blocked: the navigation goes on.
		}
	}

	function onClick( event ) {
		// auxclick: middle button only (opening in a new tab is still intent).
		if ( 'auxclick' === event.type && 1 !== event.button ) {
			return;
		}
		var target = event.target;
		var el = target && target.closest ? target.closest( '[data-trk]' ) : null;
		if ( ! el ) {
			return;
		}
		var placement = el.getAttribute( 'data-trk' );
		var action = el.getAttribute( 'data-trk-action' ) || DEFAULT_ACTION;
		if ( VALID.test( placement ) && VALID.test( action ) ) {
			// Read on the element itself: the planner clones its cards into a dialog.
			track( category( el.getAttribute( 'data-trk-cat' ) ), action, placement, el.getAttribute( 'data-trk-ctx' ) );
		}
	}

	// Capture phase: still counted when a handler on the element stops propagation.
	document.addEventListener( 'click', onClick, { capture: true, passive: true } );
	document.addEventListener( 'auxclick', onClick, { capture: true, passive: true } );

	// Availability calendar: a bookable day clicked (dispatched before it navigates).
	document.addEventListener( 'bac:book', function () {
		track( CATEGORY, DEFAULT_ACTION, 'calendrier' );
	} );

	// Other plugins: dispatch new CustomEvent( 'papounan:track', { detail: { cat, action, name, ctx } } ).
	document.addEventListener( 'papounan:track', function ( event ) {
		var d = event && event.detail;
		if ( ! d || 'string' !== typeof d.action || 'string' !== typeof d.name ) {
			return;
		}
		if ( VALID.test( d.action ) && VALID.test( d.name ) ) {
			track( category( d.cat ), d.action, d.name, d.ctx );
		}
	} );
} )();
