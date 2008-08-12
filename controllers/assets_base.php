<?php
abstract class Assets_Base_Controller extends Controller { // abstract because this class should never be instantiated, only subclassed
	
	/* caching options:
		FALSE : don't cache
		TRUE : use Kohana Cache library
		'static' : cache permanently as a static file in DOCROOT - suitable for files that are hit frequently and change rarely e.g., css and javascript */
	public $cache = FALSE;
	
	// how long to cache server-side - has no effect when using 'static'
	public $cache_lifetime = 1800;
	
	// how long to cache client-side
	public $expiry_time = 1800;
	
	// tags for cached content
	public $cache_tags = array('assets');
	
	// default content type - will be determined from extension if left blank
	public $content_type;
	
	// extension of requested file
	public $extension;


	
	public function __construct()
	{
		parent::__construct();
		
		// get the extension
		$this->extension = pathinfo(url::current(), PATHINFO_EXTENSION);
		
		// add event after the constructor is finished, but before controller methods are called, to serve content from the cache if possible
		Event::add('system.post_controller_constructor', array($this, '_serve_from_cache'));
		
		// add event to set the content-type and expiry time headers
		Event::add('system.send_headers', array($this, '_send_headers'));
	}

	
	
	public function _serve_from_cache()
	{
		// check the expiry time in the request headers and just return Not Modified if appropriate
		if($this->expiry_time) expires::check($this->expiry_time);
			
		if($this->cache AND $this->cache !== 'static') // if caching is turned on and we're using Kohana's cache library ...
		{
			$content = Cache::instance()->get('assets.'.url::current()); // try to retrive it from the cache
			if( ! empty($content))
			{
				echo $content; // serve the cached content ...
				Kohana::shutdown(); // ... run the shutdown events
				exit; // ... and bail out
			}
		}
	
		// add event to cache the output (we check whether caching is turned on at the final moment)
		Event::add('system.display', array($this, '_cache_output'));
		
	}
	
	
	
	public function _cache_output()
	{
		if ($this->cache AND ! Kohana::$has_error)  // we don't cache if an error has occured
		{	
			if($this->cache === 'static') // save the output in the DOCROOT so it can be served up as a static file
			{
				$path = explode('/', url::current());
			
				$path = DOCROOT.DIRECTORY_SEPARATOR.join(DIRECTORY_SEPARATOR, $path);
				
				$dir = dirname($path);
				
				// Disable error reporting
				$ER = error_reporting(0);
				
				// attempt to make, recursively, all required directories
				mkdir($dir, 0777, TRUE);
				
				if(is_dir($dir) AND ! file_exists($path))
				{
					file_put_contents($path, Event::$data);
				}
				
				// Turn error reporting back on
				error_reporting($ER);
				
			}
			else // otherwise use Kohana's cache
			{
				Cache::instance()->set('assets.'.url::current(), Event::$data, $this->cache_tags, $this->cache_lifetime);
			}
		}
		
	}
	
	
	
	public function _send_headers()
	{
		if( ! headers_sent() AND ! Kohana::$has_error) // we don't want to mess with the headers on error pages
		{
			if(empty($this->content_type)) // if the mimetype isn't manually set ...
			{
				$mimes = Kohana::config('mimes.'.$this->extension); // get mimetype based on extension
				$this->content_type = (isset($mimes[0])) ? $mimes[0] : 'application/octet-stream'; // use default if none found
			}
			
			header('Content-type: '.$this->content_type);
			
			if($this->expiry_time)
			{
				expires::set($this->expiry_time);
			}
		}
	}
	

}