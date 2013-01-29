<?php

/**
 * papprika – small but spicy application toolkit
 *
 * @author Thomas Rasshofer <me@thomasrasshofer.com>
 * @copyright 2013 Thomas Rasshofer
 * @link http://papprika.thomasrasshofer.com/
 * @license http://papprika.thomasrasshofer.com/license
 * @package papprika
 */

namespace papprika {

	// papprika\Application
	class Application {
	
		private $uri;
		private $sub;
		private $insensitive = false;
		private $routes = array();
		private $events = array();
		private $method;
		private $request = array();
		private $last = array();
		private $conditions = array();
	
		// Initialize
		public function __construct($sub = null) {
			$this->sub = rtrim($sub, '/');
			$this->uri = parse_url(urldecode($_SERVER['REQUEST_URI']), PHP_URL_PATH);
			$this->routes['get'] = array();
			$this->routes['post'] = array();
			$this->routes['put'] = array();
			$this->routes['delete'] = array();
			$this->events['before'] = array();
			$this->events['after'] = array();
			$this->events['error'] = array();
			$this->method = strtolower($_SERVER['REQUEST_METHOD']);
			if($this->method == 'put' || $this->method == 'delete') {
				parse_str(file_get_contents('php://input'), $this->request);
			} else if($this->method == 'get') {
				$this->request = $_GET;
			} else if($this->method == 'post') {
				$this->request = $_POST;
			}
		}

		// Case-insensitive routes
		public function insensitive($set) {
			if(is_bool($set)) {
				$this->insensitive = $set;
			}
			return $this;
		}
		
		// Returns selected parameter depending on the request-method
		public function request($key) {
			if(array_key_exists($key, $this->request)) {
				return $this->request[$key];
			}
			return false;
		}
	
		// GET-Route
		public function get() {
			return $this->route('get', func_get_args());
		}
	
		// POST-Route	
		public function post() {
			return $this->route('post', func_get_args());
		}
	
		// PUT-Route
		public function put() {
			return $this->route('put', func_get_args());
		}
	
		// DELETE-Route
		public function delete() {
			return $this->route('delete', func_get_args());
		}
	
		// Any route (GET, POST, PUT or DELETE)
		public function any() {
			return $this->route('any', func_get_args());
		}

		// Add routes from a configuration file
		public function ini($file) {
			if(file_exists($file) && function_exists('parse_ini_file')) {
				$ini = parse_ini_file($file, true);
				if($ini) {
					$ini = array_change_key_case($ini, CASE_LOWER);
					foreach(array('get', 'post', 'put', 'delete', 'any') as $method) {
						if(array_key_exists($method, $ini)) {
							foreach($ini[$method] as $pattern => $callback) {
								$this->$method($pattern, $callback);
							}
						}	
					}
				}
			}
			return $this;
		}

		// Add route
		private function route($method, $arguments) {
			$patterns = array_shift($arguments);
			$callbacks = array();
			if(count($arguments) > 0) {
				foreach($arguments as $argument) {
					if(is_callable($argument)) {
						$callbacks[] = $argument;
					}
				}
			}
			if(count($callbacks) > 0) {
				$methods = ($method == 'any') ? array('get', 'post', 'put', 'delete') : array($method);
				foreach($methods as $method) {
					if(is_array($patterns)) {
						foreach($patterns as $pattern) {
							$this->routes[$method][$pattern] = $callbacks;
						}
						$this->last = $patterns;
					} else {
						$this->routes[$method][$patterns] = $callbacks;
						$this->last = array($patterns);
					}
				}
			}			
			return $this;
		}
	
		// Before-Event
		public function before() {
			return $this->event('before', func_get_args());
		}
	
		// After-Event
		public function after() {
			return $this->event('after', func_get_args());
		}
	
		// Error-Event
		public function error() {
			return $this->event('error', func_get_args());
		}
	
		// Add event
		private function event($event, $arguments) {
			$callbacks = array();
			if(count($arguments) > 0) {
				foreach($arguments as $argument) {
					if(is_callable($argument)) {
						$callbacks[] = $argument;
					}
				}
			}
			if(count($callbacks) > 0) {
				$this->events[$event] = $callbacks;
			}
			return $this;
		}
	
		// Triggers events
		private function trigger($type) {
			foreach($this->events[$type] as $event) {
				call_user_func($event);
			}
		}
	
		// Conditions
		public function assert($variable, $condition) {
			if(!empty($this->last)) {
				foreach($this->last as $pattern) {
					$this->conditions[$pattern][] = array($variable, $condition);
				}
			}
			return $this;
		}
	
		// Run application
		public function run() {
			$found = false;
			$this->trigger('before');
			$modifier = $this->insensitive ? 'i' : '';			
			foreach($this->routes[$this->method] as $pattern => $callbacks) {
				$route = array();
				$variables = array();
				$conditions = array_key_exists($pattern, $this->conditions) ? $this->conditions[$pattern] : array();
				$pattern = $this->sub.$pattern;
				foreach(preg_split('~/~', $pattern, -1) as $part) {
					preg_match('/^\:([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$/', $part, $matches);		
					if(!empty($matches[1])) {
						$variables[] = $matches[1];
						$route[] = '([^\/]+)';
					} else {
						$route[] = preg_quote($part);
					}
				}
				if(preg_match('/^'.implode('\/', $route).'$/'.$modifier, $this->uri)) {
					$parameters = array();
					preg_match_all('/^'.implode('\/', $route).'$/'.$modifier, $this->uri, $matches, PREG_SET_ORDER);
					if(!empty($matches)) {
						$matches = array_slice($matches[0], 1);
						$i = 0;
						foreach($matches as $match) {
							$parameters[$variables[$i]] = $match;
							$i++;
						}
					}
					foreach($conditions as $condition) {
						if(!preg_match('/^'.$condition[1].'$/', $parameters[$condition[0]])) {
							continue 2;
						}
					}
					foreach($callbacks as $callback) {
						call_user_func_array($callback, $parameters);
					}
					$found = true;
					continue;
				}
			}
			if(!$found) {
				$this->trigger('error');
			}
			$this->trigger('after');
		}
	
	}
	
	// papprika\Template
	class Template {
	
		protected $_file = '';
		protected $_data;
		protected $_output = '';
	
		// Initialize
		public function __construct($file, $data = array()) {
			if(!is_file($file)) {
				throw new \Exception('Template "'.$file.'" couldn\'t be found.');
			}
			$this->_file = $file;
			if(!empty($data)) {
				$this->_data = new \stdClass();
				foreach($data as $key => $value) {
					$this->_data->$key = $value;
				}
			}
		}
	
		// Get variable(s)
		public function __get($name) {
			if(substr($name, 0, 1) == '_') {
				throw new \Exception('Variable-names aren\'t allowed to start with an underscore.');
			}
			return $this->_data->$name;
		}
	
		// Set variable(s)
		public function __set($key, $value) {
			if(substr($key, 0, 1) == '_') {
				throw new \Exception('Variable-names aren\'t allowed to start with an underscore ('.$key.').');
			}
			$this->_data->$key = $value;
		}
	
		// Output template
		public function __toString() {
			if(!$this->_output) {
				$this->parse();
			}
			return $this->_output;
		}
	
		// Parse template
		private function parse() {
			$this->beforeParse();
			ob_start();
			include($this->_file);
			$this->_output = ob_get_contents();		
			ob_end_clean();
			$this->afterParse();
		}
		
		// Extend papprika\Template and define if you want to do something before parsing the template
		private function beforeParse() { }
	
		// Extend papprika\Template and define if you want to do something after parsing the template
		private function afterParse() { }
			
	}

	// papprika\File
	class File {
	
		private $file;
		private $name;
		private $filename;
		private $extension;
		private $modified;
		private $size;

		private $width;
		private $height;
		private $mime;
	
		public function __construct($file) {
			if(!is_file($file)) {
				throw new \Exception('File "'.$file.'" couldn\'t be found.');
			}
			$info = pathinfo($file);
			$this->file = $file;
			$this->name = $info['filename'];
			$this->filename = basename($file);
			$this->extension = $info['extension'];
			$this->modified = filemtime($file);
			$this->size = filesize($file);
			$size = getimagesize($file);
			if($size) {
				$this->width = $size[0];
				$this->height = $size[1];
				$this->mime = $size['mime'];
			}
		}
	
		public function name() {
			return $this->name;
		}
		
		public function filename() {
			return $this->filename;
		}
		
		public function extension() {
			return $this->extension;
		}
		
		public function url() {
			return $this->url;
		}
		
		public function modified() {
			return $this->modified;
		}
		
		public function size() {
			return $this->size;
		}
		
		public function niceSize($decimals = 2, $decimalPoint = '.', $thousandsSeparator = '') {
			$sizes = array(' B', ' KB', ' MB', ' GB', ' TB', ' PB', ' EB', ' ZB', ' YB');
			if(empty($this->size)) {
				return '0.00 MB';
			} else {
				return (number_format($this->size/pow(1024, ($i = floor(log($this->size, 1024)))), $decimals, $decimalPoint, $thousandsSeparator).$sizes[$i]);
			}
		}

		public function width() {
			return $this->width;
		}

		public function height() {
			return $this->height;
		}

		public function mime() {
			return $this->mime;
		}

		public function max($max) { 
			if($this->width > $this->height) {
				$this->maxWidth($max);
			} else {
				$this->maxHeight($max);
			}
		}

		public function maxWidth($max) { 	
			if($this->width > $max) {
				$this->height = round($max/$this->width*$this->height);
				$this->width = $max;
			}
		}  

		public function maxHeight($max) { 	
			if($this->height > $max) {
				$this->width = round($max/$this->height*$this->width);
				$this->height = $max;
			}
		}

	}

}

namespace papprika\MySQL {

	// papprika\MySQL\Connection
	class Connection {
	
		public $mysqli = null;
	
		// Initialize
		public function __construct($server, $username, $password, $database) {
			$mysqli = new \MySQLi($server, $username, $password, $database);
			if($mysqli->connect_errno) {
				throw new \Exception('Couldn\'t connect to MySQL ('.$mysqli->connect_error.').');
			}
			$this->mysqli = $mysqli;
		}
				
		// Set charset
		public function charset($charset) {
			return $this->mysqli->set_charset($charset);	
		}
	
	}
	
	// papprika\MySQL\Query
	class Query {
	
		private $mysqli;
		private $result;
		
		// Initialize
		public function __construct() {
			$arguments = func_get_args();
			$query = array_shift($arguments);
			$mysqli = array_pop($arguments);
			if(get_class($mysqli) != 'papprika\MySQL\Connection') {
				throw new \Exception('Missing MySQL-Connection-Link.');
			}
			$this->mysqli = $mysqli->mysqli;
			if(count($arguments) > 0) {
				foreach($arguments as $key => $val) {
					$arguments[$key] = $this->mysqli->real_escape_string($val);
				}
				$query = vsprintf($query, $arguments);
			}
			$result = $this->mysqli->query($query);
			if(!$result) {
				throw new \Exception('Invalid MySQL-Query ('.$this->mysqli->error.').');
			}
			$this->result = $result;
			return $this;
		}
		
		public function __destruct() {
			if(is_resource($this->result)) {
				$this->result->close();
			}
		}
	
		public function fetch() {
			return $this->result->fetch_object();
		}
		
		public function id() {
			return $this->mysqli->insert_id;	
		}
		
		public function rows() {
			return $this->result->num_rows;
		}
	
		public function affected() {
			return $this->result->affected_rows;
		}
	
	}

}

?>