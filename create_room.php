<?php
ini_set("display_errors","on");

//エスケープ関数の作成

function escape_string($target_string){
	$target_string = str_replace(",","",$target_string);
	$target_string = strip_tags($target_string);
	if (mb_strlen($target_string) > 120){
		die("文字列が大きすぎます！！");
	}
	return $target_string;
}


if ($_SERVER['REQUEST_METHOD'] == 'GET'){

	die("GETメソッドでは、部屋は作成できません。");

} else {
	$room_name = $_POST['room_name'];
	$room_name = escape_string($room_name);
	$room_file = (string) time() . ".dat";
	//部屋のリストファイルを更新する
	$file_access = fopen("data/room.dat","a");
	flock($file_access, LOCK_EX);
	fseek($file_access, 0, SEEK_END);
	fwrite($file_access,$room_file . "," . $room_name . "," . "waiting," . $_POST['people'] . "\n");
	flock($file_access, LOCK_UN);
	fclose($file_access);

	//部屋のファイルを新規作成する
	$file_access = fopen("./data/" . $room_file,"a");
	flock($file_access, LOCK_EX);
	ftruncate($file_access, 0);
	fwrite($file_access,$room_name . "\n");//[0] 部屋の名前 
	fwrite($file_access,"waiting\n"); //[1] 部屋の状態    
	fwrite($file_access,"\n");//[2] 参加者
	fwrite($file_access,"\n");//[3] 参加者の役割(スパイのみ)
	fwrite($file_access,$_POST['people']."\n");//[4] 参加者の人数
	fwrite($file_access,"breafing\n");//[5] 部屋のシーン
	fwrite($file_access,"0\n");//[6] ミッションの回数
	fwrite($file_access,"\n");//[7] 現在のリーダー
	fwrite($file_access,"\n");//[8] リーダーをやっていない人間
	fwrite($file_access,"\n");//[9] 投票をしたかどうか
	fwrite($file_access,"\n");//[10]
	fwrite($file_access,"\n");//[11] 反対票かどうか
	fwrite($file_access,"\n");//[12] ミッションに投票したかどうか
	fwrite($file_access,"\n");//[13] ミッションに賛成か否か
	fwrite($file_access,"\n");//[14] ミッションの失敗/成功のカウント
	fwrite($file_access,"\n");//[15] ミッションでどっちが勝利したか 
	flock($file_access, LOCK_UN);
	fclose($file_access);
}
header("Location:./show.php?file=$room_file");
echo "部屋を作成しました。";
exit;



