<?php

class User {
	var $username;
}

class RoomUser extends User {
	var $pass;
	var $vote;
	var $mission;
	var $anonymous_user;
	var $given_card;
	var $have_expand_card;
	var $effect_expand_card;
	var $conform_use_card;
	var $use_expand_card;
	function __construct($name,$pass){
		$this->username = $name;
		$this->pass     = $pass;
		$this->given_card = false;
		$this->conform_use_card = false;
	}

	
}
