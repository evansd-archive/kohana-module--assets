<?php
/**
 * Copyright (c) 2008-2009 David Evans
 * License: MIT-style (see license.txt)
**/

abstract class Assets_Base_Controller extends Controller {

	// Caching options: FALSE, TRUE and 'static'
	public $cache = FALSE;

	// How long to cache server-side
	public $cache_lifetime = 1800;

	// How long to cache client-side
	public $expiry_time = 1800;

	// Key used for setting and getting cached content
	// By default constructer sets this based on hashed URL
	public $cache_id;

	// Tags for cached content
	public $cache_tags = array('assets');

	// Default content type - will be determined from extension if left blank
	public $content_type;

	// Extension of requested file
	public $extension;

	// File permissions for files cached in DOCROOT
	// Default: read and write for owner and group, read for the rest
	public $chmod = 0664;



	public function __construct()
	{
		parent::__construct();

		// Get the extension
		$this->extension = pathinfo(url::current(), PATHINFO_EXTENSION);

		// Set the cache id based on the hash of the URL (with query)
		$this->cache_id = 'cached_assets.'.sha1(url::current(TRUE));

		// Add event after the constructor is finished, but before controller methods are called, to serve content from the cache if possible
		Event::add('system.post_controller_constructor', array($this, '_serve_from_cache'));

		// Add event to set the content-type and expiry time headers
		Event::add('system.send_headers', array($this, '_send_headers'));
	}



	public function _serve_from_cache()
	{
		// Check the expiry time in the request headers and just return Not Modified if appropriate
		if ($this->expiry_time)
		{
			expires::check($this->expiry_time);
		}

		// If caching is turned on and we're using Kohana's cache library ...
		if ($this->cache AND $this->cache !== 'static')
		{
			// Try to retrive it from the cache
			$content = Cache::instance()->get($this->cache_id);

			if( ! empty($content))
			{
				echo $content; // Serve the cached content ...
				Kohana::shutdown(); // ... run the shutdown events
				exit; // ... and bail out
			}
		}

		// Add event to cache the output (we check whether caching is turned on at the final moment)
		Event::add('system.display', array($this, '_cache_output'));

	}



	public function _cache_output()
	{
		// We don't cache if an error has occured
		if ($this->cache AND ! Kohana::$has_error)
		{
			// Cache by saving the output in the DOCROOT so it can be served up as a static file
			if ($this->cache === 'static')
			{
				$this->_cache_in_docroot($path, Event::$data);
			}

			// Otherwise use Kohana's caching library
			else
			{
				Cache::instance()->set($this->cache_id, Event::$data, $this->cache_tags, $this->cache_lifetime);
			}
		}

	}



	public function _send_headers()
	{
		// We don't want to mess with the headers on error pages
		if ( ! headers_sent() AND ! Kohana::$has_error)
		{
			// If the mimetype isn't manually set ...
			if (empty($this->content_type))
			{
				// Get mimetype based on extension
				$mimes = Kohana::config('mimes.'.$this->extension);

				// Use default if none found
				$this->content_type = (isset($mimes[0])) ? $mimes[0] : 'application/octet-stream';
			}

			header('Content-type: '.$this->content_type);

			// Set client-side caching time
			if($this->expiry_time)
			{
				expires::set($this->expiry_time);
			}
		}
	}


	protected function _cache_in_docroot($path, $content)
	{
		// Translate URL into a pathname in DOCROOT
		$path = DOCROOT.DIRECTORY_SEPARATOR.ltrim($path, '\\/');

		$dir = dirname($path);

		// Disable error reporting
		$ER = error_reporting(0);

		// Attempt to make, recursively, all required directories
		mkdir($dir, 0775, TRUE);

		// If directory exists and we're not overwriting a file ...
		if (is_dir($dir) AND ! file_exists($path))
		{
			if ($success = file_put_contents($path, $content))
			{
				// Set permissions
				chmod($path, $this->chmod);
			}
		}

		// Turn error reporting back on
		error_reporting($ER);

		return ! empty($success);
	}


}
