( function () {
	var button = document.querySelector( '.wpadmin-button' );
	var root = document.documentElement;
	var visualViewport = window.visualViewport;
	var baseOffset = 16;

	// Body-level theme effects can create a fixed-position containing block.
	if ( button && button.parentNode !== root ) {
		root.appendChild( button );
	}

	function setViewportOffsets() {
		if ( ! visualViewport ) {
			return;
		}

		var layoutHeight = root.clientHeight || window.innerHeight || visualViewport.height;
		var layoutWidth = root.clientWidth || window.innerWidth || visualViewport.width;
		var bottomOffset = layoutHeight - visualViewport.height - visualViewport.offsetTop;
		var rightOffset = layoutWidth - visualViewport.width - visualViewport.offsetLeft;

		root.style.setProperty( '--wpadmin-button-viewport-bottom', Math.max( baseOffset, Math.round( bottomOffset + baseOffset ) ) + 'px' );
		root.style.setProperty( '--wpadmin-button-viewport-left', Math.max( baseOffset, Math.round( visualViewport.offsetLeft + baseOffset ) ) + 'px' );
		root.style.setProperty( '--wpadmin-button-viewport-right', Math.max( baseOffset, Math.round( rightOffset + baseOffset ) ) + 'px' );
	}

	setViewportOffsets();

	if ( visualViewport ) {
		visualViewport.addEventListener( 'resize', setViewportOffsets );
		visualViewport.addEventListener( 'scroll', setViewportOffsets );
	}

	window.addEventListener( 'orientationchange', setViewportOffsets );

	var container = document.querySelector( '[data-wpadmin-button]' );
	var toggle = container ? container.querySelector( '.wpadmin-button__toggle' ) : null;
	var menu = container ? container.querySelector( '.wpadmin-button__menu' ) : null;

	if ( container && toggle && menu ) {
		var items = function () {
			return Array.prototype.slice.call( menu.querySelectorAll( '.wpadmin-button__pill' ) );
		};

		var open = function () {
			menu.hidden = false;
			container.setAttribute( 'data-open', 'true' );
			toggle.setAttribute( 'aria-expanded', 'true' );
		};

		var close = function ( returnFocus ) {
			menu.hidden = true;
			container.removeAttribute( 'data-open' );
			toggle.setAttribute( 'aria-expanded', 'false' );
			if ( returnFocus ) {
				toggle.focus();
			}
		};

		var isOpen = function () {
			return 'true' === toggle.getAttribute( 'aria-expanded' );
		};

		// Click / tap toggles.
		toggle.addEventListener( 'click', function () {
			if ( isOpen() ) {
				close( false );
			} else {
				open();
			}
		} );

		// Desktop hover: open on enter, close on leave.
		container.addEventListener( 'mouseenter', open );
		container.addEventListener( 'mouseleave', function () {
			close( false );
		} );

		// Keyboard.
		toggle.addEventListener( 'keydown', function ( event ) {
			if ( 'ArrowUp' === event.key || 'ArrowDown' === event.key || 'Enter' === event.key || ' ' === event.key ) {
				event.preventDefault();
				open();
				var list = items();
				if ( list.length ) {
					list[0].focus();
				}
			}
		} );

		menu.addEventListener( 'keydown', function ( event ) {
			var list = items();
			var index = list.indexOf( document.activeElement );

			if ( 'Escape' === event.key ) {
				close( true );
			} else if ( 'ArrowDown' === event.key ) {
				event.preventDefault();
				if ( index < list.length - 1 ) { list[ index + 1 ].focus(); }
			} else if ( 'ArrowUp' === event.key ) {
				event.preventDefault();
				if ( index > 0 ) { list[ index - 1 ].focus(); } else { toggle.focus(); }
			}
		} );

		// Tap / click outside closes.
		document.addEventListener( 'click', function ( event ) {
			if ( isOpen() && ! container.contains( event.target ) ) {
				close( false );
			}
		} );
	}
}() );
