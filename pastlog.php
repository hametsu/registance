<?php
ini_set("display_errors","on");
require_once("./config/debug.php");
include_once("./lib/eseUtil.php");

//room.datが存在するかを保持する。
$room_exist = file_exists("./data/room.dat");


if ($room_exist){

	$room_file = eseFile("./data/room.dat");

	$end_room = array();
	$prosessing_room = array();


	foreach ($room_file as $room_data){
		$room_data = str_replace("\n","",$room_data);
		$room_arraydata =  explode(",",$room_data);
		switch($room_arraydata[2]){
		case "end":
			array_push($end_room,$room_data);
			break;
		case "processing":
			array_push($prosessing_room,$room_data);
			break;
		}
	}

}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
    <link rel="stylesheet" href="./main.css" />
	<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
	<title>過去の部屋 - レジスタンス・チャット</title>
</head>
<body>
	<div id="log">
	<a href="./">Index</a>
    <h2>過去に作られた部屋</h2>
    <!-- 終わった部屋を表示する -->
    <ul>
<?php

if (!$room_exist){
	echo "<p>ファイルは存在しません。</p>";        
} else {
	foreach ($end_room as $room_list) {
		$room_arraydata = explode(",",$room_list);
		echo "<li><a href='./show.php?file=$room_arraydata[0]'>$room_arraydata[1]($room_arraydata[3]人部屋)</a></li>";
	}
} 
?>
    </ul>
    <h2>進行中の部屋</h2>
    <ul>
<?php
if (!$room_exist){
	echo "<p>ファイルは存在しません。</p>";
} else {
	foreach ($prosessing_room as $room_list) {
		$room_arraydata = explode(",",$room_list);
		echo "<li><a href='./show.php?file=$room_arraydata[0]'>$room_arraydata[1]($room_arraydata[3]人部屋)</a></li>";
	}
}

?>
	</div>
</body>
</html>
