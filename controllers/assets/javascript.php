<?php
/**
 * Copyright (c) 2008-2009 David Evans
 * License: MIT-style (see license.txt)
**/

class JavaScript_Controller extends Assets_Base_Controller {

	// The assets controller will figure this out from the
	// extension, but we might as well save it the trouble
	public $content_type = 'application/x-javascript';

	// Directory where javascript files are stored, relative to APPROOT
	public $directory = 'javascript';

	// Variables to be available to any PHP code embedded
	// in the JS files e.g., a $debug flag
	public $vars = array();

	// Compression settings - off by default
	public $compress = FALSE;

	// Config file to load
	public $config_file = 'javascript';

	// Whether to use Kohana's cascading filesystem to
	// attempt to find the requested file, or just to look
	// in the application directory.
	//
	// You may wish to turn this off when in production to prevent
	// people requesting random files from your Javascript modules.
	//
	// Note that calls to requires() and assumes() ALWAYS use the
	// cascading filesystem.
	public $cascade_request = TRUE;
	
	// List of files that have already been included so we can avoid
	// including them twice
	protected $included_files = array();


	public function __construct()
	{
		parent::__construct();

		foreach((array) Kohana::config($this->config_file, FALSE, FALSE) as $key => $value)
		{
			if (property_exists($this, $key)) $this->$key = $value;
		}
	}


	public function __call($method, $args)
	{
		// Concat all the arguments into a filepath
		array_unshift($args, $method);
		$path = join('/', $args);

		if ($this->cascade_request)
		{
			// Strip the extension from the filepath
			$path = substr($path, 0, -strlen($this->extension) -1);

			// Search for file using cascading file system
			$file = Kohana::find_file($this->directory, $path, FALSE, $this->extension);
		}

		// Check if the file exists in the application folder
		elseif ( ! is_file($file = APPPATH.$this->directory.'/'.$path))
		{
			$file = FALSE;
		}


		// If file not found then display 404
		if( ! $file) Event::run('system.404');

		// Load file, along with all dependencies
		$output = $this->load_and_process($file);


		if( ! empty($this->compress))
		{
			$output = $this->compress($output, $this->compress);
		}

		echo $output;

	}
	
	
	protected function load_and_process($file)
	{	
		list($content, $directives) = $this->parse_file($file);
		
		$includes = array();
		
		foreach($directives as $directive)
		{
			list($command, $argument) = $directive;
			
			if($command == 'requires' OR $command == 'assumes')
			{
				$required_file = $this->parse_argument($argument, $file);
				
				if( ! in_array($required_file, $this->included_files))
				{
					if( ! is_file($required_file)) throw new Kohana_User_Exception('File Not Found', '<tt>'.$required_file.'</tt> does not exists (required by <tt>'.$file.'</tt>)');
					$this->included_files[] = $required_file;
					
					if($command == 'requires')
					{
						$includes[] = $this->load_and_process($required_file);
					}
					// For assumed files, we load them so them so that they and their dependencies
					// get added to the included_files list (and hence don't get included again later)
					// but we discard the contents
					else
					{
						$this->load_and_process($file);
					}
				}
			}
		}
		
		$includes[] = $content;
		
		return join("\n", $includes);
	}
	
	
	protected function parse_file($filename)
	{
		// Load the view in the controller for access to $this
		$content = Kohana::$instance->_kohana_load_view($filename, $this->vars);
		
		// Extract all directives
		preg_match_all('#//= *([a-z]+) +(.*?) *(\n|\r|$)#', $content, $matches, PREG_PATTERN_ORDER);
		
		// Transform into array of arrays of the form (<command>, <argument>)
		$directives = array_map(NULL, $matches[1], $matches[2]);

		return array($content, $directives);
	}
	
	
	protected function parse_argument($argument, $context)
	{
		$first_char = $argument[0];
		$file = trim($argument, '<>"');
		
		switch($first_char)
		{
			case '"':
				$file = dirname($context).'/'.$file.'.'.$this->extension;
				break;
				
			case '<':
				$file = Kohana::find_file($this->directory, $file, FALSE, $this->extension);
				break;
			
			default:
				throw new Kohana_User_Exception('Invalid Argument in JavaScript Directive', '<tt>'.$argument.'</tt> in <tt>'.$context.'</tt> is not a valid argument');
		}
		
		return realpath($file);
	}


	protected function compress($data, $config)
	{
		switch($config === TRUE ? 'jsmin' : $config['type'])
		{
			case 'jsmin':
				include_once Kohana::find_file('vendor', 'JSMin');
				return JSMin::minify($data);

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
