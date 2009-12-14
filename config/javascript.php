<?php
// When in production, compress the resulting JavaScript
$config['compress'] = IN_PRODUCTION;

$config['compress_config'] = array
(
	'type' => 'jsminplus'        // The default
	// 'type' => 'jsmin'         // Reasonably safe, but breaks on conditional comments
	// 'type' => 'packer'        // Good compression, but careful with your semi-colons
	// 'type' => 'yuicompressor' // The best and safest compression, but requires Java
);
