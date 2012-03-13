<?php
//���������״ؿ��κ���

function escape_string($target_string,$max_size){
	$target_string = str_replace(",","",$target_string);
	$target_string = strip_tags($target_string);
	if (mb_strlen($target_string) > $max_size){
		die("ʸ�����礭�����ޤ�����");
	}
	return $target_string;
}


//���ơ������򥻥åȤ���ؿ��κ���
function set_state($room_inform,$set_state,$reflash_room_list){
	//Room list�ι���
	if($reflash_room_list){
		$room_list = file("./data/room.dat");
		$file_access = fopen("./data/room.dat" , "w");
		foreach($room_list as $line){
			$line_array = explode(",",$line);
			if($room_inform['file'] === $line_array[0]){
				$line = $room_inform['file']. "," . $room_inform['name'] . "," . $set_state . "," . $room_inform['people'] . "\n";
			}
			fwrite($file_access,$line);
		}
		fclose($file_access);
	}
	//��ʬ�Υե�����򹹿�
	$room_data    = file("./data/" . $room_inform['file']);
	$room_data[2] = $set_state;
	$file_access = fopen("./data/" . $room_inform['file']);
	foreach($room_data as $line){
		fwrite($file_access,$line);
	}
	fclose($file_access);

}





//room_info�������������
function init_room_data($room_data){
	$room_info = array(
		"file"   => $room_file,
		"name"   => trim($room_data[0]),
		"states" => trim($room_data[1]),
		"users"  => $room_data[2] === "\n" ? array() : explode(",",trim($room_data[2])),
		"userrole" => $room_data[3] === "\n" ? array() : explode(",",trim($room_data[3])),
		"people" => (int) trim($room_data[4]),
		"scene" => trim($room_data[5]),
		"mission" => (int) trim($room_data[6]),
		"now_leader" => trim($room_data[7]),
		"not_leader" => explode(",",$room_data[8]),
		"team_member" => explode(",",trim($room_data[9])),
		"vote_user"  => array(),
		"vote_counter" => trim($room_data[11]),
		"mission_user" => $room_data[12] === "\n" ? array() : explode(",",trim($room_data[12])),
		"mission_vote" => $room_data[13] === "\n" ? array() : explode(",",trim($room_data[13])),
		"victory_point" => $room_data[14] === "\n" ? array() : explode(",",trim($room_data[14])),
		"mission_victory" => trim($room_data[15])
	);

	$parse_vote_user = $room_data[10] === "\n" ? array() : explode(",",trim($room_data[10]));
	for ($i = 0;$i < count($parse_vote_user);$i += 2){
		array_unshift($room_info['vote_user'],array($parse_vote_user[$i],$parse_vote_user[$i + 1]));
	}

	return $room_info;
}

function save_room_info($room_info,$room_log){
	$file_access = fopen("./data/" . $room_info['file'],"w");

	fwrite($file_access,$room_info['name'] .     "\n");//[0] ������̾�� 
	fwrite($file_access,$room_info['states'] .   "\n"); //[1] �����ξ���    
	fwrite($file_access,join(",",$room_info['users']) .    "\n");//[2] ���ü�
	fwrite($file_access,join(",",$room_info['userrole']) . "\n");//[3] ���üԤ����
	fwrite($file_access,$room_info['people'].    "\n");//[4] ���üԤοͿ�
	fwrite($file_access,$room_info['scene']    . "\n");//[5] �����Υ�����
	fwrite($file_access,$room_info['mission']  . "\n");//[6] �ߥå����β��
	fwrite($file_access,$room_info['now_leader']."\n");//[7] ���ߤΥ꡼����
	fwrite($file_access,join(",",$room_info['not_leader']) ."\n");//[8] �꡼�������äƤ��ʤ��ʹ�
	fwrite($file_access,$room_info['team_member'] . "\n");//[9]������Υ��С�
	fwrite($file_access,"\n");//[10] ��ɼ�򤷤����ɤ���
	fwrite($file_access,$room_info['vote_counter']."\n");//[11] ȿ��ɼ���ɤ���
	fwrite($file_access,"\n");//[12] �ߥå�������ɼ�������ɤ���
	fwrite($file_access,"\n");//[13] �ߥå����˻������ݤ�
	fwrite($file_access,join(",",$room_info['victory_point']) ."\n");//[14] �ߥå����μ���/�����Υ������
	fwrite($file_access,$room_info['mission_victory'] . "\n"); //[15] �ߥå����Ǥɤä�������������
	foreach($room_log as $line){
		fwrite($file_access,$line);
	}
	fclose($file_access);
}

function elect_leader($member){
	shuffle($member);
	$get_leader = array_shift($member);
	return array($get_leader,$member);
}

//���С��ȥߥå����β���ˤ�äƲ������򤹤뤫���֤��ؿ�
function select_member($mission,$user_count){
	$user_5 = array(2,3,2,3,3);
	$user_6 = array(2,3,4,3,4);
	$user_7 = array(2,3,3,4,4);
	$user_8 = array(3,4,4,5,5);
	$user_9 = array(3,4,4,5,5);
	$user_10 = array(3,4,4,5,5);
	$get_select_member = array($user_5,$user_6,$user_7,$user_8,$user_9,$user_10);
	return ($user_count > 4) ? $get_select_member[$user_count - 5][$mission - 1] : 1 ;
}
