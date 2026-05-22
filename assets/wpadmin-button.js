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
}() );
