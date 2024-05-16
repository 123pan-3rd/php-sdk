<?php

namespace Pan123;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Psr7;
use Psr\Http\Message\ResponseInterface;

class HttpReq {
	protected $timeout;
	protected $debug;
	protected $method;
	protected $url;
	protected $body = null;
	public $headers = [];
	public $querys = [];


	public function __construct() {
	}

	public function request($method, $url, $timeout = 0, $debug = false) {
		$this->method = strtoupper($method);
		$this->url = $url;
		$this->timeout = $timeout;
		$this->debug = $debug;
		return $this;
	}

	public function withBody($body) {
		$this->body = $body;

		return $this;
	}

	public function withHeader($header, $value) {
		$header = strtolower(trim($header));

		$this->headers[$header] = $value;
		return $this;
	}

	public function withHeaders($headers) {
		if (is_array($headers)) {
			foreach ($headers as $k => $v) {
				$this->withHeader($k, $v);
			}
		}
		return $this;
	}

	public function withQueryString($query, $value) {
		$query = trim($query);

		$this->querys[$query] = $value;
		return $this;
	}

	public function withQueryStrings($querys) {
		if (is_array($querys)) {
			foreach ($querys as $k => $v) {
				$this->withQueryString($k, $v);
			}
		}
		return $this;
	}

	/**
	 * @return ResponseInterface
	 * @throws GuzzleException
	 */
	public function send() {
		$client = new Client(array(
			"timeout" => $this->timeout,
		));

		$url = $this->url;
		if (!empty($this->querys)) {
			$url .= "?" . http_build_query($this->querys);
		}

		$request = new Psr7\Request(
			$this->method,
			$url,
			$this->headers,
			$this->body
		);

		return $client->send($request, array(
			"debug" => $this->debug
		));
	}
}
