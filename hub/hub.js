/*
 * TheStickPress admin behaviour, the same in every 4 Me plugin:
 * - Row tables (<table data-tsp-rows>): a Remove button per row (the row is
 *   marked and goes when you press Save, with Undo), "+ Add" for a new row,
 *   and a lock with an explanation on rows that cannot be removed.
 * - Settings forms: a "You have unsaved changes" bar with Save and Discard,
 *   and a warning before leaving the page with unsaved changes.
 * No jQuery. Texts come from tspHub (wp_localize_script).
 */
( function () {
	'use strict';
	var t = window.tspHub || {};
	var ICON = {
		trash: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M4 7h16M10 11v6M14 11v6M5 7l1 13h12l1-13M9 7V4h6v3"/></svg>',
		undo: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 14L4 9l5-5"/><path d="M4 9h10a6 6 0 0 1 0 12h-3"/></svg>',
		lock: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="5" y="11" width="14" height="10" rx="2"/><path d="M8 11V7a4 4 0 0 1 8 0v4"/></svg>',
		plus: '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" width="15" height="15"><path d="M12 5v14M5 12h14"/></svg>'
	};

	/* ---------- Unsaved changes ---------- */
	var dirty = null; // the form with unsaved changes
	var bar   = null;

	function makeBar() {
		bar = document.createElement( 'div' );
		bar.className = 'tsp-savebar';
		bar.setAttribute( 'role', 'status' );
		bar.innerHTML = '<span class="tsp-savebar__dot"></span><span></span>' +
			'<button type="button" class="button tsp-discard"></button>' +
			'<button type="button" class="button button-primary tsp-save"></button>';
		bar.children[ 1 ].textContent = t.unsaved || 'You have unsaved changes';
		bar.querySelector( '.tsp-discard' ).textContent = t.discard || 'Discard';
		bar.querySelector( '.tsp-save' ).textContent = t.save || 'Save changes';
		bar.querySelector( '.tsp-save' ).addEventListener( 'click', function () {
			if ( dirty ) {
				var form   = dirty;
				var submit = form.querySelector( '[type="submit"]' );
				dirty = null;
				window.removeEventListener( 'beforeunload', warn );
				if ( submit ) {
					submit.click(); // runs the form's own checks and handlers
				} else {
					form.submit();
				}
			}
		} );
		bar.querySelector( '.tsp-discard' ).addEventListener( 'click', function () {
			window.removeEventListener( 'beforeunload', warn );
			window.location.reload();
		} );
		document.body.appendChild( bar );
	}

	function warn( e ) {
		e.preventDefault();
		e.returnValue = '';
	}

	function markDirty( form ) {
		if ( ! form || ! form.hasAttribute( 'data-tsp-watch' ) ) {
			return;
		}
		dirty = form;
		if ( ! bar ) {
			makeBar();
		}
		bar.classList.add( 'is-on' );
		window.addEventListener( 'beforeunload', warn );
	}

	function watchForms() {
		document.querySelectorAll( '.wrap form' ).forEach( function ( form ) {
			// Settings forms only (saved through options.php), or forms marked
			// data-tsp-watch-form. Lists, search boxes and test tools are left alone.
			var settings = form.querySelector( 'input[name="option_page"]' ) || form.hasAttribute( 'data-tsp-watch-form' );
			if ( ! settings || ! form.querySelector( '[type="submit"]' ) || form.closest( '.z4m' ) ) {
				return;
			}
			form.setAttribute( 'data-tsp-watch', '' );
			form.addEventListener( 'input', function () {
				markDirty( form );
			} );
			form.addEventListener( 'change', function () {
				markDirty( form );
			} );
			form.addEventListener( 'submit', function () {
				window.removeEventListener( 'beforeunload', warn );
				if ( bar ) {
					bar.classList.remove( 'is-on' );
				}
			} );
		} );
	}

	/* ---------- Row tables ---------- */
	function rowIndex( name ) {
		var m = /\[(\d+)\]/.exec( name || '' );
		return m ? parseInt( m[ 1 ], 10 ) : -1;
	}

	function removeButton( row, table ) {
		var cell = document.createElement( 'td' );
		cell.className = 'tsp-row-actions';
		var check = row.querySelector( 'input[type="checkbox"][name$="[delete]"]' );
		var lock  = row.getAttribute( 'data-tsp-lock' );
		if ( check ) {
			check.closest( 'td' ).classList.add( 'tsp-hidden-cell' );
		}
		if ( lock ) {
			cell.innerHTML = '<span class="tsp-row-lock">' + ICON.lock + '<span></span></span>';
			cell.querySelector( '.tsp-row-lock span' ).textContent = t.locked || 'Built in';
			var tip = document.createElement( 'span' );
			tip.className = 'tsp-tip';
			tip.tabIndex = 0;
			tip.setAttribute( 'data-tip', lock );
			cell.querySelector( '.tsp-row-lock' ).appendChild( tip );
			return cell;
		}
		var btn = document.createElement( 'button' );
		btn.type = 'button';
		btn.className = 'tsp-row-btn';
		var note = document.createElement( 'span' );
		note.className = 'tsp-row-note';
		note.hidden = true;
		note.textContent = table.getAttribute( 'data-tsp-remove-note' ) || t.removeNote || 'Removed when you press Save.';
		function paint() {
			var gone = row.classList.contains( 'tsp-row-removed' );
			btn.innerHTML = ( gone ? ICON.undo : ICON.trash ) + '<span></span>';
			btn.lastChild.textContent = gone ? ( t.undo || 'Undo' ) : ( t.remove || 'Remove' );
			btn.setAttribute( 'aria-pressed', gone ? 'true' : 'false' );
			note.hidden = ! gone;
		}
		btn.addEventListener( 'click', function () {
			var form = table.closest( 'form' );
			if ( check ) {
				// Saved row with a remove box: mark it; it goes when the form is saved.
				row.classList.toggle( 'tsp-row-removed' );
				check.checked = row.classList.contains( 'tsp-row-removed' );
				paint();
			} else if ( ! row.hasAttribute( 'data-tsp-new' ) ) {
				// Saved row without a remove box: its fields are left out when saving (Undo puts them back).
				var gone = row.classList.toggle( 'tsp-row-removed' );
				row.querySelectorAll( 'input, select, textarea' ).forEach( function ( f ) {
					f.disabled = gone;
				} );
				paint();
			} else {
				// New, unsaved row: take it away now (keep at least one empty row).
				var rows = table.tBodies[ 0 ].querySelectorAll( 'tr' );
				if ( rows.length > 1 ) {
					row.remove();
				} else {
					row.querySelectorAll( 'input:not([type="hidden"]):not([type="checkbox"]), textarea' ).forEach( function ( f ) {
						f.value = '';
					} );
				}
			}
			markDirty( form );
		} );
		paint();
		cell.appendChild( btn );
		cell.appendChild( note );
		return cell;
	}

	function setupTable( table ) {
		var body = table.tBodies[ 0 ];
		if ( ! body || table.hasAttribute( 'data-tsp-ready' ) ) {
			return;
		}
		table.setAttribute( 'data-tsp-ready', '' );
		// Header: hide the old "Remove" column, add an empty one for the buttons.
		var head = table.tHead && table.tHead.rows[ 0 ];
		if ( head ) {
			var last = head.cells[ head.cells.length - 1 ];
			if ( body.querySelector( 'input[type="checkbox"][name$="[delete]"]' ) || table.hasAttribute( 'data-tsp-has-remove-col' ) ) {
				last.classList.add( 'tsp-hidden-cell' );
			}
			var th = document.createElement( 'th' );
			th.textContent = '';
			head.appendChild( th );
		}
		Array.prototype.forEach.call( body.rows, function ( row ) {
			if ( row.querySelector( '.tsp-row-actions' ) ) {
				return;
			}
			// Old remove cell of a row without a checkbox (built-in rows): hide it too.
			if ( table.hasAttribute( 'data-tsp-has-remove-col' ) && ! row.querySelector( 'input[name$="[delete]"]' ) ) {
				row.cells[ row.cells.length - 1 ].classList.add( 'tsp-hidden-cell' );
			}
			if ( row.hasAttribute( 'data-tsp-new' ) ) {
				row.classList.add( 'tsp-row-new' );
			}
			row.appendChild( removeButton( row, table ) );
		} );
		// "+ Add" copies the last new row.
		var label = table.getAttribute( 'data-tsp-add' );
		if ( label ) {
			var add = document.createElement( 'button' );
			add.type = 'button';
			add.className = 'tsp-add-row';
			add.innerHTML = ICON.plus + '<span></span>';
			add.lastChild.textContent = label;
			add.addEventListener( 'click', function () {
				var source = body.querySelector( 'tr[data-tsp-new]:last-of-type' ) || body.rows[ body.rows.length - 1 ];
				var copy   = source.cloneNode( true );
				var max    = -1;
				body.querySelectorAll( '[name]' ).forEach( function ( f ) {
					max = Math.max( max, rowIndex( f.name ) );
				} );
				var from = rowIndex( ( source.querySelector( '[name]' ) || {} ).name );
				copy.querySelectorAll( '[name]' ).forEach( function ( f ) {
					if ( from > -1 ) {
						f.name = f.name.replace( '[' + from + ']', '[' + ( max + 1 ) + ']' );
					}
					if ( 'checkbox' === f.type || 'radio' === f.type ) {
						f.checked = false;
					} else if ( 'hidden' !== f.type && 'SELECT' !== f.tagName ) {
						f.value = '';
					}
				} );
				copy.classList.remove( 'tsp-row-removed' );
				copy.classList.add( 'tsp-row-new' );
				copy.setAttribute( 'data-tsp-new', '' );
				var actions = copy.querySelector( '.tsp-row-actions' );
				if ( actions ) {
					actions.remove();
				}
				copy.appendChild( removeButton( copy, table ) );
				body.appendChild( copy );
				var first = copy.querySelector( 'input[type="text"], textarea' );
				if ( first ) {
					first.focus();
				}
				markDirty( table.closest( 'form' ) );
			} );
			table.parentNode.insertBefore( add, table.nextSibling );
		}
	}

	/* ---------- Tooltips ---------- */
	// Drawn on the page (not inside the table), so a table or card never cuts
	// them off; placed above the "?" or below it when there is no room.
	var box = null;
	var owner = null;

	function placeTip( tip ) {
		if ( ! box ) {
			box = document.createElement( 'div' );
			box.className = 'tsp-tipbox';
			box.id = 'tsp-tipbox';
			box.setAttribute( 'role', 'tooltip' );
			document.body.appendChild( box );
		}
		owner = tip;
		box.textContent = tip.getAttribute( 'data-tip' );
		box.classList.remove( 'is-below' );
		box.classList.add( 'is-on' );
		tip.setAttribute( 'aria-describedby', 'tsp-tipbox' );
		var r    = tip.getBoundingClientRect();
		var w    = box.offsetWidth;
		var h    = box.offsetHeight;
		var gap  = 8;
		var left = Math.min( Math.max( gap, r.left + r.width / 2 - w / 2 ), window.innerWidth - w - gap );
		var top  = r.top - h - gap;
		if ( top < gap + 32 ) { // 32 = admin bar.
			top = r.bottom + gap;
			box.classList.add( 'is-below' );
		}
		box.style.left = left + 'px';
		box.style.top  = top + 'px';
		box.style.setProperty( '--tsp-arrow', ( r.left + r.width / 2 - left ) + 'px' );
	}

	function hideTip() {
		if ( box ) {
			box.classList.remove( 'is-on' );
		}
		if ( owner ) {
			owner.removeAttribute( 'aria-describedby' );
			owner = null;
		}
	}

	function setupTips() {
		document.documentElement.classList.add( 'tsp-js' );
		[ 'mouseover', 'focusin' ].forEach( function ( type ) {
			document.addEventListener( type, function ( e ) {
				var tip = e.target.closest && e.target.closest( '.tsp-tip' );
				if ( tip ) {
					placeTip( tip );
				}
			} );
		} );
		[ 'mouseout', 'focusout' ].forEach( function ( type ) {
			document.addEventListener( type, function ( e ) {
				if ( owner && e.target.closest && e.target.closest( '.tsp-tip' ) === owner ) {
					hideTip();
				}
			} );
		} );
		window.addEventListener( 'scroll', hideTip, true );
		document.addEventListener( 'keydown', function ( e ) {
			if ( 'Escape' === e.key ) {
				hideTip();
			}
		} );
	}

	/* ---------- Show only what the choice needs ----------
	 * <select data-tsp-toggle> inside [data-tsp-group]: elements with
	 * data-tsp-when="link form" show only when one of those values is chosen.
	 */
	function setupToggles() {
		document.querySelectorAll( 'select[data-tsp-toggle]' ).forEach( function ( select ) {
			var scope = select.closest( '[data-tsp-group]' ) || select.parentNode;
			function update() {
				scope.querySelectorAll( '[data-tsp-when]' ).forEach( function ( el ) {
					el.hidden = el.getAttribute( 'data-tsp-when' ).split( ' ' ).indexOf( select.value ) === -1;
				} );
			}
			select.addEventListener( 'change', update );
			update();
		} );
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		setupToggles();
		setupTips();
		watchForms();
		document.querySelectorAll( 'table[data-tsp-rows]' ).forEach( setupTable );
	} );
}() );
