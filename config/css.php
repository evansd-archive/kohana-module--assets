<?php
// When in production, compress the resulting CSS
$config['compress'] = IN_PRODUCTION;

// When in production, load all @import'ed files and concat them into a
// singe file for speedier loading
$config['process_imports'] = IN_PRODUCTION;

$config['compress_config'] = array
(
	'type' => 'strip'            // Borrowed from the old Kohana media module
	// 'type' => 'yuicompressor' // Requires Java
);

