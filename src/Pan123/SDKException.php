<?php

namespace Pan123;

/**
 * Exception: SDKException
 */
class SDKException extends \Exception {
	protected $traceID;

	public function __construct($message, $code = 0, $traceID = "no_trace_id") {
		$this->traceID = $traceID;
		parent::__construct($message, $code);
	}

	public function __toString() {
		return __CLASS__ . ": [{$this->code}]({$this->traceID}): {$this->message}";
	}

	/**
	 * 接口响应异常需要技术支持时请提供此x-traceID
	 *
	 * @return mixed|string x-traceID
	 */
	public function getTraceID() {
		return $this->traceID;
	}
}