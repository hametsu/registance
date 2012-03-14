<?php
require_once "../lib/registance.php";
require_once "../lib/setlist.php";

class RegistanceTest extends PHPUnit_Framework_TestCase
{
	public function test_init_room_data(){
		
		$room_file = "./test.dat";
		$room_data = file($room_file);
		$room_info = init_room_data($room_data,$room_file);

		$this->assertSame($room_info['name'],"部屋ネーム");
		$this->assertSame($room_info['states'],"prosessing");
		$this->assertSame(count($room_info['users']),3);
		$this->assertSame($room_info['userrole'][0],"User3");
		$this->assertSame($room_info['people'],3);
		$this->assertSame($room_info['scene'],"vote");
		$this->assertSame($room_info['mission'],1);
		$this->assertSame($room_info['now_leader'],"User1");
		$this->assertSame(count($room_info['not_leader']),2);
		$this->assertSame(count($room_info['team_member']),2);
		
		$this->assertSame($room_info['vote_user'][0][0],"User1");
		$this->assertSame($room_info['vote_user'][0][1],"trust");

		$this->assertSame($room_info['vote_user'][1][0],"User2");
		$this->assertSame($room_info['vote_user'][1][1],"veto");

		$this->assertSame($room_info['mission_user'][0],"User1");
		$this->assertSame($room_info['mission_vote'][0],"success");

		$this->assertSame(count($room_info['victory_point']),2);
		$this->assertSame($room_info['mission_victory'],"");

		return $room_info;
	}

	public function test_escape_string()
	{
	
		$this->assertSame(escape_string("<h1>ほげ</h1>",20),"ほげ");
	
	}
	
	/**
	 * @depends test_init_room_data
	 */
	public function test_count_victory($room_info)
	{
		$result = count_victory($room_info['victory_point']);
		$this->assertEquals($result['success'],1);
		$this->assertEquals($result['not_success'],1);
	}

	/**
	 * @depends test_init_room_data
	 */
	public function test_is_your_leader($room_info){
		$this->assertTrue(is_your_leader($room_info,set_session_name("User1")));
		$this->assertFalse(is_your_leader($room_info,set_session_name("User2")));
	}

	/**
	 * @depends test_init_room_data
	 */
	public function test_is_your_connection($room_info){

		$this->assertTrue(is_your_connection($room_info,set_session_name("User1")));
		$this->assertFalse(is_your_connection($room_info,set_session_name("User99")));

	}

	/**
	 * @depends test_init_room_data
	 */
	public function test_is_team($room_info){

		$is_team = set_team_list($room_info);
		$this->assertSame(count($is_team),3);
		$this->assertTrue($is_team['User3']);
		$this->assertFalse($is_team['User2']);
	}

	/**
	 * @depends test_init_room_data
	 */
	public function test_is_mission($room_info){
		$this->assertSame(set_is_mission($room_info),array());
		$room_info['scene'] = "mission";
	}

	/**
	 * @depends test_init_room_data
	 */

	public function test_is_vote($room_info){
		$is_vote = set_is_vote($room_info);
		$this->assertSame(count($is_vote),3);
	}

	public function test_room_info_to_room_data(){
		$test_room_data = array();
		$room_info = array("states" => "end"
							 ,"scene" => "end"
							 ,"mission_victory"=>"registance");
		$test_room_data = room_info_to_room_data($room_info,$test_room_data);

		$this->assertSame($test_room_data[1],"end\n");
		$this->assertSame($test_room_data[5],"end\n");
		$this->assertSame($test_room_data[15],"registance\n");
	}
}


function set_session_name($_name)
{
	return array("name./test.dat" => $_name);
}

?>
