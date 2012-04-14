<?php

class User {
	var $username;
}

class RoomUser extends User {
	var $pass;
	var $vote;
	var $mission;
	var $anonymous_user;
	function __construct($name,$pass){
		$this->username = $name;
		$this->pass     = $pass;
	}
	
}
