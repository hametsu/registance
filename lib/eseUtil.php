<?php

//ステータスをファイル同士で更新する関数の作成
function reflesh_state($room_inform,$set_state,$reflash_room_list){
	//Room listの更新
	if($reflash_room_list){
		$room_list = eseFile("./data/room.dat");
		$file_access = fopen("./data/room.dat" , "a");
		flock($file_access, LOCK_EX);
		ftruncate($file_access, 0);
		foreach($room_list as $line){
			$line_array = explode(",",$line);
			if($room_inform['file'] === $line_array[0]){
				$line = $room_inform['file']. "," . $room_inform['name'] . "," . $set_state . "," . $room_inform['people'] . "\n";
			}
			fwrite($file_access,$line);
		}
		flock($file_access, LOCK_UN);
		fclose($file_access);
	}
	//自分のファイルを更新
	$room_data    = file("./data/" . $room_inform['file']);
	$room_data[2] = $set_state;
	$file_access = fopen("./data/" . $room_inform['file']);
	flock($file_access, LOCK_SH);
	foreach($room_data as $line){
		fwrite($file_access,$line);
	}
	flock($file_access, LOCK_UN);
	fclose($file_access);
}

function rewrite_room_dat($set_state,$filename) {
	
	$room_list = eseFile("./data/room.dat");
	$file_access = fopen("./data/room.dat" , "a");
	flock($file_access, LOCK_EX);
	ftruncate($file_access, 0);
	foreach($room_list as $line){
		$line_array = explode(",",$line);
		if($filename === $line_array[0]){
			$line_array[2] = $set_state;
			$line = implode(",",$line_array);
		}
		$line_array = explode(",",$line);
		fwrite($file_access,$line);
	}	
	flock($file_access, LOCK_UN);
	fclose($file_access);
}

function eseFile($filename)
{
	$content = array();
	try{
		$f = fopen($filename , "r");
		flock($f, LOCK_SH);
		fseek($f, 0);
		while($content[] = fgets($f));
		flock($f, LOCK_UN);
		fclose($f);
		
		unset($content[count($content)-1]);
	}catch(Exception $e){
		$content = FALSE;
	}
	return $content;
}

//エスケープ関数の作成
function escape_string($target_string,$max_size){
	$target_string = str_replace(",","、",$target_string);
	$target_string = strip_tags($target_string);

	// < > & "を文字参照化する
	$target_string = str_replace("&","＆",$target_string);
	$target_string = str_replace("<","＜;",$target_string);
	$target_string = str_replace(">","＞",$target_string);
	$target_string = str_replace('"',"",$target_string);

	$target_string = ereg_replace("(\r\n|\r|\n)","<br />",$target_string);
	if (mb_strlen($target_string,"UTF8") > $max_size){
		die("文字列が大きすぎます！！");
	}
	return $target_string;
}
