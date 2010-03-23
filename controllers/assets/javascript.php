<?php
/**
 * Copyright (c) 2008-2009 David Evans
 * License: MIT-style (see license.txt)
**/

class JavaScript_Controller extends Assets_Base_Controller
{
	// The assets controller will figure this out from the
	// extension, but we might as well save it the trouble
	public $content_type = 'application/x-javascript';

	// Directory where JavaSript files are stored, relative to
	// application or module root
	public $directory = 'javascript';

	// Variables to be available to any PHP code embedded
	// in the JS files e.g., a $debug flag
	public $vars = array();

	// Whether to compress output
	public $compress = FALSE;
	
	// How to compress output
	public $compress_config = array();
	
	// Paths where the preprocessor should look for JavaScript files
	public $include_paths = array();


	public function __construct()
	{
		parent::__construct();
		
		// Get config settings
		$config = Kohana::config('javascript', FALSE, FALSE);
		
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
		$path = join('/', $args);

		// Strip the extension from the filepath
		$path = substr($path, 0, -strlen($this->extension) - 1);

		// Search for file using cascading file system
		$filename = Kohana::find_file($this->directory, $path, FALSE, $this->extension);
		if ( ! $filename) Event::run('system.404');
		
		// Add all the module JavaScript directories to the include paths list
		foreach(Kohana::include_paths() as $path)
		{
			$this->include_paths[] = $path.$this->directory;
		}
		
		// Load file, along with all dependencies
		$preprocessor = new JavaScriptPreprocessor($this->include_paths, $this->vars);
		$output = $preprocessor->requires($filename);
		
		// Compress JavaScript, if desired
		if ($this->compress)
		{
			$output = $this->compress($output, $this->compress_config);
		}

		echo $output;
	}
	

	protected function compress($data, $config)
	{
		switch($config['type'])
		{
			case 'jsmin':
				include_once Kohana::find_file('vendor', 'JSMin');
				return JSMin::minify($data);
				
			case 'jsminplus':
				include_once Kohana::find_file('vendor', 'jsminplus');
				return JSMinPlus::minify($data);

			case 'packer':
				include_once Kohana::find_file('vendor', 'JavaScriptPacker');
				$packer = new JavaScriptPacker($data, empty($config['level']) ? 'Normal' : $config['level']);
				return $packer->pack();

			case 'yuicompressor':
				$options = isset($config['options']) ? $config['options'] : '';
				return yuicompressor::compress($data, 'js', $options);


			default:
				throw new Kohana_User_Exception('Unknown Javascript Compression Type', 'Unknown type: '.$config['type']);
		}
	}
}
