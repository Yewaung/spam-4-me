( function () {
	function arm( event ) {
		var target = event.target;
		var form = target && target.closest ? target.closest( 'form' ) : null;

		if ( ! form ) {
			return;
		}

		var token = form.querySelector( 'input[name="_s4m_t"]' );
		var proof = form.querySelector( 'input[name="_s4m_js"]' );

		if ( token && proof ) {
			proof.value = token.value;
		}
	}

	[ 'focusin', 'pointerdown', 'keydown', 'touchstart', 'submit' ].forEach( function ( type ) {
		document.addEventListener( type, arm, { capture: true, passive: true } );
	} );

	function armNinja() {
		if ( armNinja.done || ! window.Backbone || ! window.Backbone.Radio ) {
			return;
		}

		armNinja.done = true;

		window.Backbone.Radio.channel( 'forms' ).on( 'before:submit', function ( model ) {
			var box = document.querySelector( '.s4m-nf[data-s4m-form="' + String( model.get( 'id' ) ).split( '_' )[ 0 ] + '"]' );
			var token = box ? box.querySelector( 'input[name="_s4m_t"]' ) : null;
			var trap = box ? box.querySelector( 'input[name="s4m_website"]' ) : null;
			var extra = model.get( 'extra' ) || {};

			if ( token ) {
				extra._s4m_t = token.value;
				extra._s4m_js = token.value;
				extra.s4m_website = trap ? trap.value : '';
				model.set( 'extra', extra );
			}
		} );
	}

	armNinja();
	document.addEventListener( 'DOMContentLoaded', armNinja );
	window.addEventListener( 'load', armNinja );
} )();
