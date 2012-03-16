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
	public function test_vote_user_to_string($room_info){

		$room_data = file("./test.dat");
		$this->assertSame(vote_user_to_string($room_info['vote_user']),$room_data[10]);
	
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

	/**
	 * @depends test_init_room_data
	 */

	public function test_room_info_to_room_data($room_info){

		$room_data = file("./test.dat");
		
		$test_room_data = array();
		$test_room_data = room_info_to_room_data($room_info,$test_room_data);

		for ($i = 0;$i < count($test_room_data);$i++){
			if ($test_room_data[$i] !== $room_data[$i]){
				echo "\nFailed Room_data is No." . $i . "\n"; 
			}
			$this->assertSame($test_room_data[$i],$room_data[$i]);
		}
	}

	/**
	 * @depends test_init_room_data
	 */

	public function test_init_waiting_to_processing($room_info){

	$room_info = set_waiting_to_processing($room_info);

	$this->assertSame($room_info['mission'],1);
	$this->assertSame($room_info['not_leader'],$room_info['users']);
	$this->assertSame(count($room_info['userrole']),1);

	return $room_info;

	}

	/**
	 * @depends test_init_waiting_to_processing
	 */
	public function test_set_scene_team($room_info){

		//シーン : Teamの初期化
		//
		// 初期化するべきこと
		//
		//   === 共通 ===
		//
		//　・新しいリーダーの選出
		//     ->$room_data['not_leader']からメンバーは一人減っているか？
		//     ->$room_data['now_leader']には、ちゃんとメンバーが代入されているか？
		//        ->そのメンバーとは、"\n"とか""の類ではないか？
		//    
		//
		//　・チームの解散
		//   * scene "mission" から遷移した場合のみ、ミッションナンバーをプラスする
		//　・ミッション投票メンバーの初期化
		//　・ミッション投票の初期化
		//

		$not_leader_count = count($room_info['not_leader']);
		$premission = $room_info['mission'];

		$room_info = set_scene("team",$room_info);
		$this->assertSame($room_info['scene'],"team");
		$this->assertSame($room_info['mission'],$premission);
		$this->assertTrue(($room_info['now_leader'] !== "\n") && ($room_info['now_leader'] !== ""));
		$this->assertSame($not_leader_count - 1,count($room_info['not_leader']));

		//何も入っているべきではない関数群
		$this->assertEmpty($room_info['team_member']);
		$this->assertEmpty($room_info['mission_user']);
		$this->assertEmpty($room_info['mission_vote']);

		return $room_info;
	}
	/**
	 * @depends test_set_scene_team
	 */
	public function test_mission_number_plus_one($room_info){
		$premission = $room_info['mission'];
		//scene missionのとき、ミッションナンバーがプラスされるか
		$room_info['scene'] = "mission";
		$room_info = set_scene("team",$room_info);
		$this->assertSame($premission + 1,$room_info['mission']);
	}

	/**
	 * @depends test_set_scene_team
	 */
	public function test_set_scene_vote($room_info){

		//シーン : Voteの初期化
		//
		// 初期化するべきこと
		//

	}

}


function set_session_name($_name)
{
	return array("name./test.dat" => $_name);
}

?>
