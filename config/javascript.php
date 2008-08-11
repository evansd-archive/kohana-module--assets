<?php defined('SYSPATH') or die('No direct script access.');

$config['directory'] = 'javascript';

if( ! IN_PRODUCTION) // leave JS uncached and uncompressed while in development
{
	$config['cache'] = FALSE;
	$config['compress'] = FALSE;
	$config['expiry_time'] = FALSE;
}
else
{
	$config['cache'] = 'static';
	
	$config['compress'] = array(
		
		'type' => 'packer',
		'level' => 'Normal',
		
	);
}
