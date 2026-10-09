/**
 * Room gallery lightbox: native <dialog>, no dependency.
 * Without JavaScript the links simply open the full-size image.
 */
( function () {
	'use strict';

	function init( dialog ) {
		var gallery = document.getElementById( dialog.dataset.gallery );
		if ( ! gallery || ! dialog.showModal ) {
			return;
		}

		var links = Array.prototype.slice.call( gallery.querySelectorAll( '.room-gallery__link' ) );
		var img = dialog.querySelector( '.room-gallery__full' );
		var counter = dialog.querySelector( '.room-gallery__counter' );
		var current = 0;
		var opener = null;

		function show( index ) {
			current = ( index + links.length ) % links.length;
			var link = links[ current ];
			var thumb = link.querySelector( 'img' );
			img.src = link.href;
			img.alt = thumb ? thumb.alt : '';
			counter.textContent = ( current + 1 ) + ' / ' + links.length;
		}

		gallery.addEventListener( 'click', function ( event ) {
			var link = event.target.closest( '.room-gallery__link' );
			if ( ! link ) {
				return;
			}
			event.preventDefault();
			opener = link;
			show( links.indexOf( link ) );
			dialog.showModal();
		} );

		dialog.addEventListener( 'click', function ( event ) {
			var action = event.target.closest( '[data-action]' );
			if ( action ) {
				var name = action.dataset.action;
				if ( 'close' === name ) {
					dialog.close();
				} else {
					show( current + ( 'next' === name ? 1 : -1 ) );
				}
			} else if ( event.target === dialog ) {
				dialog.close(); // Click on the backdrop.
			}
		} );

		dialog.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowRight' === event.key ) {
				show( current + 1 );
			} else if ( 'ArrowLeft' === event.key ) {
				show( current - 1 );
			}
		} );

		// Esc is handled natively by <dialog>; return focus to the thumbnail.
		dialog.addEventListener( 'close', function () {
			img.removeAttribute( 'src' );
			if ( opener ) {
				opener.focus();
			}
		} );
	}

	function boot() {
		document.querySelectorAll( '.room-gallery__dialog' ).forEach( init );
	}

	if ( 'loading' === document.readyState ) {
		document.addEventListener( 'DOMContentLoaded', boot );
	} else {
		boot();
	}
} )();
