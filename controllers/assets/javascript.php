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

	// Directory where JavasSript files are stored, relative to
	// application or module root
	public $directory = 'javascript';

	// Variables to be available to any PHP code embedded
	// in the JS files e.g., a $debug flag
	public $vars = array();

	// Compression settings - off by default
	public $compress = FALSE;


	public function __construct()
	{
		parent::__construct();

		foreach((array) Kohana::config('javascript', FALSE, FALSE) as $key => $value)
		{
			if (property_exists($this, $key)) $this->$key = $value;
		}
	}


	public function __call($method, $args)
	{
		// Concat all the arguments into a filepath
		array_unshift($args, $method);
		$path = join(DIRECTORY_SEPARATOR, $args);

		// Strip the extension from the filepath
		$path = substr($path, 0, -strlen($this->extension) - 1);

		// Search for file using cascading file system
		$filename = Kohana::find_file($this->directory, $path, FALSE, $this->extension);
		if( ! $filename) Event::run('system.404');

		// Load file, along with all dependencies
		$output = $this->requires($filename);

		if( ! empty($this->compress))
		{
			$output = $this->compress($output, $this->compress);
		}

		echo $output;
	}
	
	
	protected function requires($filename, &$included_files = array())
	{
		// Parse the file to extract a list of directives
		list($lines, $directives) = $this->parse_file($filename);
		
		foreach($directives as $index => $directive)
		{
			$lines[$index] = $this->process_directive($directive, $filename, $included_files);
		}
		
		return join("\n", $lines);
	}
	
	
	protected function process_directive($directive, $path_context, &$included_files)
	{
		// Find the specified file (throws an exeception if it can't be found)
		$filename = $this->find_file($directive['path'], $directive['search_include_paths'], $path_context);
		
		// If it's not already included ..
		if( ! in_array($filename, $included_files))
		{
			// ... add it to the list ...
			$included_files[] = $filename;
			
			// ... and execute the command.
			switch($directive['command'])
			{
				case 'requires':
					return $this->requires($filename, $included_files);
				
				case 'assumes';
					// For assumed files, we run the pre-processor on them, but discard the contents
					// and just merge their dependencies into the included files list so they won't
					// get included later, even if another file requires them.
					$this->requires($filename, $included_files);
					return '';
			}
		}
	}
	
	
	protected function parse_file($filename)
	{
		// Load the view in the controller for access to $this
		$content = Kohana::$instance->_kohana_load_view($filename, $this->vars);
		
		// Break into lines
		$lines = explode("\n", $content);
		
		// Extract all directives.
		// We use a loop rather than array_map because we need to maintain
		// index association and we have integer keys
		$directives = array();
		
		foreach(preg_grep('#^\s*//=#', $lines) as $index => $line)
		{
			if($directive = $this->parse_directive($line))
			{
				$directives[$index] = $directive;
			}
		}

		return array($lines, $directives);
	}
	
	
	protected function parse_directive($directive)
	{
		if (preg_match('#^\s*//= *([a-z]+) +("(.*?)"|<(.*?)>) *$#', $directive, $matches))
		{
			if($command = $this->command_from_alias($matches[1]))
			{
				return array
				(
					'command'              => $command,
					'search_include_paths' => isset($matches[4]),
					'path'                 => end($matches),
				);
			}
		}
		
		return NULL;
	}
	
	
	protected function command_from_alias($alias)
	{
		switch($alias)
		{
			case 'require':
			case 'requires':
			case 'include':
			case 'includes':
				return 'requires';
			
			case 'assume':
			case 'assumes':
				return 'assumes';
			
			default:
				return NULL;
		}
	}
	
	
	protected function find_file($path, $search_include_paths, $context)
	{
		if ($search_include_paths)
		{
			$file = Kohana::find_file($this->directory, $path, FALSE, $this->extension);
			if( ! $file)
			{
				$cant_find = $this->directory.DIRECTORY_SEPARATOR.$path.'.'.$this->extension;
			}
		}
		else
		{
			// If the path is relative, prepend the appropriate directory
			$file = ($this->path_is_relative($path) ? dirname($context).DIRECTORY_SEPARATOR : '').$path.'.'.$this->extension;
			if ( ! file_exists($file))
			{
				$cant_find = $file;
			}
		}
		
		if (isset($cant_find))
		{
			throw new Kohana_User_Exception('Sprockets Missing File', "File <tt>$cant_find</tt> could not be found. (Required by <tt>$context</tt>)");
		}
		
		return realpath($file);
	}
	
	
	protected function path_is_relative($path)
	{
		if ( ! KOHANA_IS_WIN)
		{
			return ($path[0] != DIRECTORY_SEPARATOR);
		}
		else
		{
			return
			(
				$path[0] != DIRECTORY_SEPARATOR
				AND
				! (ctype_alpha($path[0]) AND $path[1] == ':' AND $path[2] == DIRECTORY_SEPARATOR)
			);
		}
	}


	protected function compress($data, $config)
	{
		switch($config === TRUE ? 'jsminplus' : $config['type'])
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
