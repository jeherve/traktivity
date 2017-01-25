/* global Query */

jQuery( document ).ready( function( $ ) {
	$( '#traktivity_settings' ).on( 'submit', function( e ) {
		// By default, we don't allow submitting the form.
		e.preventDefault();
		// Get our username and Trakt.tv API key from the form.
		var username = $( '#username' ).val();
		var trakt_api_key = $( '#trakt_api_key' ).val();

		// Make a query to our custom endpoint with those parameters.
		$.ajax({
			url: traktivity_settings.api_url + 'traktivity/v1/connection/' + username + '/' + trakt_api_key,
			method: 'GET',
			beforeSend : function( xhr ) {
				xhr.setRequestHeader( 'X-WP-Nonce', traktivity_settings.api_nonce );
			},
			success: function( response ){
				if ( response.code === 200 ) {
					console.log( 'good key' );
					//$('#traktivity_settings').submit();
					$('#traktivity_settings').unbind().submit();
				} else {
					// Display the message returned by the API.
					$( '#api_test_results' ).html( '<p><strong>' + response.message + '</p></strong>' ).show();
					// Style the notice message box according to the response code.
					$( '#api_test_results' ).addClass( 'notice-error' );
					console.log( 'bad key' );
				}
			}
		});

	});
});
