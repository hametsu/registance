<?php

require_once("eseUtil.php");
require_once("userclass.php");
require_once("singletonclass.php");

class RoomInfo extends Singleton {

	private $file;
	private $room_data;
	private $room_user = array();
	private $vote_user = array();
	private $mission_user = array();
	private $mission_success = 0;
	private $mission_failure = 0;

	public function loadfile($file_name) {
		$this->file = $file_name;
		$this->room_data = eseFile($file_name);
		$this->parse_user();
		$this->parse_vote_user();
		$this->parse_mission_user();
	}

	//method for users
	private function parse_user() {
		$parse_users = $this->room_data[2] === "\n" ? array() : explode(",",trim($this->room_data[2]));
		for ($i = 0;$i < count($parse_users);$i += 2){
			$set_user = new RoomUser($parse_users[$i],$parse_users[$i + 1]);
			array_push($this->room_user,$set_user);
			if ($parse_users[$i + 1] === "success") {
				$this->mission_success ++;
			} elseif ($parse_users[$i + 1] === "failure") {
				$this->mission_failure ++;
			}
		}
	}

	public function count_user() {
		return count($this->get_user_array());
	}

	public function get_users() {
		return $this->room_user;
	}

	public function get_users_array() {
		$users_array = array();
		foreach($this->get_users() as $user_item){
			array_push($users_array,$user_item->username);
		}
		return $users_array;
	}

	public function get_user($i) {
		return $this->room_user[$i];
	}

	public function add_user($username,$pass){
		$this->room_data[2] = trim($this->room_data[2]) . ",$username,$pass\n";
		$adduser = new RoomUser($username,$pass);
		array_push($this->room_user,$adduser);
	}

	public function is_user($username) {
		foreach($this->get_users() as $user_item){
			if ($user_item->username === $username) {
				return TRUE;
			}
		}
		return FALSE;
	}

	public function is_leader($username) {
	
		return ($this->get_states() === "processing" &&
				$this->get_now_leader() === $user_name);
	
	}

	//method for mission_user
	private function parse_mission_user() {
		$parse_mission_users = $this->room_data[12] == "\n" ? array() : explode(",",trim($this->room_data[12]));
		for($i = 0;$i < count($parse_mission_users);$i += 2) {
			$this->set_mission_to_user($parse_mission_users[$i],$parse_mission_users[$i + 1]);
		}
	}
	public function set_mission_user($name,$vote) {
		$this->set_mission_to_user($name,$vote);
		if($this->room_data[12] === "\n"){
			$this->room_data[12] = "$name,$vote\n";
		} else {
			$this->room_data[12] = trim($this->room_data[12]) . "$name,$vote\n";
		}
	}

	public function get_user_mission($name) {
		foreach($this->room_user as $user_item) {
			if($name === $user_item->username) {
				return $user_item->mission;
			}
		}
		return NULL;
	}

	public function set_mission_to_user($name,$mission) {
		$max = count($this->room_user);
		for($i = 0;$i < $max;$i++){
			if($this->room_user[$i]->username === $name){
				$this->room_user[$i]->mission = $mission;
				break;
			}
		}
	}

	public function count_mission_user() {
		$counter = 0;
		foreach($this->room_user as $user_item) {
			if ($this->get_user_mission($user_item->username) !== NULL){
				$counter ++;
			}
		}
		return $counter;
	}

	//method for vote_user
	private function parse_vote_user() {
		$parse_vote_users = $this->room_data[10] === "\n" ? array() : explode(",",trim($this->room_data[10]));
		for ($i = 0;$i < count($parse_vote_users);$i += 2){
			$this->set_vote_to_user($parse_vote_users[$i],$parse_vote_users[$i + 1]);
		}
	}

	public function get_user_vote($name) {
		foreach($this->room_user as $user_item) {
			if($name === $user_item->username){
				return $user_item->vote;
			}
		}
		return FALSE;
	}

	public function set_vote_to_user($name,$vote) {
		$max = count($this->room_user);
		for($i = 0;$i < $max;$i++){
			if($this->room_user[$i]->username === $name){
				$this->room_user[$i]->vote = $vote;
				break;
			}
		}
	}

	public function reset_vote_user() {
		$max = count($this->room_user);
		for($i = 0;$i < $max;$i++){
			$this->room_user[$i]->vote = NULL;
		}
		$this->room_data[10] = "\n";
	}

	public function set_vote_user($name,$vote){
		$max = count($this->room_user);
		$this->set_vote_to_user($name,$vote);
		for($i = 0;$i < $max;$i++){
			if ($name === $this->room_user[$i]->username){
				$this->room_user[$i]->vote = $vote;
			}
		}
		if ($this->room_data[10] === "\n"){
			$this->room_data[10] = "$name,$vote\n"; 
		} else {
			$this->room_data[10] = trim($this->room_data[10]) . ",$name,$vote\n";
		}
	}

	public function count_vote() {
		$counter = 0;
		foreach($this->get_users() as $user_item){
			if ($user_item->vote !== NULL){
				$counter ++;
			}
		}
		return $counter;
	}

	public function is_already_user($name) {
		foreach($this->room_user as $user_item) {
			if ($name === $user_item->username) {
				return $user_item; 
			}
		}
		return FALSE;
	}

	//get proparty method

	public function get_spylist() {
		return $this->room_data[3] === "\n" ? array() : explode(",",trim($this->room_data[3]));
	}

	public function is_spy($target_name) {
		foreach($this->get_spylist() as $user_item){
			if ($target_name === $user_item){
				return TRUE;
			}
		}
		return FALSE;
	}

	public function set_spylist() {
		$SPY_NUMBER = array (
			3 => 1,
			4 => 2,
			5 => 2,
			6 => 2,
			7 => 3,
			8 => 3,
			9 => 3,
			10 => 4
			);
		$get_user = $this->get_users_array();
		$set_user = array();
		shuffle($get_user);
		for ($i = 0;$i < $SPY_NUMBER[count($this->get_users_array())];$i++){
			$push_user = array_shift($get_user);
			array_push($set_user,$push_user);
		}
		$this->room_data[3] = implode(",",$set_user);
	}

	public function get_mission_victory() {
		return trim($this->room_data[15]);
	}
	
	public function get_filename() {
		return $this->file;
	}

	public function get_raw_roomdata(){
		return isset($this->room_data) ? $this->room_data : NULL;
	}

	public function get_name() {
		return isset($this->room_data[0]) ? trim($this->room_data[0]) : NULL;
	}

	public function get_states() {
		return isset($this->room_data[1]) ? trim($this->room_data[1]) : NULL;
	}

	public function set_states($target_states){
		$this->room_data[1] = "$target_states\n";
	}

	public function get_room_people(){
		return (int) trim($this->room_data[4]);
	}

	public function get_scene() {
		return trim($this->room_data[5]);
	}
	
	public function set_scene($target_scene){
	
		switch($target_scene){
		case "team":
			$this->set_now_leader();
			if ($this->get_scene() === "mission"){
				$this->plus_mission();
			}
			$this->reset_team_member();
			$this->reset_vote_user();
			break;
		case "vote":
			break;
		case "mission":
			break;
		}
		$this->room_data[5] = $target_scene . "\n";
	}

	public function get_mission_no() {
		return (int) trim($this->room_data[6]);
	}

	public function set_mission_no($i) {
		$this->room_data[6] = (string) $i . "\n";
	}

	public function plus_mission() {
		$this->set_mission($this->get_mission() + 1);
	}

	public function get_now_leader() {
		return trim($this->room_data[7]);
	}

	public function set_now_leader() {
		$target_not_leader = $this->get_not_leader();
		if ((string) $target_not_leader[0] === "") {
			$this->reset_not_leader();
			$target_not_leader = $this->get_not_leader();
		}
		shuffle($target_not_leader);
		$this->room_data[7] = array_shift($target_not_leader) . "\n";
		$this->room_data[8] = implode(",",$target_not_leader);
	}

	public function get_not_leader() {
		return explode(",",trim($this->room_data[8]));
	}

	public function reset_not_leader() {
		$not_leader_array = $this->get_users_array();
		$this->room_data[8] = implode(",",$not_leader_array) . "\n";
	}

	public function debug_set_not_leader($set_array) {
		$this->room_data[8] = implode(",",$set_array);
	}

	public function get_team_member() {
		return $this->room_data[9] === "\n" ? array() : explode(",",trim($this->room_data[9]));
	}

	public function reset_team_member() {
		$this->room_data[9] = "\n";
	}

	public function set_team_member($target_member){
		$this->room_data[9] = implode(",",$target_member) . "\n"; 
	}

	public function is_team_member($name){
		foreach($this->get_team_member() as $check_same_name){
			if($check_same_name === $name){
				return TRUE;
			}
		}
		return FALSE;
	}

	public function count_team_member($name){
		return count($this->get_team_member());
	}

	public function get_need_team_member() {
		$user_5 = array(2,3,2,3,3);
		$user_6 = array(2,3,4,3,4);
		$user_7 = array(2,3,3,4,4);
		$user_8 = array(3,4,4,5,5);
		$user_9 = array(3,4,4,5,5);
		$user_10 = array(3,4,4,5,5);

		$get_select_member = array(
			$user_5,$user_6,$user_7,$user_8,$user_9,$user_10
		);
	
	return (count($this->get_users_array()) > 4) ? $get_select_member[count($this->get_users_array()) - 5][$this->get_mission_no() - 1] : 3 ;
	}

	public function get_victory_point() {
		return $this->room_data[14] === "\n" ? array() : explode(",",trim($this->room_data[14]));
	}

	public function get_victory_point_count_array() {
		$counter_success = 0;
		$counter_failure = 0;
		$victory_point_array = $this->get_victory_point();
		foreach($victory_point_array as $victory_point_item) {
			if ($victory_point_item === "registance") {
				$counter_success ++;
			} elseif ($victory_point_item === "spy") {
				$counter_failure ++;
			}
		}
		return array("registance" => $counter_success,
					 "spy" => $counter_failure);
	}

	public function add_victory_point($setpoint) {
		$this->room_data[14] = $this->room_data[14] === "\n" ? $setpoint . "\n" : trim($this->room_data[14]) . "," . $setpoint . "\n";
	}

	const START_LOG_LINE = 16;
	public function add_log($username,$comd,$color,$message) {
		if(!isset($this->room_data[self::START_LOG_LINE])) {
			$this->room_data[self::START_LOG_LINE] = "$username,$comd,$color,$message," . (string) time() . "\n";
		} else {
			array_splice($this->room_data,self::START_LOG_LINE,0,"$username,$comd,$color,$message," . (string) time() . "\n");
		}
	}	

	public function set_waiting_to_processing() {

		$this->set_mission_no(1);
		$this->reset_not_leader();
		$this->set_spylist();
	}

	public function get_game_victory() {
		return trim($this->room_info[15]);
	}

	public function set_game_victory($set_victory) {
		$this->room_info[15] = "$set_victory\n";
	}

	public function write_room_data(){
		//$room_infoと$room_dataを同期する
		$file_access = fopen($this->file,"w");
		flock($file_access,LOCK_EX);
		foreach($room_data as $lines){
			fwrite($file_access,$lines);
		}
		flock($file_access,LOCK_UN);
		fclose($file_access);
	}

	public function count_failure() {
		$counter = 0;
		foreach($this->get_users() as $user_item){
			if ($user_item->mission === "failure") {
				$counter ++;
			}
		}
		return $counter;
	}

	public function get_room_states_message() {
		switch($this->get_states()){
		case "waiting":
			return "待機中";
			break;
		case "prosessing":
		 $return_string = "";
		 $return_string .= "Mission #" . $roominfo->get_mission_no() . " - ";
		switch ($room_info->get_scene()){
			case "team":
				$return_string .= "チームを編成します。";
				break;
			case "vote":
				$return_string .= "チームを信任するか、選んでください。";
				break;
			case "mission":
				$return_string .= "ミッションを成功させるかどうか、選んでください。";
				break;
		}
		return $return_string;
		break;
		case "end":
			return "終了しました";
			break;
		}
	}

}
