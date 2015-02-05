jQuery( function ( $ ) {
	$( 'a.signin' ).on( 'click', function ( e ) {
		e.preventDefault();
		
		navigator.id.request();
	} );
	
	$( 'a.signout' ).on( 'click', function ( e ) {
		e.preventDefault();
		
		navigator.id.logout();
	} );
	
	navigator.id.watch({
		loggedInUser: SESS_EMAIL,
		
		onlogin: function( assertion ) {
			$.ajax({
				type : 'POST',
				url : '/auth/login',
				data : { assertion : assertion },
				success : function ( res, status, xhr ) {
					window.location.reload();
				},
				error : function ( xhr, status, err ) {
					navigator.id.logout();
					alert( "The login system failed. Please try again later or contact chris@interpr.it." );
				}
			});
		},
		onlogout: function() {
			// A user has logged out! Here you need to:
			// Tear down the user's session by redirecting the user or making a call to your backend.
			// Also, make sure loggedInUser will get set to null on the next page load.
			// (That's a literal JavaScript null. Not false, 0, or undefined. null.)
			window.location.href = '/signout';
		}
	});
} );