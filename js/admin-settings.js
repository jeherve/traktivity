/* global Query */

jQuery( document ).ready( function( $ ) {
	// When clicking on the connection test button, check the connection.
	$( '#submit_connection_test' ).on( 'click', function( e ) {

		// Get our username and Trakt.tv API key from the form.
		var username = $( '#username' ).val();
		var trakt_api_key = $( '#trakt_api_key' ).val();

		// If we have no data in the form, show a notice and stop here.
		if ( ( '' === username ) || ( '' === trakt_api_key ) ) {
			$( '#test_message' ).show();
			$( '#test_message' ).html( '<strong>' + traktivity_settings.empty_message + '</strong>' ).show();
			$( '#api_test_results' ).addClass( 'notice-error' );
			return true;
		}

		// Make a query to our custom endpoint with those parameters.
		$.ajax({
			url: traktivity_settings.api_url + 'traktivity/v1/connection/' + username + '/' + trakt_api_key,
			method: 'GET',
			beforeSend : function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', traktivity_settings.api_nonce );
			},
			success: function( response ){
				// Display the message returned by the API.
				$( '#test_message' ).show();
				$( '#test_message' ).html( '<strong>' + response.message + '</strong>' ).show();
				$( '#api_test_results' ).removeClass( 'notice-error' );
				$( '#api_test_results' ).removeClass( 'notice-success' );
				if ( response.code === 200 ) {
					// Style the notice message box according to the response code.
					$( '#api_test_results' ).addClass( 'notice-success' );
				} else {
					// Style the notice message box according to the response code.
					$( '#api_test_results' ).addClass( 'notice-error' );
				}
			}
		});
	});

	// When clicking on the Full sync button, start sync.
	$( '#full_sync' ).on( 'click', function( e ) {
		// Make a query to our custom endpoint to launch sync.
		$.ajax({
			url: traktivity_settings.api_url + 'traktivity/v1/sync',
			method: 'POST',
			beforeSend : function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', traktivity_settings.api_nonce );
			}
		}).done( function ( response ) {
			$( '#full_sync' ).attr( 'value', traktivity_settings.progress_message );
			$( '#full_sync' ).addClass( 'disabled' );
			$( '#full_sync_details' ).html( response ).show();
		});
	});
});
