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

	// Directory where javascript files are stored, relative to APPROOT
	public $directory = 'javascript';

	// Variables to be available to any PHP code embedded
	// in the JS files e.g., a $debug flag
	public $vars = array();

	// Compression settings - off by default
	public $compress = FALSE;

	// Config file to load
	public $config_file = 'javascript';
	
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

		// Strip the extension from the filepath
		$path = substr($path, 0, -strlen($this->extension) -1);

		// Search for file using cascading file system
		$file = Kohana::find_file($this->directory, $path, FALSE, $this->extension);
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
		list($lines, $directives) = $this->parse_file($file);
		
		foreach($directives as $index => $directive)
		{
			list($command, $argument) = $directive;
			
			if (in_array($command, array('require', 'requires', 'assume', 'assumes')))
			{
				$required_file = $this->parse_argument($argument, $file);
				$required_content = '';
				
				if( ! in_array($required_file, $this->included_files))
				{
					if( ! is_file($required_file)) throw new Kohana_User_Exception('File Not Found', '<tt>'.$argument.'</tt> does not exist (required by <tt>'.$file.'</tt>)');
					$this->included_files[] = $required_file;
					
					if($command == 'require' OR $command == 'requires')
					{
						$required_content = $this->load_and_process($required_file);
					}
					// For assumed files, we load them so them so that they and their dependencies
					// get added to the included_files list (and hence don't get included again later)
					// but we discard the contents
					else
					{
						$this->load_and_process($required_file);
					}
				}
				
				$lines[$index] = $required_content;
			}
		}
		
		return join("\n", $lines);
	}
	
	
	protected function parse_file($filename)
	{
		// Load the view in the controller for access to $this
		$content = Kohana::$instance->_kohana_load_view($filename, $this->vars);
		
		// Break into lines
		$lines = explode("\n", $content);
		
		// Extract all directives
		$directives = preg_grep('#^\s*//=#', $lines);
		
		// Parse each directive into array of the form (<command>, <argument>)
		foreach($directives as $index => $directive)
		{
			preg_match('#^\s*//= *([a-z]+) +(.*?) *$#', $directive, $matches);
			$directives[$index] = array_slice($matches, 1);
		}

		return array($lines, $directives);
	}
	
	
	protected function parse_argument($argument, $context)
	{
		$first_char = $argument[0];
		$file = trim($argument, '<>"');
		
		switch($first_char)
		{
			case '"':
				$file = $file.'.'.$this->extension;
				// If it's a relative path prepend the current directory
				if($file[0] != '/')
				{
					$file = dirname($context).'/'.$file;
				}
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
