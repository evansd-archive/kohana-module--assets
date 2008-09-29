<?php
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
	
	
	
	public function __construct()
	{
		parent::__construct();
		
		foreach((array) Kohana::config($this->config_file, FALSE, FALSE) as $key => $value)
		{
			if(property_exists($this, $key)) $this->$key = $value;
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
		else
		{
			// Check if the file exists in the application folder
			if ( ! is_file($file = APPPATH.$this->directory.'/'.$path))
			{
				$file = FALSE;
			}
			
		}
		
		// If file not found then display 404
		if( ! $file) Event::run('system.404');
		
		// Load the view in the controller for access to $this
		$output = Kohana::$instance->_kohana_load_view($file, $this->vars);
		
		if( ! empty($this->compress))
		{
			$output = $this->_compress($output, $this->compress);
		}
		
		echo $output;
		
	}
	
	
	
	protected function requires($filename)
	{
		foreach(func_get_args() as $filename)
		{
			// Get extension
			if ($extension = substr(strrchr($filename, '.'), 1))
			{
				$filename = substr($filename, 0, -1 - strlen($extension));
			}
			else
			{
				$extension = $this->extension;
			}
			
			include_once Kohana::find_file($this->directory, $filename, TRUE, $extension);
		}
	}
	
	
	
	protected function assumes($filename)
	{
		// Start output buffer
		ob_start();
		
		foreach(func_get_args() as $filename)
		{
			// Include the assumed files, so they won't get included again
			$this->requires($filename);
		}
		
		// Turn off output buffer and discard contents 
		ob_end_clean();
	}
	
	
	
	protected function _compress($data, $config)
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
