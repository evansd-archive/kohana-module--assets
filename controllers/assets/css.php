<?php
// Kohana::__autoload can't figure this out by itself
include_once Kohana::find_file('controllers/assets', 'javascript', TRUE);

class CSS_Controller extends Javascript_Controller {
	
	// the assets controller will figure this out from the extension, but we might as well save it the trouble
	public $content_type = 'text/css';
	
	// directory where CSS files are stored, relative to APPROOT
	public $directory = 'css';
	
	// config file to load
	public $config_file = 'css';
	
	
	
	protected function _compress($data, $config)
	{
		switch($config === TRUE ? 'strip' : $config['type'])
		{
			case 'strip': // Pilfered from the Kohana media module: controllers/media.php
			
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
				
			default:
				throw new Kohana_User_Exception('Unknown CSS Compression Type', 'Unknown type: '.$config['type']);
		}
	}
	
}
