<?php
require_once("./room_info_test.php");

class ExpandsTest extends RoomInfoTest
{

	public function loadfile() {

		$file_name = "newtest.dat";
		$roominfo = RoomInfo::getInstance();
		$roominfo->loadfile($file_name,TRUE);

		return $roominfo;
	
	}

	public function test_is_apply_expands(){

		$roominfo = $this->loadfile();
		$this->assertTrue($roominfo->is_apply_expands());

		return $roominfo;
	}

	public function add_user($roominfo,$user_number) {

		for ($i = 0;$i < $user_number;$i++){
			$roominfo->add_user("User" . rand() , "pass" . rand());
		}

		return $roominfo;
	}

	/**
	 * @depends test_is_apply_expands
	 */
	public function test_get_card_number($roominfo) {
		$roominfo = $this->add_user($roominfo,2);
		$this->assertSame(count($roominfo->get_users()),5);
		$roominfo->reset_expands(true);
		$roominfo->set_expands();
		$this->assertSame(count($roominfo->get_expand_leader()),1);

		$roominfo = $this->add_user($roominfo,2);
		$roominfo->set_expands();
		$this->assertSame(count($roominfo->get_expand_leader()),2);

		$roominfo = $this->add_user($roominfo,2);
		$roominfo->set_expands();
		$this->assertSame(count($roominfo->get_expand_leader()),3);
	}

	/**
	 * @depends test_get_card_number
	 */
	public function test_leader_have_expand_cards() {
		$roominfo = $this->loadfile();
		$this->assertTrue($roominfo->leader_have_expand_cards());
		$roominfo->reset_expands(true);
		$roominfo->set_expands();
		$this->assertTrue($roominfo->leader_have_expand_cards());
		return $roominfo;
	}

	public function test_parse_expands_data_to_user() {
		$roominfo = $this->loadfile();
		$roominfo->parse_expands_data_to_user();
		$this->assertSame($roominfo->get_user(0)->have_expand_card[0],"Hoge");
		$this->assertSame($roominfo->get_user(1)->have_expand_card[1],"Fuga");
		$this->assertSame(count($roominfo->get_user(2)->have_expand_card),0);
		$this->assertSame($roominfo->get_user(1)->effect_expand_card[0],"Mokkori");
		return $roominfo;
	}

	/**
	 * @depends test_leader_have_expand_cards
	 */
	public function test_parse_expands_user_to_data($roominfo) {
		$pre_room_data = $roominfo->get_raw_roomdata();
		$roominfo->parse_expands_user_to_data();
		$check_room_data = $roominfo->get_raw_roomdata();
		$this->assertSame($pre_room_data[20],$check_room_data[20]);
	}

	/**
	 * @depends test_leader_have_expand_cards
	 */
	public function test_give_expands_user($roominfo) {
		$pre_room_data = $roominfo->get_raw_roomdata();
		$roominfo->set_user_anonymous_name();
		$roominfo->give_expands_user("User1","Hoge");
		$this->assertSame($roominfo->get_user(0)->have_expand_card[1],"Hoge");
		$this->assertSame(count($roominfo->get_expand_leader()),1);
		$this->assertTrue($roominfo->get_user(0)->given_card);

		$roominfo->give_expands_user("User1","Mokkori");
		$this->assertSame(count($roominfo->get_user(0)->have_expand_card),2);
		$this->assertSame(count($roominfo->get_expand_leader()),1);

		$roominfo->give_expands_user("User9999","Fuga");
		$this->assertSame(count($roominfo->get_expand_leader()),1);
	
		$roominfo->give_expands_user("User1","Fuga");
		$this->assertSame(count($roominfo->get_user(0)->have_expand_card),2);
		$this->assertSame(count($roominfo->get_expand_leader()),1);
		
		$roominfo->parse_expands_data_to_user();
		$this->assertTrue($roominfo->get_user(0)->given_card);
		return $roominfo;
	}

	/**
	 * @depends test_give_expands_user
	 */
	public function test_reset_expands_user($roominfo) {
		$this->assertTrue($roominfo->get_user(0)->given_card);
		$roominfo->set_scene("team");
		$this->assertFalse($roominfo->get_user(0)->given_card);
	}	

	public function test_use_expands_user() {
		$roominfo = $this->loadfile();
		$roominfo->parse_expands_data_to_user();
		$this->assertTrue($roominfo->use_expands_user("User1","Hoge"));
		$this->assertSame(count($roominfo->get_user(0)->have_expand_card),0);
		$this->assertTrue(!$roominfo->use_expands_user("User2","Mokkori"));
		$this->assertSame(count($roominfo->get_user(1)->have_expand_card),2);
		$room_data = $roominfo->get_raw_roomdata();
		$this->assertSame("name,User1,name,User2,card,Hoge,card,Fuga,effect,Mokkori,name,User3\n",$room_data[20]);
		$this->assertSame("\n",$room_data[18]);
	}

	public function test_set_use_card_conform() {
		$roominfo = $this->loadfile();
		$roominfo->parse_expands_data_to_user();
		$this->assertFalse($roominfo->get_user(0)->conform_use_card);
		$roominfo->set_user_conform("User1");
		$this->assertTrue($roominfo->get_user(0)->conform_use_card);
		
		$roominfo->parse_expands_user_to_data();
		$roominfo->parse_expands_data_to_user();

		$this->assertTrue($roominfo->get_user(0)->conform_use_card);
		
		$roominfo->set_scene("Hoge");
		$this->assertFalse($roominfo->get_user(0)->conform_use_card);
	}

}
