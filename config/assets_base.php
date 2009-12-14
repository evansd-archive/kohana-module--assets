<?php
// Turn on caching when in production
$config['cache'] = IN_PRODUCTION;

// By default, cache content directly into the public docroot
// so it can be served up directly by the webserver as a 
// static file
$config['cache_config'] = Assets_Base_Controller::WRITE_TO_DOCROOT;

// When in production, clients should refresh every 30 mins
// When in development, clients should always refresh. (Note that
// this setting becomes ineffective if docroot caching is used.)
$config['expiry_time'] = (IN_PRODUCTION) ? 3600 : 0;
