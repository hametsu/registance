<?php

function load_room_data($target_data){
	$parse_data = array_splice($target_data,16);
	$result_data = array();
	foreach($parse_data as $data_item){
		$result_item = explode(",",$data_item);
		array_push($result_data,array(
			  "name" => $result_item[0]
			 ,"comd" => $result_item[1]
			 ,"color" => $result_item[2]
			 ,"message" => $result_item[3]
			 ,"time"=>trim($result_item[4])
		 ));
	}
	return $result_data;
}

function get_after_post($time,$room_data)
{
	$result_array = array();
	for($i = 0;$i < count($room_data);$i++){
		if($time < $room_data[$i]['time']){
			array_unshift($result_array,$room_data[$i]);
		}
	}
	return $result_array;
}

function get_json($time,$room_data){
	$result_array = get_after_post($time,$room_data);
	return json_encode($result_array);
}

if (isset($_GET['file'])){
$room_file = $_GET['file'];
$room_file = str_replace("/","",$room_file);

$room_data = file("data/$room_file");
$room_data = load_room_data($room_data);

echo get_json($_GET['time'],$room_data);
}
