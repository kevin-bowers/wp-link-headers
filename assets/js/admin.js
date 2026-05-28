/**
 * WP Link Headers — Admin UI.
 *
 * Handles:
 *  - Live page/post search → add row via AJAX
 *  - Custom URL tab → add row from <template>
 *  - Drag-to-reorder (jQuery UI Sortable)
 *  - rel= select → show/hide custom input
 *  - Remove-row button
 *  - Toggle opacity on enable/disable
 *  - Row index renumbering after reorder/remove
 */
( function ( $, wplhData ) {
	'use strict';

	// -------------------------------------------------------------------------
	// Counter: start above any rows already on the page.
	// -------------------------------------------------------------------------
	let rowCounter = $( '#wplh-entries-body tr.wplh-entry-row' ).length;

	function nextIndex() {
		return rowCounter++;
	}

	// -------------------------------------------------------------------------
	// Parse a <tr> HTML string safely.
	// Browsers strip table rows that aren't inside a <table>, so we wrap first.
	// -------------------------------------------------------------------------
	function parseTrHtml( html ) {
		return $( '<table><tbody>' + html + '</tbody></table>' ).find( 'tr.wplh-entry-row' );
	}

	// -------------------------------------------------------------------------
	// Re-number row data-index attributes and name="entries[N][field]" inputs.
	// Called after every add / remove / reorder so PHP receives a clean array.
	// -------------------------------------------------------------------------
	function renumberRows() {
		$( '#wplh-entries-body tr.wplh-entry-row' ).each( function ( i ) {
			const $row = $( this );
			$row.attr( 'data-index', i );
			$row.find( '[name]' ).each( function () {
				const $el  = $( this );
				const name = $el.attr( 'name' ) || '';
				$el.attr( 'name', name.replace( /^entries\[[^\]]+\]/, 'entries[' + i + ']' ) );
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Show / hide "no entries" notice.
	// -------------------------------------------------------------------------
	function syncEmptyNotice() {
		const count  = $( '#wplh-entries-body tr.wplh-entry-row' ).length;
		const $table = $( '#wplh-entries-table' );

		if ( count === 0 ) {
			let $notice = $( '#wplh-empty-notice' );
			if ( ! $notice.length ) {
				$notice = $( '<p class="wplh-empty-notice" id="wplh-empty-notice"></p>' )
					.text( 'No entries yet. Use the form below to add pages, posts, or custom URLs.' );
				$table.after( $notice );
			}
			$notice.show();
			$table.hide();
		} else {
			$( '#wplh-empty-notice' ).hide();
			$table.show();
		}
	}

	// -------------------------------------------------------------------------
	// Bind all row-level events. Also called after rows are injected dynamically.
	// -------------------------------------------------------------------------
	function bindRowEvents( $row ) {

		// Remove button.
		$row.find( '.wplh-remove-btn' ).on( 'click', function () {
			if ( ! window.confirm( wplhData.i18n.confirmRemove ) ) {
				return;
			}
			$row.fadeOut( 200, function () {
				$( this ).remove();
				renumberRows();
				syncEmptyNotice();
			} );
		} );

		// Toggle row opacity when enabled checkbox flips.
		$row.find( 'input[type="checkbox"]' ).on( 'change', function () {
			$row.toggleClass( 'wplh-row-enabled', this.checked );
			$row.toggleClass( 'wplh-row-disabled', ! this.checked );
		} );

		// rel= select → show/hide custom text input in this row.
		bindRelSelectInContext( $row );
	}

	// Show/hide rel custom inputs inside a given context element.
	function bindRelSelectInContext( $ctx ) {
		$ctx.find( '.wplh-rel-select' ).each( function () {
			const $select = $( this );
			const $custom = $select.siblings( '.wplh-rel-custom-input' );

			$select.on( 'change', function () {
				$custom.toggle( this.value === 'custom' );
				if ( this.value === 'custom' ) {
					$custom.trigger( 'focus' );
				}
			} );
		} );
	}

	// -------------------------------------------------------------------------
	// Insert a parsed row into the table, wire events, scroll to it.
	// -------------------------------------------------------------------------
	function insertRow( $row ) {
		const idx = nextIndex();
		$row.attr( 'data-index', idx );

		// Replace __IDX__ placeholders in name attributes.
		$row.find( '[name]' ).each( function () {
			const $el  = $( this );
			const name = $el.attr( 'name' ) || '';
			$el.attr( 'name', name.replace( /__IDX__/g, idx ) );
		} );

		$( '#wplh-entries-body' ).append( $row );
		bindRowEvents( $row );
		syncEmptyNotice();

		$( 'html, body' ).animate( { scrollTop: $row.offset().top - 80 }, 300 );
	}

	// -------------------------------------------------------------------------
	// jQuery UI Sortable
	// -------------------------------------------------------------------------
	$( '#wplh-entries-body' ).sortable( {
		handle:               '.wplh-drag-handle',
		axis:                 'y',
		placeholder:          'wplh-sort-placeholder',
		forcePlaceholderSize: true,
		update:               renumberRows,
	} );

	// -------------------------------------------------------------------------
	// Tab switching
	// -------------------------------------------------------------------------
	$( document ).on( 'click', '.wplh-tab-btn', function () {
		const tab = $( this ).data( 'tab' );
		$( '.wplh-tab-btn' ).removeClass( 'active' );
		$( '.wplh-tab-content' ).removeClass( 'active' );
		$( this ).addClass( 'active' );
		$( '#wplh-tab-' + tab ).addClass( 'active' );
	} );

	// The "Add Custom URL" tab has a separate <tr> for the custom rel value
	// (not a sibling of the select), so handle it separately.
	$( '#wplh-custom-rel' ).on( 'change', function () {
		const isCustom = this.value === 'custom';
		$( '.wplh-custom-rel-row' ).toggle( isCustom );
		if ( isCustom ) {
			$( '#wplh-custom-rel-value' ).trigger( 'focus' );
		}
	} );

	// -------------------------------------------------------------------------
	// Live page / post search
	// -------------------------------------------------------------------------
	let searchTimer = null;

	$( '#wplh-search-input' ).on( 'input', function () {
		const q = $.trim( this.value );
		clearTimeout( searchTimer );

		if ( q.length < 1 ) {
			$( '#wplh-search-results' ).empty();
			return;
		}

		$( '.wplh-spinner' ).addClass( 'is-active' );

		searchTimer = setTimeout( function () {
			$.ajax( {
				url:      wplhData.ajaxUrl,
				method:   'GET',
				dataType: 'json',
				data:     { action: 'wplh_search_posts', nonce: wplhData.nonce, q: q },
			} )
			.done( function ( res ) {
				$( '.wplh-spinner' ).removeClass( 'is-active' );
				const $list = $( '#wplh-search-results' ).empty();

				if ( ! res.success || ! res.data || ! res.data.length ) {
					$list.append(
						$( '<li>' ).append(
							$( '<span class="wplh-no-results"></span>' )
								.text( wplhData.i18n.noResults )
						)
					);
					return;
				}

				res.data.forEach( function ( item ) {
					const $btn = $( '<button type="button" class="wplh-result-item"></button>' )
						.append(
							$( '<span></span>' )
								.addClass( 'wplh-badge wplh-badge-' + item.type )
								.text( item.type )
						)
						.append(
							$( '<span class="wplh-result-title"></span>' ).text( item.title )
						)
						.append(
							$( '<span class="wplh-result-url"></span>' ).text( item.permalink )
						);

					$btn.on( 'click', function () {
						fetchAndInsertRow( item.id, item.type );
					} );

					$list.append( $( '<li>' ).append( $btn ) );
				} );
			} )
			.fail( function () {
				$( '.wplh-spinner' ).removeClass( 'is-active' );
			} );
		}, 300 );
	} );

	// Fetch a server-rendered row for a page/post and insert it.
	function fetchAndInsertRow( postId, postType ) {
		$.ajax( {
			url:      wplhData.ajaxUrl,
			method:   'POST',
			dataType: 'json',
			data:     {
				action:  'wplh_get_entry_row',
				nonce:   wplhData.nonce,
				post_id: postId,
				source:  postType,
				index:   '__IDX__',
			},
		} )
		.done( function ( res ) {
			if ( res.success && res.data && res.data.html ) {
				const $row = parseTrHtml( res.data.html );
				if ( $row.length ) {
					insertRow( $row );
					$( '#wplh-search-input' ).val( '' );
					$( '#wplh-search-results' ).empty();
				}
			}
		} );
	}

	// Close search results when clicking outside.
	$( document ).on( 'click', function ( e ) {
		if ( ! $( e.target ).closest( '#wplh-tab-search' ).length ) {
			$( '#wplh-search-results' ).empty();
		}
	} );

	// Keyboard: close results on Escape.
	$( '#wplh-search-input' ).on( 'keydown', function ( e ) {
		if ( e.key === 'Escape' ) {
			$( '#wplh-search-results' ).empty();
		}
	} );

	// -------------------------------------------------------------------------
	// Add Custom URL
	// -------------------------------------------------------------------------
	$( '#wplh-add-custom-btn' ).on( 'click', function () {
		const url      = $.trim( $( '#wplh-custom-url' ).val() );
		const relVal   = $( '#wplh-custom-rel' ).val();
		const relCustom = $.trim( $( '#wplh-custom-rel-value' ).val() );
		const typeVal  = $.trim( $( '#wplh-custom-type' ).val() );
		const titleVal = $.trim( $( '#wplh-custom-title' ).val() );

		if ( ! url ) {
			$( '#wplh-custom-url' ).addClass( 'wplh-error' ).trigger( 'focus' );
			return;
		}
		$( '#wplh-custom-url' ).removeClass( 'wplh-error' );

		// Clone the template row and stamp in real values.
		const rawTemplate = $( '#wplh-row-template' ).html();
		if ( ! rawTemplate ) {
			return;
		}

		const $row = parseTrHtml( rawTemplate );
		if ( ! $row.length ) {
			return;
		}

		// Stamp field values.
		$row.find( '[name$="[url]"]' ).val( url );
		$row.find( '[name$="[source]"]' ).val( 'custom' );
		$row.find( '[name$="[label]"]' ).val( url );    // use URL as label for custom entries
		$row.find( '.wplh-rel-select' ).val( relVal );
		$row.find( '.wplh-rel-custom-input' )
			.val( relCustom )
			.toggle( relVal === 'custom' );
		$row.find( '[name$="[link_type]"]' ).val( typeVal );
		$row.find( '[name$="[link_title]"]' ).val( titleVal );

		// Update the visible resource label.
		const displayUrl = url.length > 55 ? url.slice( 0, 55 ) + '…' : url;
		$row.find( '.wplh-col-resource' )
			.empty()
			.append(
				$( '<span class="wplh-badge wplh-badge-custom"></span>' ).text( 'Custom' )
			)
			.append( document.createTextNode( ' ' + displayUrl ) );

		// Show the URL inline (it's editable in the URL cell for custom entries).
		$row.find( '.wplh-col-url input[type="url"]' ).val( url );

		insertRow( $row );

		// Clear the form.
		$( '#wplh-custom-url, #wplh-custom-type, #wplh-custom-title, #wplh-custom-rel-value' ).val( '' );
		$( '#wplh-custom-rel' ).val( 'canonical' );
		$( '.wplh-rel-custom-input', '#wplh-tab-custom' ).hide();
	} );

	// -------------------------------------------------------------------------
	// Bind events on rows that already exist when the page loads.
	// -------------------------------------------------------------------------
	$( '#wplh-entries-body tr.wplh-entry-row' ).each( function () {
		bindRowEvents( $( this ) );
	} );

	syncEmptyNotice();

}( jQuery, window.wplhData || {} ) );
