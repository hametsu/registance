<?php

function set_team_list($room_info){
	//チームリストをセットする
	
	$is_team = array();

	if ($room_info['states'] === "prosessing"){
		foreach($room_info['users'] as $set_key_user){
			$is_team[$set_key_user] = FALSE;
		}

	foreach($room_info['team_member'] as $set_key_user){
		$is_team[$set_key_user] = TRUE;
	}
}
	return $is_team;
}

//ミッション遂行リストをセットする
function set_is_mission($room_info){
	$is_mission = array();
	if ($room_info['scene'] === "mission"){
		foreach($room_info['team_member'] as $set_key_user){
			$is_mission[$set_key_user] = FALSE;   
		}

		foreach($room_info['mission_user'] as $set_key_user){
			$is_mission[$set_key_user] = TRUE;
		}
	}
	return $is_mission;
}

//投票リストをセットする
function set_is_vote($room_info){

$is_vote = array();
if ($room_info['scene'] === "vote"){
	foreach($room_info['users'] as $set_key_user){
		$is_vote[$set_key_user] = FALSE;
	}

	foreach($room_info['vote_user'] as $set_key_user){
		$is_vote[$set_key_user[0]] = TRUE;
	}

	}
return $is_vote;
}
