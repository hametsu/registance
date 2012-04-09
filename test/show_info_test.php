<?php
require("../lib/room_info.php");
require("../lib/show_info.php");

class ShowInfoTest extends PHPUnit_Framework_TestCase
{
	public function test_showfile(){
		$file_name = "./newtest.dat";
		$roominfo = RoomInfo::getInstance();
		$roominfo->loadfile($file_name,TRUE);
		$showinfo = ShowInfo::getInstance();
		//擬似セッションの作成
		$_SESSION = array("name" . $roominfo->get_filename() => "User1");
		//デバックモードはFalse
		$debug = FALSE;

		$showinfo->initialization($roominfo,$_SESSION,$debug);
		
	}

}
