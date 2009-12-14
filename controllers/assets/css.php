<?php
/**
 * Copyright (c) 2008-2009 David Evans
 * License: MIT-style (see license.txt)
**/

class CSS_Controller extends Assets_Base_Controller
{
	// The assets controller will figure this out from the extension, but we might as well save it the trouble
	public $content_type = 'text/css';

	// Directory where CSS files are stored, relative to
	// application or module root
	public $directory = 'css';

	// Variables to be available to any PHP code embedded
	// in the CSS files e.g., a $header_color
	public $vars = array();

	// Whether to compress output
	public $compress = FALSE;
	
	// How to compress output
	public $compress_config = array();
	
	// Whether to process @import statments and concatenate imported files
	// into one single file for speedier loading
	public $process_imports = FALSE;


	public function __construct()
	{
		parent::__construct();

		// Get config settings
		$config = Kohana::config('css', FALSE, FALSE);
		
		// Support old API
		if (isset($config['compress']) AND is_array($config['compress']))
		{
			$config['compress_config'] = $config['compress'];
			$config['compress'] = ! empty($config['compress']);
		}

		$this->apply_config($config);
	}


	public function __call($method, $args)
	{
		// Concat all the arguments into a filepath
		array_unshift($args, $method);
		$path = join(DIRECTORY_SEPARATOR, $args);
		
		
		// Straightforwarding loading of file
		if ( ! $this->process_imports)
		{
			// Strip the extension from the filepath
			$path = substr($path, 0, -strlen($this->extension) - 1);

			// Search for file using cascading file system
			$filename = Kohana::find_file($this->directory, $path, FALSE, $this->extension);
			if( ! $filename) Event::run('system.404');
			
			$output = Kohana::$instance->_kohana_load_view($filename, $this->vars);
		}
		
		// Process @import statements
		else
		{
			$protocol = (empty($_SERVER['HTTPS']) OR $_SERVER['HTTPS'] === 'off') ? 'http' : 'https';
			$this->site_domain = $protocol.'://'.$_SERVER['HTTP_HOST'];
			// TODO: This needs to be worked out properly
			$this->path_prefix = '/assets/css/';
			
			// Load file, along with all dependencies
			$output = $this->import_and_process($this->path_prefix.$path);
		}
		
		if ($this->compress)
		{
			$output = $this->compress($output, $this->compress_config);
		}

		echo $output;
	}
	
	
	protected function import_and_process($filename, &$imported_files = array())
	{
		// Load the file contents
		$contents = $this->import_file($filename);
		
		// Set the base URL to the current file's URL
		$this->set_base_url($this->get_absolute_url($filename));
		
		// Wrap bare @import strings with url()
		$contents = preg_replace('/@import\s+"([^"]+)"/', '@import url("$1")', $contents);
		
		// Run regex callback to canonicalise all URLs
		$contents = preg_replace_callback
		(
			'/url\(.*[^\\\]\)/',
			array($this, 'canonicalise_url'),
			$contents
		);
		
		$lines = explode("\n", $contents);
		$file = FALSE;
		
		foreach($lines as $index => $line)
		{
			if (preg_match('/^\s*@import\s+url\("([^"]+)"\)/', $line, $matches))
			{
				$file = $matches[1];
				if ( ! in_array($file, $imported_files))
				{
					$imported_files[] = $file;
					$lines[$index] = $this->import_and_process($file, $imported_files);
				}
			}
		}
		
		return ($file === FALSE) ? $contents : join("\n", $lines);
	}
	
	
	protected function canonicalise_url($matches)
	{
		// We can guarantee URL begins 'url(' and terminates wtih ')'
		$url = substr($matches[0], 4, -1);
		$url = trim($url, '"\' ');
		
		// Join url with current base url (set before the regex replace
		// is initiated)
		$url = self::url_join($url, $this->base_url);
		
		if (self::startswith($url, $this->site_domain.'/'))
		{
			$url = substr($url, strlen($this->site_domain));
		}
		
		return 'url("'.$url.'")';
	}
	
	
	protected function set_base_url($url)
	{
		$this->base_url = $url;
	}
	
	
	protected function import_file($url)
	{
		// Local file
		if (self::startswith($url, $this->path_prefix))
		{
			// Strip off controller path
			$path = substr($url, strlen($this->path_prefix));
			
			// Strip off extension
			$last_dot = strrpos($path, '.');
			$extension = substr($path, $last_dot +1);
			$path = substr($path, 0, $last_dot);
			
			// Search include paths for file
			$filename = Kohana::find_file($this->directory, $path, FALSE, $extension);
			if( ! $filename) throw new Kohana_User_Exception('Missing CSS File', "Couldn't import <tt>$url</tt>");
			
			// Include file and return contents
			return Kohana::$instance->_kohana_load_view($filename, $this->vars);
		}
		
		// Remote file
		else
		{
			$url = $this->get_absolute_url($url);
			
			$ER = error_reporting(0);
			
			$contents = file_get_contents($url);
			
			error_reporting($ER);
			
			if($contents === FALSE) throw new Kohana_User_Exception('Missing CSS File', "Couldn't import <tt>$url</tt>");
			
			return $content;
		}
	}
	
	
	protected function get_absolute_url($url)
	{
		// This function assumes all URLs passed in will be in canonical
		// form i.e., either a fully specified absolute URL or a URL
		// relative to the current server route
		return ($url[0] === '/') ? $this->site_domain.$url : $url;
	}
	

	protected function compress($data, $config)
	{
		switch($config['type'])
		{
			case 'strip':
				// Borrowed from the old Kohana media module:
				// http://code.google.com/p/kohanamodules/source/browse/tags/2.2/media/controllers/media.php

				// Remove comments
				$data = preg_replace('~/\*[^*]*\*+([^/][^*]*\*+)*/~', '', $data);

				// Replace all whitespace by single spaces
				$data = preg_replace('~\s+~', ' ', $data);

				// Remove needless whitespace
				$data = preg_replace('~ *+([{}+>:;,]) *~', '$1', trim($data));

				// Remove ; that closes last property of each declaration
				$data = str_replace(';}', '}', $data);

				// Remove empty CSS declarations
				$data = preg_replace('~[^{}]++\{\}~', '', $data);

				return $data;

			case 'yuicompressor':
				$options = isset($config['options']) ? $config['options'] : '';
				return yuicompressor::compress($data, 'css', $options);

			default:
				throw new Kohana_User_Exception('Unknown CSS Compression Type', 'Unknown type: '.$config['type']);
		}
	}
	
	
	/* ----------------------
	 * Utility functions
	 * --------------------*/
	
	protected static function startswith($haystack, $needle)
	{
		return strncmp($haystack, $needle, strlen($needle)) === 0;
	}
	
	
	protected static function url_join($url, $base)
	{
		is_array($base) or $base = parse_url($base);
		is_array($url)  or $url  = parse_url($url);
			
		if(isset($base['path']))
		{
			$path = explode('/', $base['path']);
			$base['path'] = end($path);
			$path[key($path)] = '';
			$base['base'] = join('/', $path);
		}
		
		if(isset($url['path']) AND $url['path'][0] == '/')
		{
			$url['base'] = $url['path'];
			unset($url['path']);
		}
		
		foreach(array('scheme', 'host', 'port', 'base', 'path', 'query', 'fragment') as $key)
		{
			if(isset($url[$key]))
			{
				$base[$key] = $url[$key];
				$found = TRUE;
			}
			elseif( ! empty($found))
			{
				unset($base[$key]);	
			}
			
		}

		if(isset($base['base']))
		{
			$base['path'] = $base['base'] . @$base['path'];
			unset($base['base']);
		}
		
		return self::build_url($base);
	}


	protected static function build_url($parts)
	{
		$url = '';

		if(isset($parts['scheme'])) $url .= $parts['scheme'] . '://';
		if(isset($parts['host'])) $url .= $parts['host'];
		if(isset($parts['port'])) $url .= ':' . $parts['port'];
		if(isset($parts['path'])) $url .= $parts['path'];
		if(isset($parts['query'])) $url .= '?' . $parts['query'];
		if(isset($parts['fragment'])) $url .= '#' . $parts['fragment'];
		
		return $url;
	}
}
