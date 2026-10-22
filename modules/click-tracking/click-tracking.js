/**
 * Click tracking: clicks on [data-trk] and the calendar's bac:book event
 * become Matomo events (category « Navigation »). Never blocks or delays the
 * navigation; does nothing when Matomo is blocked or not loaded.
 */
( function () {
	'use strict';

	var CATEGORY = 'Navigation';
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

	function debug( entry ) {
		try {
			if ( '1' === window.localStorage.getItem( DEBUG_KEY ) ) {
				console.debug( '[papounan tracking]', entry );
			}
		} catch {
			// No storage: no debug.
		}
	}

	function track( action, placement ) {
		try {
			var paq = window._paq;
			if ( ! paq || 'function' !== typeof paq.push ) {
				return;
			}
			var slug = room();
			var entry = [ 'trackEvent', CATEGORY, action, slug ? placement + ' · ' + slug : placement ];
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
			track( action, placement );
		}
	}

	// Capture phase: still counted when a handler on the element stops propagation.
	document.addEventListener( 'click', onClick, { capture: true, passive: true } );
	document.addEventListener( 'auxclick', onClick, { capture: true, passive: true } );

	// Availability calendar: a bookable day clicked (dispatched before it navigates).
	document.addEventListener( 'bac:book', function () {
		track( DEFAULT_ACTION, 'calendrier' );
	} );
} )();
