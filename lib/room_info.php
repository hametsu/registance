<?php

require_once("eseUtil.php");
require_once("userclass.php");
require_once("singletonclass.php");

class RoomInfo extends Singleton {

	private $file;
	public  $cgi_file;
	private $room_data;
	private $room_user = array();
	private $vote_user = array();
	private $mission_user = array();
	private $mission_success = 0;
	private $mission_failure = 0;

	public function loadfile($file_name,$test_mode) {
		$this->cgi_file = "$file_name";
		if ($test_mode) {
			$this->file = "$file_name";
		} else {
			$this->file = "data/$file_name";
		}
		$this->room_data = eseFile($this->file);
		$this->parse_user();
		$this->parse_vote_user();
		$this->parse_mission_user();
		if (($this->get_states() === "processing"
			or $this->get_states() === "end") 
			and $this->is_room_anonymous() !== "false") {
			$this->set_user_anonymous_name();
		}
	}

	//method for users
	private function parse_user() {
		$this->room_user = array();
		$parse_users = $this->room_data[2] === "\n" ? array() : explode(",",trim($this->room_data[2]));
		if ($parse_users !== array("")) {
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
	}

	public function count_user() {
		return count($this->get_users_array());
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
		$this->room_data[2] = $this->room_data[2] === "\n" ? "$username,$pass\n" : trim($this->room_data[2]) . ",$username,$pass\n";
		$this->parse_user();
	}

	public function logout_user($username,$pass){
		$new_username_string = "";
		if ($username === "" || $pass === "") {
			return FALSE;
		}

		foreach($this->get_users() as $useritem){
			if ($useritem->username === $username) {
				if ($useritem->pass !== $pass){
					return FALSE;
				}
			} else {
				$new_username_string = $new_username_string === "" ? $useritem->username . "," . $useritem->pass : $new_username_string . "," . $useritem->username . "," . $useritem->pass;
			}
		}
		$this->room_data[2] = $new_username_string . "\n";
		$this->reflesh_want_spy_user();
		return TRUE;
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
				$this->get_now_leader() === $username);
	
	}

	public function shuffle_users() {
		$new_user_string = "";
		shuffle($this->room_user);
		foreach($this->room_user as $user_item) {
			$new_user_string = $new_user_string === "" ? $user_item->username . "," . $user_item->pass : $new_user_string . "," . $user_item->username . "," . $user_item->pass;
		}
		$this->room_data[2] = $new_user_string . "\n";
	}

	//method for mission_user
	private function parse_mission_user() {
		$parse_mission_users = $this->room_data[12] == "\n" ? array() : explode(",",trim($this->room_data[12]));
		if ($parse_mission_users !== array("")){
		for($i = 0;$i < count($parse_mission_users);$i += 2) {
			$this->set_mission_to_user($parse_mission_users[$i],$parse_mission_users[$i + 1]);
		}
		}
	}
	public function set_mission_user($name,$vote) {
		$this->set_mission_to_user($name,$vote);
		if($this->room_data[12] === "\n"){
			$this->room_data[12] = "$name,$vote\n";
		} else {
			$this->room_data[12] = trim($this->room_data[12]) . ",$name,$vote\n";
		}

		if ($this->is_room_anonymous() !== "false") {
			$name = $this->get_username_to_anonymous($name);
		}

		$this->add_log("system","message","green","【" . $name . "】さんは、ミッションを遂行しました。");
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

	public function reset_mission_user(){
		$max = count($this->room_user);
		for($i = 0;$i < $max;$i++){
			$this->room_user[$i]->mission = NULL;
		}
		$this->room_data[12] = "\n";
	}

	//method for vote_user
	private function parse_vote_user() {
		$parse_vote_users = $this->room_data[10] === "\n" ? array() : explode(",",trim($this->room_data[10]));
		if ($parse_vote_users !== array("")){
		for ($i = 0;$i < count($parse_vote_users);$i += 2){
			$this->set_vote_to_user($parse_vote_users[$i],$parse_vote_users[$i + 1]);
		}
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
				if ($this->room_user[$i]->vote === NULL){
					$this->add_log("system","message","green", $name . "さんは投票を行いました。");
				} else {
					if ($this->is_room_anonymous() !== "false") {
						$name = $this->room_user[$i]->anonymous_name;
					}
					$this->add_log("system","message","green", $name . "さんは投票を行いました。");
				}
				$this->room_user[$i]->vote = $vote;
			}
		}
		$room_data_string = "";

		foreach($this->room_user as $user_item){
			if ($user_item->vote !== NULL){
			$room_data_string = $room_data_string === "" ? $user_item->username . "," . $user_item->vote : $room_data_string . "," . $user_item->username . "," . $user_item->vote;
			}
		}

		$this->room_data[10] = $room_data_string . "\n";

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

	public function get_anonymous_spylist() {
		$parse_anonymous = Array();
		foreach ($this->get_spylist() as $spy_item){
			array_push($parse_anonymous,$this->get_username_to_anonymous($spy_item));
		}
		return $parse_anonymous;
	}

	public function is_spy($target_name) {
		foreach($this->get_spylist() as $user_item){
			if ($target_name === $user_item){
				return TRUE;
			}
		}
		return FALSE;
	}
	public function count_spy() {
		$SPY_NUMBER = array (
			3 => 2,
			4 => 2,
			5 => 2,
			6 => 2,
			7 => 3,
			8 => 3,
			9 => 3,
			10 => 4
		);
		return $SPY_NUMBER[count($this->get_users_array())];
	}
	
	public function set_spylist() {
		$set_user = array();
		$count_spy = $this->count_spy();
		
		if (count($this->get_want_spy_user()) === 0){
			//スパイ希望者がいない場合は、そのままユーザー配列を渡す
			$get_user = $this->get_users_array();
		} elseif ($count_spy > count($this->get_want_spy_user())) {
			$not_spy_user = $this->get_users_array();
			$get_user = $this->get_want_spy_user();
			foreach($get_user as $push_user){
				array_push($set_user,$push_user);
				for ($i = 0;$i < count($not_spy_user);$i++){
					if ($not_spy_user[$i] === $push_user) {
						$not_spy_user[$i] = "";
						break;
					}
				}
			}
			$get_user = $not_spy_user;
		} else {
			$get_user = $this->get_want_spy_user();
		}
		//スパイの選択を始める
		shuffle($get_user);
		for ($i = 0;count($set_user) < $count_spy;$i++){
			$push_user = array_shift($get_user);
			if ($push_user === ""){
				$i--;
				continue;
			}
			array_push($set_user,$push_user);
		}
		$this->room_data[3] = implode(",",$set_user)."\n";
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
		$data_array = explode(",",trim($this->room_data[4]));
		return (int) $data_array[0];
	}

	public function set_room_people($i) {
		$data_array = explode(",",trim($this->room_data[4]));
		$data_array[0] = $i;
		$this->room_data[4] = implode(",",$data_array) . "\n";
	}

	public function can_start_game() {
		$user_count = count($this->get_users());
		return ($user_count >= 5 and $user_count <= 10);
	}

	public function get_vote_start() {
		return array_slice(explode(",",trim($this->room_data[4])),1);
	}

	public function set_vote_start($username) {
		foreach($this->get_vote_start() as $vote_item) {
			if ($vote_item === $username) { return FALSE; }
		}
		if($this->is_user($username)){
			$this->room_data[4] = trim($this->room_data[4]) . "," . $username . "\n";
		}
	}

	public function is_vote_start($username) {
		foreach($this->get_vote_start() as $vote_item){
			if ($vote_item === $username) {
				return TRUE;
			}
		}
		return FALSE;
	}

	public function get_scene() {
		return trim($this->room_data[5]);
	}
	
	public function set_scene($target_scene){	
		switch($target_scene){
		case "expands_card":
			$this->set_expands();
		//以下、チームからミッションがやってきたときの場合と同様
		case "team":
			if ($this->get_scene() === "mission"){
				$this->plus_mission();
				$this->set_failure_team_no(0);
			}
			
			if ($this->get_scene() !== "expands_card") {
				$this->plus_failure_team_no();
				$this->set_now_leader();
			}
			
			$this->reset_team_member();
			$this->reset_vote_user();
			if ($this->is_apply_expands()) {
				$this->reset_given_card();
			}
			break;
		case "vote":
			$this->reset_mission_user();
			break;
		case "mission":
			break;
		}
		$this->reset_user_conform();
		$this->room_data[5] = $target_scene . "\n";
	}

	public function get_mission_no() {
		$line_parse = explode(",",trim($this->room_data[6]));
		return (int) $line_parse[0];
	}

	public function set_mission_no($i) {
		$this->room_data[6] = (string) $i . "," . $this->get_failure_team_no() ."\n";
	}

	public function plus_mission() {
		$this->set_mission_no($this->get_mission_no() + 1);
	}

	public function get_failure_team_no() {
		$line_parse = explode(",",trim($this->room_data[6]));
		return (int) $line_parse[1];
	}

	public function set_failure_team_no($i) {
		$this->room_data[6] = (string) $this->get_mission_no() . "," . $i .  "\n";
	}

	public function plus_failure_team_no() {
		$this->set_failure_team_no($this->get_failure_team_no() + 1);
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
		$this->room_data[7] = array_shift($target_not_leader) . "\n";
		$this->room_data[8] = implode(",",$target_not_leader) . "\n";
	}

	public function get_not_leader() {
		return explode(",",trim($this->room_data[8]));
	}

	public function reset_not_leader() {
		$this->room_data[8] = implode(",",$this->get_users_array()) . "\n";
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

	public function parse_team_to_anonymous() {
		if ($this->is_room_anonymous() !== "false"){
			$anonymous_to_user_array = Array();
			foreach($this->get_team_member() as $member_item){
				array_push($anonymous_to_user_array,$this->get_username_to_anonymous($member_item));
			}
			return $anonymous_to_user_array;
		} else {
			return $this->get_team_member();
		}
	}

	public function parse_team_to_username() {
		if ($this->is_room_anonymous() !== "false"){
			$anonymous_to_user_array = Array();
			
			foreach($this->get_team_member() as $member_item){
				array_push($anonymous_to_user_array,$this->get_anonymous_name_to_user($member_item));
			}
			$this->room_data[9] = implode(",",$anonymous_to_user_array) . "\n";
		}
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

	public function get_need_team_array() {
		$user_5 = array(2,3,2,3,3);
		$user_6 = array(2,3,4,3,4);
		$user_7 = array(2,3,3,4,4);
		$user_8 = array(3,4,4,5,5);
		$user_9 = array(3,4,4,5,5);
		$user_10 = array(3,4,4,5,5);

		$get_select_member = array($user_5,$user_6,$user_7,$user_8,$user_9,$user_10);
		return (count($this->get_users_array()) > 4) ? $get_select_member[count($this->get_users_array()) - 5 ] : array(3,3,3,3,3);
	}

	public function get_need_team_member() {
		$get_select_member = $this->get_need_team_array();	
	return $get_select_member[$this->get_mission_no() - 1];
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

	public function is_apply_expands() {
		return trim($this->room_data[17]) === "is_expands";
	}

	public function set_expands($debug = false) {
		$draw_cards = Array();
		$expand_cards = $this->get_expand_cards();
		$user_member = count($this->get_users());

		if ($user_member <= 6 and !$debug) {
			$draw_number = 1;
		} elseif ($user_member >= 7
			and $user_member <= 8) {
			$draw_number = 2;
		} elseif ($user_member >= 9 or $debug) {
			$draw_number = 3;
		}

		for ($i = 0;$i < $draw_number; $i++){
			array_push($draw_cards,array_shift($expand_cards));
		}

		$this->room_data[18] = implode(",",$expand_cards) . "\n";
		$this->room_data[19] = implode(",",$draw_cards) . "\n";

	}

	public function parse_expands_user_to_data() {
		$result_string = "";
		foreach($this->room_user as $user_item){
			if($result_string === ""){
				$result_string = "name,". $user_item->username;
			} else {
				$result_string .= ",name," . $user_item->username;
			}

			if ($user_item->have_expand_card !== Null) {
				foreach($user_item->have_expand_card as $have_card){
					$result_string .= ",card,$have_card";
				}
			}

			if($user_item->effect_expand_card !== Null) {
				foreach($user_item->effect_expand_card as $effect){
					$result_string .= ",effect,$effect";
				}
			}

			if ($user_item->given_card){
				$result_string .= ",given,true";
			}

			if ($user_item->conform_use_card) {
				$result_string .= ",conform,true";
			}
		}
		$this->room_data[20] = $result_string . "\n";
	}

	public function reset_given_card() {

		$max_user = count($this->room_user);

		for($i = 0;$i < $max_user;$i++) {
			$this->room_user[$i]->given_card = false;
		}

		$this->parse_expands_user_to_data();

	}

	public function get_expand_leader() {
		return $this->room_data[19] === "\n" ? Array() : explode(",",trim($this->room_data[19]));
	}

	public function get_expand_cards() {
		return $this->room_data[18] === "\n" ? Array() : explode(",",trim($this->room_data[18]));
	}

	public function leader_have_expand_cards() {
		return count($this->get_expand_leader()) !== 0;
	}

	public function reset_expands($debug = false){
		$this->room_data[18] = 'Power_leader,Power_leader,Vote_concenser,Observer,Observer,Veto_leader,Card_getter';
		if (count($this->get_users()) >= 7
			or $debug) {
			$this->room_data[18] .= 'Wiretapper,Wiretapper,Veto_leader,Veto_leader,Open_Inform,Vote_concenser,Open_Mission,Open_leader';
			}
		$this->room_data[20] = "";
		foreach ($this->room_user as $user_item) {
			$this->room_data[20] .= $this->room_data[20] === "" ? "name," . $user_item->username : ",name," . $user_item->username;
		}
			$this->room_data[20] .= "\n";
	}	

	public function parse_expands_data_to_user() {
		$parse_target = "";
		$data_array = explode(",",trim($this->room_data[20]));
		$max = count($data_array);
		for ($i = 0;$i < $max;$i =  $i + 2){
			switch($data_array[$i]){
				case "name":
				if ($parse_target !== "") {
					$this->set_expands_to_user($parse_target,$parse_have_card,$parse_effect,$given_flag,$conform_flag);
				}
					$given_flag = false;
					$conform_flag = false;
					$parse_target = $data_array[$i + 1];
					$parse_have_card = Array();
					$parse_effect = Array();
					break;
				case "card":
					array_push($parse_have_card,$data_array[$i + 1]);
					break;
				case "effect":
					array_push($parse_effect,$data_array[$i + 1]);
					break;
				case "given":
					if ($data_array[$i + 1] === "true"){
						$given_flag = true;
					}
				case "conform":
					if ($data_array[$i + 1] === "true"){
						$conform_flag = true;
					}
			}
		}
		$this->set_expands_to_user($parse_target,$parse_have_card,$parse_effect,$given_flag,$conform_flag);
	}

	public function set_user_conform($target_user) {
		$max_user = count($this->room_user);
		for ($i = 0;$i < $max_user;$i++) {
			if ($this->room_user[$i]->username === $target_user) {
				$this->room_user[$i]->conform_use_card = true;
				break;
			}
		}
	}

	public function reset_user_conform() {
		$max_user = count($this->room_user);
		for ($i = 0;$i < $max_user;$i++) {
			$this->room_user[$i]->conform_use_card = false;
		}
	}

	public function set_expands_to_user($parse_target,$parse_have_card,$parse_effect,$given_flag,$conform_flag) {
		$user_member = count($this->get_users());
		for($j = 0;$j < $user_member;$j++){
			if ($this->room_user[$j]->username === $parse_target){
				$this->room_user[$j]->have_expand_card = $parse_have_card;
				$this->room_user[$j]->effect_expand_card = $parse_effect;
				$this->room_user[$j]->given_flag = $given_flag;
				$this->room_user[$j]->conform_use_card = $conform_flag;
				break;
			}
		}
	}

	public function use_expands_user($username,$card) {
		$is_effect = false;
		$max_user   = count($this->room_user);
		for ($i = 0;$i < $max_user;$i++){
			if ($this->room_user[$i]->username === $username) {
				$result_card = Array();
				$target_card = $this->room_user[$i]->have_expand_card;
				foreach($target_card as $card_item) {
					if ($card_item !== $card) {
						array_push($result_card,$card_item);
					} else {
						$is_effect = true;
					}
				}
			break;
			}
		}
		$this->room_user[$i]->have_expand_card = $result_card;
		$this->parse_expands_user_to_data();
		return $is_effect;
	}

	public function give_expands_user($username,$card){
		$exist_user = false;
		$max_user   = count($this->room_user);
		for ($i = 0;$i < $max_user;$i++) {
			if ($this->room_user[$i]->username === $username
			and $this->room_user[$i]->given_card === false) {
				$exist_user = true;
				break;
			}
		}

		$exist_card = false;
		if ($exist_user) {
			$result_card = Array();
			foreach($this->get_expand_leader() as $card_item) {
				if ($card_item !== $card){
					array_push($result_card,$card_item);
				} else {
				$exist_card = true;
				}
			}
		}

		if ($exist_card) {
			array_push($this->room_user[$i]->have_expand_card,$card);
			$this->room_data[19] = implode(",",$result_card);
			$this->room_user[$i]->given_card = true;
		}
		
		$log_leader = $this->is_room_anonymous() === "false" ? $this->get_now_leader() : $this->get_username_to_anonymous($this->get_now_leader());
		$log_name = $this->is_room_anonymous() === "false" ? $username : $this->get_username_to_anonymous($username);
		$kind_card = $this->get_cards_text($card);
		$this->add_log("system","message","green","【" . $log_leader . " 】は、【" . $log_name . "】に対して『" . $kind_card . "』を渡しました。");
	}

	public function get_cards_text($kind_card) {
		switch($kind_card){
		case "Power_leader":
			return "強制的にリーダーになる";
		case "Vote_concenser":
			return "皆に先駆けて投票を公開する";
		case "Observer":
			return "ミッションカードを見る";
		case "Veto_leader":
			return "強制的にリーダーを解任する";
		case "Wiretapper":
			return "隣の人間の役割カードを見る";
		case "Open_Inform":
			return "役割カードを他の人に見せる";
		case "Open_Mission":
			return "ミッションカードをオープンにさせる";
		case "Open_leader":
			return "誰かに役割カードを見せる";
		case "Card_getter":
			return "誰かのカードを奪い取る";
		}
	}

	const START_LOG_LINE = 22;
	public function add_log($username,$comd,$color,$message) {
		if(!isset($this->room_data[self::START_LOG_LINE])) {
			$this->room_data[self::START_LOG_LINE] = "$username,$comd,$color,$message," . (string) time() . "\n";
		} else {
			array_splice($this->room_data,self::START_LOG_LINE,0,"$username,$comd,$color,$message," . (string) time() . "\n");
		}
	}

	public function is_room_anonymous() {
		$is_room_anonymous = explode(",",trim($this->room_data[16]));
		return $is_room_anonymous[0];
	}

	public function get_username_to_anonymous($target_username) {
		foreach($this->room_user as $user_item) {
			if ($user_item->username === $target_username) {
				return $user_item->anonymous_name;
			}
		}
	}

	public function new_user_anonymous_name($debug = null) {
		$max = count($this->room_user);
		if ($debug === "test"){
			for ($i = 0;$i < $max;$i++){
				$this->room_user[$i]->anonymous_name = null;
			}
		} else {
			$anonymous_name_set = Array(
				"ルパン","ボンド","オーウェル","ヘンリー","クラーク","ハインライン","アシモフ","イーガン","ウルフ","ギブスン"
				,"ザミャーチン","デニーロ","ウィルソン","ハント","クルーズ","ロジャー","ヒッチコック","コナン","ホームズ"
				,"ゴルゴ","カリオストロ","スマイリー","ドミニク","ヘルム","ドラモント","ガーラント","ヤン","ラウ","ボーン","ミュアー"
				,"アーチャー","トロイ","アームストロング");
			shuffle($anonymous_name_set);
			for ($i = 0;$i < $max;$i++){
				$this->room_user[$i]->anonymous_name = array_shift($anonymous_name_set);
			}
			
			$this->anonymous_name_to_data();
		}
	}

	public function anonymous_name_to_data() {

	if ($this->is_room_anonymous() === "false") {
		$this->room_data[16] = "false\n";
	} else {
		$write_string = "true";
		foreach ($this->room_user as $user_item){
			$write_string .= "," . $user_item->username . "," . $user_item->anonymous_name;
		}
		$this->room_data[16] = $write_string . "\n";
	}
	
	}

	public function set_user_anonymous_name() {
		$parse_anonymous_name = explode(",",trim($this->room_data[16]));
		$max_anonymous_name = count($parse_anonymous_name);

		for ($i = 1;$i < $max_anonymous_name;$i = $i + 2){
			$this->set_user_to_anonymous_name($parse_anonymous_name[$i],$parse_anonymous_name[$i + 1]);
		}

	}

	public function get_anonymous_name_to_user($target_anonymous_name) {
		foreach($this->room_user as $user_item) {
			if ($user_item->anonymous_name === $target_anonymous_name) {
				return $user_item->username;
			}
		}
	}

	public function set_user_to_anonymous_name($target_user,$anonymous_name) {
		$max_user = count($this->room_user);

		for ($i = 0; $i < $max_user;$i ++){
			if ($this->room_user[$i]->username === $target_user) {
				$this->room_user[$i]->anonymous_name = $anonymous_name;
			}
		}
	}

	/*
	public function set_user_anonymous_name("false") {
		
	}
	 */

	public function set_waiting_to_processing() {
		if ($this->is_room_anonymous() !== "false") {
			$this->new_user_anonymous_name();
		}

		if ($this->is_apply_expands()) {
			$this->reset_expands();
		}

		$this->set_mission_no(1);
		$this->shuffle_users();
		$this->set_states("processing");
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
		foreach($this->room_data as $lines){
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
		case "processing":
		 $return_string = "";
		 $return_string .= "Mission" . $this->get_mission_no() . " - ";
		switch ($this->get_scene()){
			case "expands_card":
				$return_string .= "カードを配布しています。";
				break;
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

	public function set_victory_history($team_member,$team_leader,$victory_point,$failure_number) {
		$this->room_data[11] = $this->room_data[11] === "\n" ? implode(",",$team_member) . ",$team_leader,$victory_point,$failure_number\n" : trim($this->room_data[11]) . "," . implode(",",$team_member) . ",$team_leader,$victory_point,$failure_number\n"; 
	}

	public function get_victory_history() {
		$result_array = array();
		$array_point = 0;
		$team_need_array = $this->get_need_team_array();
		$raw_array = explode(",",trim($this->room_data[11]));
		for ($i = 0;$i < $this->get_mission_no() - 1;$i++){
			$set_array = array("team_member"=>array(),"team_leader"=>NULL,"victory_point" => NULL,"failure_number"=>NULL);
			for ($j = 0;$j < $team_need_array[$i];$j++) {
				array_push($set_array["team_member"],$raw_array[$array_point]);
				$array_point ++;
			}
			$set_array["team_leader"] = $raw_array[$array_point];
			$array_point++;
			$set_array["victory_point"] = $raw_array[$array_point];
			$array_point++;
			$set_array["failure_member"] = (int) $raw_array[$array_point];
			$array_point++;
			array_push($result_array,$set_array);
		}
		return $result_array;
	}

	public function debug_reset_want_spy_user(){
		$this->room_data[13] = "\n";
	}

	public function get_want_spy_user() {
		return $this->room_data[13] === "\n" ? array() : explode(",",trim($this->room_data[13]));
	}

	public function set_want_spy_user($username){
		$this->room_data[13] = $this->room_data[13] === "\n" ? "$username\n" : trim($this->room_data[13]) . "," . "$username\n";
	}

	public function reflesh_want_spy_user() {
		$reflesh_want_spy_string = "";
		$this->parse_user();
		foreach($this->get_users_array() as $user_item){
			foreach($this->get_want_spy_user() as $check_user){
				if ($check_user === $user_item){
					$reflesh_want_spy_string = $reflesh_want_spy_string === "" ? $user_item : $reflesh_want_spy_string . "," . $user_item;
				}
			}
		}
		$this->room_data[13] = $reflesh_want_spy_string . "\n";
	}

}

