<?php

/**
 * papprika – small but spicy application toolkit
 *
 * @author Thomas Rasshofer <tr@papprika.org>
 * @copyright 2012 Thomas Rasshofer
 * @link http://papprika.org/
 * @license http://papprika.org/license
 * @version 0.1
 * @package papprika
 */

namespace papprika;

class Application {

	private $uri;
	private $sub;
	private $routes = array();
	private $events = array();
	private $method;
	private $request = array();

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
			} else {
				$this->routes[$method][$patterns] = $callbacks;
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

	// Run application
	public function run() {
		$found = false;
		foreach($this->routes[$this->method] AS $pattern => $callbacks) {
			$route = array();
			$variables = array();
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
				$this->trigger('before');
				$parameters = array();
				preg_match_all('/^\/'.implode('\/', $route).'$/', $this->uri, $matches);
				if(!empty($matches[1])) {
					$i = 0;
					foreach($matches[1] AS $match) {
						$parameters[$variables[$i]] = $match;
						$i++;
					}
				}
				foreach($callbacks AS $callback) {
					call_user_func_array($callback, $parameters);
				}
				$this->trigger('after');
				$found = true;
				break;
			}
		}
		if(!$found) {
			$this->trigger('error');
		}
	}

}

?>