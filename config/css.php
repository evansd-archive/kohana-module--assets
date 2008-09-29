<?php

// While in development ...
if ( ! IN_PRODUCTION)
{
	// Don't cache server-side
	$config['cache'] = FALSE;
	
	// Don't allow clients to cache
	$config['expiry_time'] = 0;
	
	// Don't compress
	$config['compress'] = FALSE;
}

// While in production ...
else
{
	// Cache as a static file in DOCROOT/assets/css
	$config['cache'] = 'static'; 
	
	// Cache using Kohana's Cache library
	// $config['cache'] = TRUE; 
	
	// Clients should cache for 30 mins
	$config['expiry_time'] = 1800;
	
	$config['compress'] = array(
		'type' => 'strip' // - Borrowed from the old Kohana media module
		// 'type' => 'yuicompressor' // - Requires Java
	);
}
