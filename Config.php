<?php

class Config {

	private $configParams = array();

	function __construct() {
		global $theRealConfigParams;
		
		$this->configParams = $theRealConfigParams;

	}

	function getConf($key) {
		return @$this->configParams[$key];
	}

}
