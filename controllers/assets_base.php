<?php
/**
 * Copyright (c) 2008-2009 David Evans
 * License: MIT-style (see license.txt)
**/

abstract class Assets_Base_Controller extends Controller
{
	// Content type header to output
	// Will be determined automatically from the extension if not
	// specified
	public $content_type;

	// Extension of requested file (set automatically by the constructor)
	public $extension;
	
	// Whether to cache output
	public $cache = FALSE;
	
	// Settings to pass to Cache library constructor
	// Accepts one special value, the class constant WRITE_TO_DOCROOT,
	// which, instead of using Kohana's Cache library, writes the output
	// into the appropriate place in the document root where it can be
	// served up directly as a static file without involving PHP 
	public $cache_config = NULL;
	
	// Special value for cache_config (we use the dot char as this is
	// guaranteeed not to be a valid Cache group name)
	const WRITE_TO_DOCROOT = '.';
	
	// Permissions for files cached in DOCROOT, and for any new directories
	// that are created.
	// Default: read and write for owner, read for the rest
	public $file_permissions      = 0644;
	public $directory_permissions = 0755;
	
	// How long to cache server-side (leave NULL for default)
	public $cache_lifetime = NULL;

	// Tags for cached content
	public $cache_tags = array();
	
	// How long clients should cache for (0 to turn off client caching)
	public $expiry_time = 0;
	
	// Key used for setting and getting cached content
	// By default, it includes the sha1 of the URL
	protected $cache_id;
	
	// Holds instance of the Cache library
	protected $cache_instance;
	

	public function __construct()
	{
		parent::__construct();
		
		$this->apply_config(Kohana::config('assets_base', FALSE, FALSE));

		// Get the extension
		$this->extension = pathinfo(url::current(), PATHINFO_EXTENSION);

		// After the constructor is finished but before controller methods are called,
		// attempt to serve content from the cache if possible
		Event::add('system.post_controller_constructor', array($this, '_serve_from_cache'));

		// Set the content-type and expiry time headers
		Event::add('system.send_headers', array($this, '_send_headers'));
	}

	
	protected function apply_config($config)
	{
		foreach((array) $config as $key => $value)
		{
			if(property_exists($this, $key)) $this->$key = $value;
		}
	}
	
	
	public function _serve_from_cache()
	{
		// Check the expiry time in the request headers and just return
		// Not Modified if appropriate
		if ($this->expiry_time)
		{
			expires::check($this->expiry_time);
		}

		// If caching is turned on and we're using Kohana's cache library ...
		if ($this->cache AND ! $this->cache_config_writes_to_docroot())
		{
			// Get cache id (calling this cache_id() method if it's not already set)
			$cache_id = isset($this->cache_id) ? $this->cache_id : ($this->cache_id = $this->cache_id());
			
			// Try to retrive it from the cache
			$content = $this->cache_instance()->get($cache_id);

			if ( ! empty($content))
			{
				// Serve the cached content ...
				echo $content;
				// ... run the shutdown events
				Kohana::shutdown();
				// ... and bail out
				exit;
			}
		}
		
		// Add handler to cache the output (we check whether caching is
		// turned on at the final moment)
		Event::add('system.display', array($this, '_cache_output'));
	}
	
	
	protected function cache_id()
	{
		// Set the cache id based on the hash of the full URL
		// This avoids any issues with the URL being too long or
		// containing characters not allowed in cache keys
		return 'assets_base_controller.'.sha1(url::current(TRUE));
	}
	
	
	protected function cache_instance()
	{
		if ( ! isset($this->cache_instance))
		{
			$this->cache_instance = new Cache($this->cache_config);
		}
		
		return $this->cache_instance;
	}
	
	
	protected function cache_config_writes_to_docroot()
	{
		// Support old API
		if ($this->cache === 'static') return TRUE;
		
		return ($this->cache_config === self::WRITE_TO_DOCROOT);
	}
	

	public function _cache_output()
	{
		// We don't cache if an error has occured
		if ($this->cache AND ! Kohana::$has_error)
		{
			// Cache by saving the output in the DOCROOT so it can be served up as a static file
			if ($this->cache_config_writes_to_docroot())
			{
				$this->write_to_docroot(url::current(), Event::$data);
			}

			// Otherwise use Kohana's caching library
			else
			{
				// Get cache id (calling this cache_id() method if it's not already set)
				$cache_id = isset($this->cache_id) ? $this->cache_id : ($this->cache_id = $this->cache_id());
				$this->cache_instance()->set($this->cache_id(), Event::$data, $this->cache_tags, $this->cache_lifetime);
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
			if ($this->expiry_time)
			{
				expires::set($this->expiry_time);
			}
		}
	}


	protected function write_to_docroot($path, $content)
	{
		// Translate URL into a pathname in DOCROOT
		$path = DOCROOT.DIRECTORY_SEPARATOR.ltrim($path, '\\/');

		$dir = dirname($path);
		
		$success = FALSE;
		
		// Disable error reporting
		$ER = error_reporting(0);

		// Attempt to make, recursively, all required directories
		mkdir($dir, $this->directory_permissions, TRUE);

		// If directory exists and we're not overwriting a file ...
		if (is_dir($dir) AND ! file_exists($path))
		{
			// ... save it ...
			if ($success = file_put_contents($path, $content))
			{
				// ... and set permissions
				chmod($path, $this->file_permissions);
			}
		}

		// Turn error reporting back on
		error_reporting($ER);

		return $success;
	}
}
