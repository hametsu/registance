<?php

require_once("../lib/room_info.php");

class RoomInfoTest extends PHPUnit_Framework_TestCase
{
	public function test_loadfile(){
		$file_name = "newtest.dat";
		$roominfo = RoomInfo::getInstance();
		$roominfo->loadfile($file_name,TRUE);

		$this->assertSame($roominfo->cgi_file,$file_name);
		$this->assertSame(count($roominfo->get_raw_roomdata()),18);

		return $roominfo;

	}

	public function test_userclass() {
		$test_class_roomuser = new RoomUser("Hoge","Fuga");
		$this->assertSame($test_class_roomuser->username,"Hoge");
		$this->assertSame($test_class_roomuser->pass,"Fuga");
	}

	/**
	 * @depends test_loadfile
	 */

	public function test_get_property_not_array($roominfo){
		
		$this->assertSame($roominfo->get_name(),"部屋ネーム");
		$this->assertSame($roominfo->get_states(),"prosessing");
	
		$this->assertSame($roominfo->get_room_people(),3);
		$this->assertSame($roominfo->get_scene(),"vote");
		$this->assertSame($roominfo->get_mission_no(),1);
		$this->assertSame($roominfo->get_now_leader(),"User1");
		
		$this->assertSame(count($roominfo->get_not_leader()),2);
		$this->assertSame(count($roominfo->get_team_member()),2);
		
		$this->assertSame(count($roominfo->get_victory_point()),2);
		$this->assertSame($roominfo->get_mission_victory(),"");
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_is_room_anonymous($roominfo) {
		$this->assertSame($roominfo->is_room_anonymous(),"true");
	}

	/**
	 * @depends test_loadfile
	 */

	public function test_get_room_anonymous_name($roominfo) {
		
	$roominfo->set_user_anonymous_name();
	$this->assertSame($roominfo->get_user(0)->anonymous_name,"ボンド");

	return $roominfo;
	}
	/**
	 * @depends test_get_room_anonymous_name
	 */
	public function test_get_anonymous_name_to_user($roominfo) {
		$check_anonymous_user = $roominfo->get_user(0);
		$this->assertSame(
			$roominfo->get_anonymous_name_to_user($check_anonymous_user->anonymous_name),
			$check_anonymous_user->username
		);
	}

	/**
	 * @depends test_get_room_anonymous_name
	 */
	public function test_set_anonymous_name_by_false($roominfo) {
		$roominfo->new_user_anonymous_name("test");
		$this->assertTrue($roominfo->get_user(0)->anonymous_name === null);
		return $roominfo;
	}

	/**
	 * @depends test_set_anonymous_name_by_false
	 */
	public function test_set_anonymous_name($roominfo) {
		$roominfo->new_user_anonymous_name();
		$this->assertTrue($roominfo->get_user(0)->anonymous_name !== null);
		$check_anonymous_user = $roominfo->get_user(0)->anonymous_name;
		$roominfo->set_user_anonymous_name();

		$this->assertSame($check_anonymous_user,$roominfo->get_user(0)->anonymous_name);
	}


	/**
	 * @depends test_loadfile
	 */
	public function test_set_vote_start($roominfo) {
		$roominfo->set_vote_start("User1");
		$raw_data = $roominfo->get_raw_roomdata();
		$this->assertSame($raw_data[4],"3,User1\n");
		$roominfo->set_vote_start("User4");
		$raw_data = $roominfo->get_raw_roomdata();
		$this->assertSame($raw_data[4],"3,User1\n");
		$roominfo->set_vote_start("User1");
		$raw_data = $roominfo->get_raw_roomdata();
		$this->assertSame($raw_data[4],"3,User1\n");
		return $roominfo;
	}

	/**
	 * @depends test_set_vote_start
	 */
	public function test_get_vote_start($roominfo) {
		$vote_user = $roominfo->get_vote_start();
		$this->assertSame(count($vote_user),1);
		$this->assertSame($vote_user[0],"User1");
		$roominfo->set_vote_start("User2");
		$vote_user = $roominfo->get_vote_start();
		$this->assertSame(count($vote_user),2);
		$this->assertSame($vote_user[1],"User2");
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_get_user($roominfo) {
		$this->assertSame(count($roominfo->get_users()),3);
		$this->assertSame($roominfo->get_user(0)->username,'User1');
		$this->assertSame($roominfo->get_user(0)->pass,'pass');
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_get_vote_user($roominfo) {
		$this->assertSame($roominfo->get_user_vote("User1"),"trust");
		$this->assertSame($roominfo->get_user_vote("User2"),"veto");
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_mission_user($roominfo) {
		$this->assertSame($roominfo->get_user_mission("User1"),"success");
		$this->assertSame($roominfo->get_user_mission("User3"),"failure");
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_spy_user($roominfo) {
		$spylist = $roominfo->get_spylist();
		$this->assertSame($spylist[0],"User3");
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_already_user_or_not($roominfo) {
		$is_already_user = $roominfo->is_already_user("User1");
		$this->assertTrue($is_already_user->username === "User1");
		$this->assertFalse($roominfo->is_already_user("Hoge1"));
		return $is_already_user;
	}

	/**
	 * @depends test_already_user_or_not
	 */
	public function test_password_is_same($is_already_user) {
		$this->assertTrue($is_already_user->pass === "pass");
		$this->assertFalse($is_already_user->pass === "fuga");
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_add_user($roominfo){
		$roominfo->add_user("User4","hoge");
		$raw_data = $roominfo->get_raw_roomdata();
		$this->assertSame($raw_data[2],"User1,pass,User2,pass2,User3,pass3,User4,hoge\n");
		$this->assertTrue($roominfo->is_already_user("User4")->username === "User4");
		return $roominfo;
	}

	/**
	 * @depends test_add_user
	 */
	public function test_logout_user($roominfo) {
		$this->assertTrue($roominfo->logout_user("User4","hoge"));
		$this->assertFalse($roominfo->logout_user("","Falsepass"));
		$this->assertFalse($roominfo->logout_user("User1",""));
		$raw_data = $roominfo->get_raw_roomdata();
		$this->assertSame($raw_data[2],"User1,pass,User2,pass2,User3,pass3\n");
		$this->assertFalse($roominfo->logout_user("User1","Falsepass"));
		return $roominfo;
	}

	/**
	 * @depends test_loadfile
	 */

	public function test_is_team_member($roominfo) {
		$this->assertTrue($roominfo->is_team_member("User1"));
		$this->assertFalse($roominfo->is_team_member("User2"));
	}

	/**
	 * @depends test_loadfile
	 */

	public function test_add_log($roominfo) {
		$time_string = (string) time();
		$roominfo->add_log("system","warning","red","ほげほげ");
		$raw_data = $roominfo->get_raw_roomdata();
		$this->assertSame($raw_data[17],"system,warning,red,ほげほげ,$time_string\n");
		$roominfo->add_log("ほげ","say","black","ふがふが");
		$raw_data = $roominfo->get_raw_roomdata();
		$this->assertSame($raw_data[17],"ほげ,say,black,ふがふが,$time_string\n");
		$this->assertSame($raw_data[18],"system,warning,red,ほげほげ,$time_string\n");
	}	

	/**
	 * @depends test_loadfile
	 */
	public function test_reset_leader($roominfo) {
		$roominfo->reset_not_leader();
		$this->assertSame(count($roominfo->get_not_leader()),3);
	}
	
	/**
	 * @depends test_loadfile
	 */

	public function test_is_spy($roominfo) {
		$this->assertTrue($roominfo->is_spy("User3"));
		$this->assertFalse($roominfo->is_spy("User2"));
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_set_spy_to_user($roominfo) {
		$roominfo->set_spylist();
		$this->assertSame(count($roominfo->get_spylist()),2);
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_get_want_spy_user($roominfo) {
		$get_want_to_spy = $roominfo->get_want_spy_user();
		$this->assertSame(count($get_want_to_spy),2);
		$this->assertSame($get_want_to_spy[0],"User1");
	return $roominfo;
	}

	/**
	 * @depends test_get_want_spy_user
	 */
	public function test_set_want_spy_user($roominfo){
		$roominfo->debug_reset_want_spy_user();
		$this->assertSame(count($roominfo->get_want_spy_user()),0);
		$roominfo->set_want_spy_user("User3","spy");
		$get_want_to_spy = $roominfo->get_want_spy_user();
		$this->assertSame(count($get_want_to_spy),1);
		$this->assertSame($get_want_to_spy[0],"User3");

		$roominfo->set_want_spy_user("User1","spy");
		$get_want_to_spy = $roominfo->get_want_spy_user();
		$this->assertSame(count($get_want_to_spy),2);
		$this->assertSame($get_want_to_spy[1],"User1");
		return $roominfo;
	}
	/**
	public function test_dump_set_spylist($roominfo) {
		$roominfo->debug_reset_want_spy_user();
		$roominfo->set_want_spy_user("User3");
		$roominfo->set_spylist();
	}
	 */

	/**
	 * @depends test_logout_user
	 */
	public function test_reflesh_want_spy_user($roominfo) {
		$roominfo->debug_reset_want_spy_user();
		$roominfo->set_want_spy_user("User1","spy");
		$roominfo->logout_user("User1","pass");
		$raw_user = $roominfo->get_raw_roomdata();
		$this->assertSame(count($roominfo->get_want_spy_user()),0);
		$roominfo->add_user("User1","pass");
		return $roominfo;
	}

	/**
	 * @depends test_reflesh_want_spy_user
	 */
	public function test_set_want_double_spy_user($roominfo) {
		$roominfo->debug_reset_want_spy_user();
		$roominfo->set_want_spy_user("User2","resistance");
		$roominfo->set_want_spy_user("User3","resistance");
		$roominfo->set_want_spy_user("User1","double_spy");
		for ($i = 0;$i < 100; $i++){
			$roominfo->set_spylist(true);
			$spy_user = $roominfo->get_spylist();
			$this->assertSame($spy_user[1],"User1");
		}
	}

	/**
	 * @depends test_reflesh_want_spy_user
	 */
	public function test_set_spy_member_by_spy_and_registance($roominfo) {
		$roominfo->debug_reset_want_spy_user();
		$roominfo->set_want_spy_user("User1","spy");
		$roominfo->set_want_spy_user("User2","resistance");
		$roominfo->set_want_spy_user("User3","resistance");

		for ($i = 0;$i < 100;$i++){
			$roominfo->set_spylist();
			$this->assertSame(count($roominfo->get_spylist()),2);
			$this->assertTrue($roominfo->is_spy("User1"));
		}
		return $roominfo;
	}

	/**
	 * @depends test_set_spy_member_by_spy_and_registance
	 */
	
	public function test_set_spy_member_by_spy_and_not($roominfo) {
		$roominfo->debug_reset_want_spy_user();
		$roominfo->set_want_spy_user("User1","not");
		$roominfo->set_want_spy_user("User2","not");
		$roominfo->set_want_spy_user("User3","resistance");
		for ($i = 0;$i < 100;$i++) {
			$roominfo->set_spylist();
			$this->assertTrue($roominfo->is_spy("User1"));
			$this->assertTrue($roominfo->is_spy("User2"));
		}
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_waiting_to_processing($roominfo){
		$roominfo->set_waiting_to_processing();
		$not_leader = count($roominfo->get_not_leader());
		$this->assertSame($roominfo->get_mission_no(),1);
		$this->assertSame(count($roominfo->get_not_leader()),3);
	}

	/**
	 * @depends test_loadfile
	 */

	public function test_set_new_leader($roominfo) {

		$pre_not_leader = count($roominfo->get_not_leader());
		
		//
		//　二つを確認するべき
		//
		//　1. not_leaderは一つ減っているか
		//　2. now_leaderにはちゃんと値がセットされているか
		//

		$roominfo->set_now_leader();
		$this->assertSame(count($roominfo->get_not_leader()) + 1,$pre_not_leader);

		return $roominfo;
	}


	/**
	 * @depends test_set_new_leader
	 */

	public function test_new_leader_if_void($roominfo){
		//
		//何も入っていないときは、ちゃんとnot_leaderにメンバーを追加し、
		//あらためて選択しているか
		//
		$roominfo->debug_set_not_leader(array(""));
		$roominfo->set_now_leader();
		$this->assertSame(count($roominfo->get_not_leader()),count($roominfo->get_users_array()) - 1);
	}
	

	/**
	 * @depends test_loadfile
	 */
	public function test_set_scene_team($roominfo) {
		
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

		$not_leader_count = count($roominfo->get_not_leader());
		$premission = $roominfo->get_mission_no();
		$roominfo->set_scene("team");

		//何かしらの値が代入されているもの
		$this->assertSame($roominfo->get_scene(),"team");
		$this->assertSame($roominfo->get_mission_no(),$premission);
		$this->assertTrue($roominfo->get_now_leader() !== "\n" && $roominfo->get_now_leader() !== "");
		$this->assertSame($not_leader_count - 1,count($roominfo->get_not_leader()));

		//何かしらの値が代入されているべきではないもの
		$this->assertSame(count($roominfo->get_team_member()),0);
		
		$is_reset_vote = TRUE;
		foreach($roominfo->get_users() as $check_user){
			if ($check_user->vote !== NULL){
				$is_reset_vote = FALSE;
			}
		}

		$this->assertTrue($is_reset_vote);

		return $roominfo;
	}

	/**
	 * @depends test_loadfile
	 */

	public function test_is_user($roominfo) {
		$this->assertTrue($roominfo->is_user("User1"));
		$this->assertFalse($roominfo->is_user("Hoge2"));
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_get_need_team_member($roominfo){

		$this->assertSame($roominfo->get_need_team_member(),3);

	}

	/**
	 * @depends test_loadfile
	 */

	public function test_set_team_member($roominfo) {
		$check_user = array("HOGE","FUGA");
		$roominfo->set_team_member($check_user);
		$this->assertSame($roominfo->get_team_member(),$check_user);
	}

	/**
	 * @depends test_loadfile
	 */

	public function test_set_vote($roominfo) {
		$roominfo->set_vote_user("User1","veto");
		$raw_data = $roominfo->get_raw_roomdata();
		
		$this->assertSame($roominfo->get_user_vote("User1"),"veto");
		$this->assertSame($raw_data[10],"User1,veto\n");
		
		$roominfo->set_vote_user("User2","trust");
		$raw_data = $roominfo->get_raw_roomdata();
		$this->assertSame($roominfo->get_user_vote("User2"),"trust");
		//TODO 通ったり通らなかったりするテストなので改善されるべき
		//$this->assertSame($raw_data[10],"User1,veto,User2,trust\n");
		
		return $roominfo;
	}

	/**
	 * @depends test_set_vote
	 */
	public function test_count_vote($roominfo) {
		$this->assertSame($roominfo->count_vote(),2);
	}

	/**
	 * @depends test_set_scene_team
	 */
	public function test_set_scene_vote($roominfo) {
		$roominfo->set_scene("vote");
		$this->assertSame($roominfo->get_scene(),"vote");
	}

	/**
	 * @depends test_set_scene_vote
	 */
	public function test_set_scene_mission($roominfo) {
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_set_mission($roominfo) {
		/*
		$this->assertSame($roominfo->count_mission_user(),0);
		$this->assertSame($roominfo->get_user_mission("User1"),"success");
		$this->assertNull($roominfo->get_user_mission("User2"));
		$roominfo->set_mission_user("User2","failure");
		$this->assertSame($roominfo->count_mission_user(),3);
		$this->assertSame($roominfo->get_user_mission("User2"),"failure");
		*/
		return $roominfo;
	}

	/**
	 * @depends test_set_mission
	 */
	public function test_count_failure($roominfo) {
		$this->assertSame($roominfo->count_failure(),0);
	}

	/**
	 * @depends test_loadfile
	 */

	public function test_count_victory_point($roominfo){
		$count_result = $roominfo->get_victory_point_count_array();
		$this->assertSame($count_result['registance'],1);
		$this->assertSame($count_result['spy'],1);
	}

	/**
	 * @depends test_loadfile
	 */
	public function test_add_victory_point($roominfo) {
		$roominfo->add_victory_point("registance");
		
		$count_result = $roominfo->get_victory_point_count_array();
		$raw_data = $roominfo->get_raw_roomdata();
		//var_dump($count_result);
		$this->assertSame($count_result['registance'],2);
		$this->assertSame($count_result['spy'],1);
		$this->assertSame($raw_data[14],"registance,spy,registance\n");

		$roominfo->add_victory_point("spy");
		$count_result = $roominfo->get_victory_point_count_array();
		$raw_data = $roominfo->get_raw_roomdata();
		$this->assertSame($count_result['registance'],2);
		$this->assertSame($count_result['spy'],2);
		$this->assertSame($raw_data[14],"registance,spy,registance,spy\n");
	}

	/**
	 * @depends test_loadfile
	 */

	public function test_set_victory_history($roominfo){
		$roominfo->set_victory_history(array("User1","User2","User3"),"User5","failure",1);
		$raw_data = $roominfo->get_raw_roomdata();
		$this->assertSame($raw_data[11],"User1,User2,User3,User5,failure,1\n");
		$roominfo->set_victory_history(array("User1","User2","User3"),"User4","success",0);
		$raw_data = $roominfo->get_raw_roomdata();
		$this->assertSame($raw_data[11],"User1,User2,User3,User5,failure,1,User1,User2,User3,User4,success,0\n");

		return $roominfo;
	}

	/**
	 * @depends test_set_victory_history
	 */

	public function test_get_victory_history($roominfo){
		$roominfo->set_mission_no(2);
		$victory_array = $roominfo->get_victory_history();
		$this->assertSame(count($victory_array),1);
		$this->assertSame($victory_array[0]["team_member"],array("User1","User2","User3"));
		$this->assertSame($victory_array[0]["victory_point"],"failure");
		$this->assertSame($victory_array[0]["failure_member"],1);

		$roominfo->set_mission_no(3);
		$victory_array = $roominfo->get_victory_history();
		$this->assertSame(count($victory_array),2);
		$this->assertSame($victory_array[1]["team_member"],array("User1","User2","User3"));
		$this->assertSame($victory_array[1]["victory_point"],"success");
		$this->assertSame($victory_array[1]["failure_member"],0);
		return $roominfo;
	}
	
	/**
	 * @depends test_loadfile
	 */
	public function test_get_failure_team_no($roominfo) {
		$this->assertSame($roominfo->get_failure_team_no(),5);
		return $roominfo;
	}

	/**
	 * @depends test_get_failure_team_no
	 */
	public function test_set_failure_team_no($roominfo) {
		$roominfo->set_failure_team_no(2);
		$this->assertSame($roominfo->get_failure_team_no(),2);
		return $roominfo;
	}

	/**
	 * @depends test_set_failure_team_no
	 */

	public function test_plus_failure_team_no($roominfo) {
		$roominfo->plus_failure_team_no();
		$this->assertSame($roominfo->get_failure_team_no(),3);
	}

	/**
	 * @depends test_get_failure_team_no
	 * ミッションをセットしたとき、ちゃんと不信任チームの回数がリセットされるか
	 */
	public function test_reset_failure_team_no($roominfo) {
		$roominfo->set_scene("mission");
		$roominfo->set_scene("team");
		$this->assertSame($roominfo->get_failure_team_no(),1);
		$roominfo->set_scene("team");
		$this->assertSame($roominfo->get_failure_team_no(),2);
	}
}
