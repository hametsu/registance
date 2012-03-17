<?php

require_once "../ajaxpush.php";

class ajaxpushTest extends PHPUnit_Framework_TestCase
{
	public function test_log_data(){
		$room_file = "./test.dat";
		$room_data = file($room_file);
		$room_log = load_room_data($room_data);

		$this->assertSame(count($room_log),2);
		$this->assertSame(count($room_log[0]),4);

		return $room_log;
	}

	/**
	 * @depends test_log_data 
	 */
	public function test_after_time_log($room_log){
		$this->assertSame(count(get_after_post(0,$room_log)),2);
		$this->assertSame(count(get_after_post(10,$room_log)),1);
		$this->assertSame(count(get_after_post(100,$room_log)),0);
	}

	/**
	 * @depends test_log_data
	 */

}
