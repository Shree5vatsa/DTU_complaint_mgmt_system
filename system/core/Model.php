<?php

defined('BASEPATH') OR exit('No direct script access allowed');

class CI_Model {

	public function __construct() {}

	public function __get($key)
	{

		return get_instance()->$key;
	}

}
