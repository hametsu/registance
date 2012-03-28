<?php
include_once("eseUtil.php");

//ログデータを代入する
function set_log($room_data,$save_name,$save_comd,$save_color,$save_message){
	$save_message = escape_string($save_message,1000);
	$save_data = $save_name . "," . $save_comd . "," . $save_color . "," . $save_message; 
	array_splice($room_data,16,0,$save_data . "," . (string) time() . "\n");
	return $room_data;

}

//ステータスを初期化するための関数

function set_waiting_to_processing($room_info){

	//
	//　Waiting -> Processingに移行する場合における初期化
	//
	//  ・Missionナンバーを1に変更
	//　・リーダーになっていない人間を、ユーザーとして代入する
	//　・Spyになる人間の選択
	//

	$room_info['mission'] = 1;
	$room_info['not_leader']  = array();
	foreach ($room_info['users'] as $user_item){
		array_push($room_info['not_leader'],$user_item['name']);
	}

	//役割の決定 
	$get_user = $room_info['users']; 
	shuffle($get_user);
	$spy_numbers = array(3  => 1,
		5  => 2,
		6  => 2,
		7  => 3,
		8  => 3,
		9  => 3,
		10 => 4);


	$room_info['userrole'] = array();
	for ($i = 0; $i < ($spy_numbers[count($room_info['users'])]);$i ++){
		$push_user = array_shift($get_user);
		array_push($room_info['userrole'],$push_user["name"]); 
	}
	return $room_info;

}

function set_scene($target_scene,$room_info){
	$pre_scene = $room_info['scene'];
	$room_info['scene'] = $target_scene;
	switch($target_scene){
	case "team":
			$room_info = elect_leader($room_info);
			$room_info['mission'] = $pre_scene == "mission" ? $room_info['mission'] + 1 : $room_info['mission'];
			$room_info['team_member'] = array();
			$room_info['mission_user'] = array();
			$room_info['mission_vote'] = array();

			break;
		case "vote":
			break;
	}

	return $room_info;

}

//room_info配列を初期化する
function init_room_data($room_data,$room_file){
	$room_info = array(
		"file"   => $room_file,
		"name"   => trim($room_data[0]),
		"states" => trim($room_data[1]),
		"users"  => array(),
		"userrole" => $room_data[3] === "\n" ? array() : explode(",",trim($room_data[3])),
		"people" => (int) trim($room_data[4]),
		"scene" => trim($room_data[5]),
		"mission" => (int) trim($room_data[6]),
		"now_leader" => trim($room_data[7]),
		"not_leader" => explode(",",trim($room_data[8])),
		"team_member" => explode(",",trim($room_data[9])),
		"vote_user"  => array(),
		"vote_counter" => trim($room_data[11]),
		"mission_user" => $room_data[12] === "\n" ? array() : explode(",",trim($room_data[12])),
		"mission_vote" => $room_data[13] === "\n" ? array() : explode(",",trim($room_data[13])),
		"victory_point" => $room_data[14] === "\n" ? array() : explode(",",trim($room_data[14])),
		"mission_victory" => trim($room_data[15])
	);

	$parse_users = $room_data[2] === "\n" ? array() : explode(",",trim($room_data[2]));
	for($i = 0; $i < count($parse_users);$i += 2){
		array_push($room_info['users'],array('name' => $parse_users[$i],'pass' => $parse_users[$i + 1]));
	}

	$parse_vote_user = $room_data[10] === "\n" ? array() : explode(",",trim($room_data[10]));
	for ($i = 0;$i < count($parse_vote_user);$i += 2){
		array_push($room_info['vote_user'],array($parse_vote_user[$i],$parse_vote_user[$i + 1]));
	}
	return $room_info;
}

//勝敗をカウントする
function count_victory ($victory_point) { 
$count_success = 0;
$count_not_success = 0;
foreach($victory_point as $victory_point_item){
	if ($victory_point_item === "resistance"){

		$count_success ++;

	} elseif ($victory_point_item === "spy") {

		$count_not_success ++;
	}

}
return array("success" => $count_success, "not_success" => $count_not_success);
}

function elect_leader($room_info){
	if ($room_info['not_leader'][0] === "") {
		$room_info['not_leader'] = array();
		foreach ($room_info['users'] as $user_item){
			array_push($room_info['not_leader'],$user_item['name']);
		}
	}
	shuffle($room_info['not_leader']);
	$room_info['now_leader'] = array_shift($room_info['not_leader']);
	return $room_info;
}


function set_spylist ($room_info,$_SESSION){
	
//スパイリストをセットする
$is_your_spy = FALSE;
$is_spy = array();
if ($room_info_['state'] === "prosessing" or $room_info['state'] === "end"){
	foreach($room_info['users'] as $set_key_user ){
		$is_spy[$set_key_user["name"]] = FALSE;
	}

	foreach($room_info['userrole'] as $set_key_user ){
		$is_spy[$set_key_user] = TRUE;
	}

	if($is_spy[$_SESSION["name" . $room_info['file']]]) {
		$is_your_spy = TRUE;
	}
	}

	return array($is_your_spy,$is_spy);

}


//メンバーとミッションの回数によって何人選択するかを返す関数
function select_member($mission,$user_count){
	$user_5 = array(2,3,2,3,3);
	$user_6 = array(2,3,4,3,4);
	$user_7 = array(2,3,3,4,4);
	$user_8 = array(3,4,4,5,5);
	$user_9 = array(3,4,4,5,5);
	$user_10 = array(3,4,4,5,5);
	$get_select_member = array($user_5,$user_6,$user_7,$user_8,$user_9,$user_10);
	return ($user_count > 4) ? $get_select_member[$user_count - 5][$mission - 1] : 3 ;
}

//そのユーザーがリーダーがどうか判定する
function is_your_leader($room_info,$_SESSION){
	return ($room_info['states'] === "prosessing" && $room_info['now_leader'] === $_SESSION["name" . $room_info['file']]);
}

//そのユーザーがアクセスしているかどうかを判定する
function is_your_connection($room_info,$_SESSION){
$is_your_connection = FALSE;
if(isset($_SESSION["name" . $room_info['file']])){
	foreach($room_info['users'] as $user_item){
		if($_SESSION["name" . $room_info['file']] === $user_item['name']){
			$is_your_connection = TRUE;
		}
	}
}
	return $is_your_connection;
}

//Room_info変数とRoom_data変数を同期する
function room_info_to_room_data($room_info,$room_data){
	
	//関数を使ってパースを行うもの
	$room_data[10] = vote_user_to_string($room_info['vote_user']);
	$room_data[2]  = users_to_string($room_info['users']);

	//関数を使うほどでもないもの
	$room_data[0] = $room_info['name'] . "\n";
	$room_data[1] = $room_info['states'] . "\n";
	$room_data[3] = implode(",",$room_info['userrole']) . "\n";
	$room_data[4] = (string) $room_info['people'] . "\n";
	$room_data[5] = $room_info['scene'] . "\n";
	$room_data[6] = (string) $room_info['mission'] . "\n";
	$room_data[7] = $room_info['now_leader'] . "\n";
	$room_data[8] = implode(",",$room_info['not_leader']) . "\n";
	$room_data[9]  = implode(",",$room_info['team_member']) . "\n";
	$room_data[11] = "\n"; // 欠番
	$room_data[12] = implode(",",$room_info['mission_user']) . "\n";
	$room_data[13] = implode(",",$room_info['mission_vote']) . "\n";
	$room_data[14] = implode(",",$room_info['victory_point']) . "\n";
	$room_data[15] = $room_info['mission_victory'] . "\n";

	return $room_data;
}
//$room_info['users']の配列化しにくい部分を配列化する
function users_to_string($room_users){

	$join_double_array = "";
	foreach($room_users as $user_item){
		$join_double_array .= $join_double_array === "" ? $user_item['name'] . "," . $user_item['pass'] : "," . $user_item['name'] . "," . $user_item['pass'];
	}
	return $join_double_array . "\n";

}


//$room_info['vote_user']の配列化しにくい部分を配列化する
function vote_user_to_string($vote_user){
	$join_double_array = "";
	foreach($vote_user as $vote_user_item){
		$join_double_array .= $join_double_array === "" ? implode(",",$vote_user_item) : "," . implode(",",$vote_user_item);
	}
	return $join_double_array . "\n";
}

//データを書き込む
function write_room_data($room_info,$room_data){
	//$room_infoと$room_dataを同期する
	$room_data = room_info_to_room_data($room_info,$room_data);
	$file_access = fopen("./data/" . $room_info['file'],"w");
	flock($file_access,LOCK_EX);
	foreach($room_data as $lines){
		fwrite($file_access,$lines);
	}
	flock($file_access,LOCK_UN);
	fclose($file_access);

	return $room_data;
}
