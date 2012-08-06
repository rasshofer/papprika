<?php

/**
 * papprika – small but spicy application toolkit
 *
 * @author Thomas Rasshofer <tr@papprika.org>
 * @copyright 2012 Thomas Rasshofer
 * @link http://papprika.org/
 * @license http://papprika.org/license
 * @version 0.2
 * @package papprika
 */

namespace papprika {

	// papprika\Application
	class Application {
	
		private $uri;
		private $sub;
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
	
		// Add route
		private function route($method, $arguments) {
			$patterns = array_shift($arguments);
			$callbacks = array();
			if(count($arguments) > 0) {
				foreach($arguments AS $argument) {
					if(is_callable($argument)) {
						$callbacks[] = $argument;
					}
				}
			}
			if(count($callbacks) > 0) {
				if(is_array($patterns)) {
					foreach($patterns AS $pattern) {
						$this->routes[$method][$pattern] = $callbacks;
					}
					$this->last = $patterns;
				} else {
					$this->routes[$method][$patterns] = $callbacks;
					$this->last = array($patterns);
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
				foreach($arguments AS $argument) {
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
			foreach($this->events[$type] AS $event) {
				call_user_func($event);
			}
		}
	
		// Simple unit testing
		public function assert($variable, $condition) {
			if(!empty($this->last)) {
				foreach($this->last AS $pattern) {
					$this->conditions[$pattern][] = array($variable, $condition);
				}
			}
			return $this;
		}
	
		// Run application
		public function run() {
			$found = false;
			$this->trigger('before');
			foreach($this->routes[$this->method] AS $pattern => $callbacks) {
				$route = array();
				$variables = array();
				$conditions = $this->conditions[$pattern] ?: array();
				$pattern = $this->sub.$pattern;
				foreach(preg_split('~/~', $pattern, -1, PREG_SPLIT_NO_EMPTY) AS $part) {
					preg_match('/^\:([a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*)$/', $part, $matches);		
					if(!empty($matches[1])) {
						$variables[] = $matches[1];
						$route[] = '([^\/]+)';
					} else {
						$route[] = preg_quote($part);
					}
				}
				if(preg_match('/^\/'.implode('\/', $route).'$/', $this->uri)) {
					$parameters = array();
					preg_match_all('/^\/'.implode('\/', $route).'$/', $this->uri, $matches);
					if(!empty($matches[1])) {
						$i = 0;
						foreach($matches[1] AS $match) {
							$parameters[$variables[$i]] = $match;
							$i++;
						}
					}
					foreach($conditions AS $condition) {
						if(!preg_match('/^'.$condition[1].'$/', $parameters[$condition[0]])) {
							continue 2;
						}
					}
					foreach($callbacks AS $callback) {
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
				foreach($data AS $key => $value) {
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

}

namespace papprika\MySQL {

	// papprika\MySQL\Connection
	class Connection {
	
		private $link = null;
	
		// Initialize
		public function __construct($server, $username, $password, $database) {
			$link = @mysql_connect($server, $username, $password);
			if(!$link) {
				throw new \Exception('Couldn\'t connect to MySQL ('.mysql_error().').');
			}
			if(!@mysql_select_db($database, $link)) {
				throw new \Exception('Couldn\'t use Database "'.$database.'" ('.mysql_error().').');
			}
			$this->link = $link;
		}
	
		// Close connection
		public function __destruct() {
			return mysql_close($this->link);
			return false;
		}
		
		// Get link
		public function get() {
			return $this->link;
		}
		
		// Set charset
		public function charset($charset) {
			return mysql_set_charset($charset, $this->link);	
		}
	
	}
	
	// papprika\MySQL\Query
	class Query {
	
		private $link;
		private $result;
		
		// Initialize
		public function __construct() {
			$arguments = func_get_args();
			$query = array_shift($arguments);
			$link = array_pop($arguments);
			if(get_class($link) != 'papprika\MySQL\Connection') {
				throw new \Exception('Missing MySQL-Connection-Link.');
			}
			$this->link = $link->get();
			if(count($arguments) > 0) {
				foreach($arguments AS $key => $val) {
					$arguments[$key] = mysql_real_escape_string($val, $this->link);
				}
				$query = vsprintf($query, $arguments);
			}
			$result = mysql_query($query, $this->link);
			if(!$result) {
				throw new \Exception('Invalid MySQL-Query ('.mysql_error().').');
			}
			$this->result = $result;
			return $this;
		}
		
		public function __destruct() {
			mysql_free_result($this->result);
		}
	
		public function fetch() {
			return mysql_fetch_object($this->result);	
		}
		
		public function id() {
			return mysql_insert_id($this->link);	
		}
		
		public function rows() {
			return mysql_num_rows($this->result);	
		}
	
		public function affected() {
			return mysql_affected_rows($this->link);	
		}
	
	}

}

?>