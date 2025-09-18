(function( $ ) {
	'use strict';

	window.t4eConfirmHandover = function() {
		const params = new Proxy(new URLSearchParams(window.location.search), {
			get: (searchParams, prop) => searchParams.get(prop),
		});
		let orderId = params.id;
		console.log('orderId', orderId);
		fetch(t4e_pg_trustap_admin_data.confirm_handover_url, {
			method: "POST",
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': t4e_pg_trustap_admin_data.nonce
			},
			credentials: "include",
			body: JSON.stringify({orderId})
		})
		.then(response => {
			console.log('response', response);
			
			//location.reload();
		});
	}

})( jQuery );
