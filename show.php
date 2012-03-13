<?php
ini_set("display_erors","on");
ini_set("session.gc_maxlifetime","1800");


//ページのキャッシュを無効にする

header('Last-Modified:' . gmdate('D, d M Y H:i:s') . 'GMT');
header('pragma: no-cache');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon,26 Jul 1997 05:00:00 GMT');

//エスケープ関数の作成

function escape_string($target_string,$max_size){
	$target_string = str_replace(",","",$target_string);
	$target_string = strip_tags($target_string);
	if (mb_strlen($target_string) > $max_size){
		die("文字列が大きすぎます！！");
	}
	return $target_string;
}


//ステータスをセットする関数の作成
function set_state($room_inform,$set_state,$reflash_room_list){
	//Room listの更新
	if($reflash_room_list){
		$room_list = file("./room.dat");
		$file_access = fopen("./room.dat" , "w");
		foreach($room_list as $line){
			$line_array = explode(",",$line);
			if($room_inform['file'] === $line_array[0]){
				$line = $room_inform['file']. "," . $room_inform['name'] . "," . $set_state . "," . $room_inform['people'] . "\n";
			}
			fwrite($file_access,$line);
		}
		fclose($file_access);
	}
	//自分のファイルを更新
	$room_data    = file("./dat/" . $room_inform['file']);
	$room_data[2] = $set_state;
	$file_access = fopen("./dat/" . $room_inform['file']);
	foreach($room_data as $line){
		fwrite($file_access,$line);
	}
	fclose($file_access);

}


//TODO 状態によって表示を切り替える -> 参加者、ログ
$room_file = $_GET['file'];
$room_file = str_replace("/","",$room_file);

if (!file_exists("dat/$room_file") && !isset($_GET['file'])){
	die("そのようなファイルは存在しません");
}

$room_data = file("dat/$room_file");

//room_info配列を初期化する
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

$room_info = init_room_data($room_data);
$room_info['file'] = $_GET['file'];

session_start();
//---------------------------------
//参加者が現れたときの処理
//
//もしwaitingでなければ参加できない
//---------------------------------
if(!isset($_SESSION[$room_file]) && $room_info['states'] === "waiting"){
	$_POST['name'] = escape_string($_POST['name'],40);
	if($_POST['name'] !== "" and isset($_POST['name'])){

		foreach($room_info['users'] as $name_check){
			if($name_check === $_POST['name']){
				die("名前が重複しています");
			}
		} 

		//セッションの保存
		$_SESSION["name$room_file"] = $_POST['name'];
		$_SESSION[$room_file] = TRUE;
		array_unshift($room_info['users'],$_POST['name']);
		//ログに参加者として保存            
		$file_access = fopen("./dat/" . $room_file,"w");
		if(trim($room_data[2]) === ""){
			$room_data[2] = $_POST['name'] . "\n";
		} else {
			$room_data[2] = $_POST['name'] . "," . $room_data[2];
		}
		$save_name = $_POST['name'];
		$save_data = "system,warning,red,".$_POST['name']."さんが入室しました。\n";
		if(!isset($room_data[16])){
			$room_data[16] = $save_data;
		} else {
			array_splice($room_data,16,0,$save_data);    
		} 

		foreach($room_data as $lines){
			fwrite($file_access,$lines);
		}

		fclose($file_access);
	}
}

//もし参加者が規定数を超えたなら、状態を変える
if (($room_info['states'] === "waiting")  && (count($room_info['users']) >= $room_info["people"])) {
	set_state($room_info,"prosessing",TRUE);
	$room_info["states"] = "prosessing";


	//---------------------------------
	//
	//システムの初期化
	//
	//----------------------------------

	$room_info['scene'] = "team";
	$room_info['mission'] = 1;
	$room_info['not_leader']  = $room_info['users']; 
	//リーダーの決定
	$result = elect_leader($room_info['not_leader']);
	$room_info['now_leader']  = $result[0];
	$room_info['not_leader']  = $result[1];

	//役割の決定 
	$get_user = $room_info['users']; 
	shuffle($get_user);
	$spy_numbers = array(2  => 1,
		5  => 2,
		6  => 2,
		7  => 3,
		8  => 3,
		9  => 3,
		10 => 4);

	$room_info['userrole'] = array();

	for ($i = 0; $i < ($spy_numbers[count($room_info['users'])]);$i ++){
		array_push($room_info['userrole'],array_shift($get_user)); 
	}

	$room_log = array_splice($room_data,16);
	save_room_info($room_info,$room_log);
	$room_data = array_merge($room_data,$room_log);

}

function save_room_info($room_info,$room_log){
	$file_access = fopen("./dat/" . $room_info['file'],"w");

	fwrite($file_access,$room_info['name'] .     "\n");//[0] 部屋の名前 
	fwrite($file_access,$room_info['states'] .   "\n"); //[1] 部屋の状態    
	fwrite($file_access,join(",",$room_info['users']) .    "\n");//[2] 参加者
	fwrite($file_access,join(",",$room_info['userrole']) . "\n");//[3] 参加者の役割
	fwrite($file_access,$room_info['people'].    "\n");//[4] 参加者の人数
	fwrite($file_access,$room_info['scene']    . "\n");//[5] 部屋のシーン
	fwrite($file_access,$room_info['mission']  . "\n");//[6] ミッションの回数
	fwrite($file_access,$room_info['now_leader']."\n");//[7] 現在のリーダー
	fwrite($file_access,join(",",$room_info['not_leader']) ."\n");//[8] リーダーをやっていない人間
	fwrite($file_access,$room_info['team_member'] . "\n");//[9]チームのメンバー
	fwrite($file_access,"\n");//[10] 投票をしたかどうか
	fwrite($file_access,$room_info['vote_counter']."\n");//[11] 反対票かどうか
	fwrite($file_access,"\n");//[12] ミッションに投票したかどうか
	fwrite($file_access,"\n");//[13] ミッションに賛成か否か
	fwrite($file_access,join(",",$room_info['victory_point']) ."\n");//[14] ミッションの失敗/成功のカウント
	fwrite($file_access,$room_info['mission_victory'] . "\n"); //[15] ミッションでどっちが勝利したか
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

//そのユーザーがアクセスしているかどうかを判定する
$is_your_connection = FALSE;
if(isset($_SESSION["name" . $room_info['file']])){
	foreach($room_info['users'] as $user_item){
		if($_SESSION["name" . $room_info['file']] === $user_item){
			$is_your_connection = TRUE;
		}
	}
}

//そのユーザーがリーダーがどうか判定する
$is_browse_leader = FALSE;
if ($room_info['states'] === "prosessing" && $room_info['now_leader'] === $_SESSION["name" . $room_info['file']]){
	$is_browse_leader = TRUE;
}


//スパイリストをセットする
$is_your_spy = FALSE;
$is_spy = array();
if ($room_info['states'] === "prosessing" or $room_info['states'] === "end"){
	foreach($room_info['users'] as $set_key_user ){
		$is_spy[$set_key_user] = FALSE;
	}

	foreach($room_info['userrole'] as $set_key_user ){
		$is_spy[$set_key_user] = TRUE;
	}

	if($is_spy[$_SESSION["name" . $room_info['file']]]) {
		$is_your_spy = TRUE;
	}
}

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

//投票リストをセットする

$is_vote = array();
if ($room_info['scene'] === "vote"){
	foreach($room_info['users'] as $set_key_user){
		$is_vote[$set_key_user] = FALSE;
	}

	foreach($room_info['vote_user'] as $set_key_user){
		$is_vote[$set_key_user[0]] = TRUE;
	}

}
//ミッション遂行リストをセットする
$is_mission = array();
if ($room_info['scene'] === "mission"){
	foreach($room_info['team_member'] as $set_key_user){
		$is_mission[$set_key_user] = FALSE;   
	}

	foreach($room_info['mission_user'] as $set_key_user){
		$is_mission[$set_key_user] = TRUE;
	}
}

//コマンドによって挙動を変更する
if ($room_info['states'] === "prosessing" && isset($_POST['command'])){
	switch($room_info['scene']){
	case "team":
		if ($is_browse_leader 
			&& ($_POST['command'] === 'select_member') 
			&& (count($_POST['select_user']) === select_member($room_info['mission'],count($room_info['users'])))){

				//チームを選択する
				$save_data = "system,warning,red," . $_SESSION["name$room_file"] . "さんは、【" . implode("、",$_POST['select_user']) . "】を、チームとして選びました。\n";
				array_splice($room_data,16,0,$save_data);    

				$file_access = fopen("./dat/" . $room_file,"w");
				$room_data[9] = implode(",",$_POST['select_user']) . "\n";
				foreach($_POST['select_user'] as $set_user){
					$is_team[$set_user] = TRUE;
				}
				$room_info['scene'] = "vote";
				$room_data[5] = "vote\n";
				foreach($room_data as $lines){
					fwrite($file_access,$lines);
				}
				fclose($file_access);
			}
		break;
	case "vote":
		if($_POST['command'] === "vote"
			&& $is_your_connection){
				if(!$is_vote['name' . $room_info['file']]){
					$is_vote[$_SESSION['name' . $room_info['file']]] = TRUE;
					$file_access = fopen("./dat/" . $room_info['file'],"w");
					$set_vote_user = array($_SESSION["name" . $room_info['file']] , $_POST['vote']);
					array_unshift($room_info['vote_user'],$set_vote_user);
					$join_double_array = "";
					foreach($room_info['vote_user'] as $vote_user_item){
						if ($join_double_array === ""){
							$join_double_array = implode(",",$vote_user_item);
						} else {
							$join_double_array .= "," . implode(",",$vote_user_item); 
						}
					}
					$room_data[10] = $join_double_array . "\n";
					foreach($room_data as $lines){
						fwrite($file_access,$lines);
					}
					fclose($file_access);
				}

			} 
		break;
	case "mission":
		if($_POST['command'] === "mission"
			&& $is_your_connection
			&& $is_team[$_SESSION['name' . $room_info['file']]]){
				$is_mission[$_SESSION['name' . $room_info['file']]] = TRUE;
				array_unshift($room_info['mission_user'],$_SESSION['name' . $room_info['file']]);
				array_unshift($room_info['mission_vote'],$_POST['vote']);
				$room_data[12] = implode(",",$room_info['mission_user']) . "\n";
				$room_data[13] = implode(",",$room_info['mission_vote']) . "\n";
				$file_access = fopen("./dat/" . $room_info['file'],"w");
				foreach($room_data as $lines){
					fwrite($file_access,$lines);
				}
				fclose($file_access);
			}
		break;
	}
}
//もし投票者と参加者が一緒になり、かつ信任が過半数なら、
//次の状態に移行する
if($room_info['scene'] === "vote"){
	$vote_count = 0;
	foreach($is_vote as $user_item){
		if ($user_item){
			$vote_count ++;
		}
	}
	if($vote_count >= count($room_info['users'])){
		//投票者の統計をログに流す
		$is_team_trust = 0;
		foreach($room_info['vote_user'] as $get_user){
			$save_data = "system,message,red," . $get_user[0] . "さんは、【" . ($get_user[1] === "trust" ? "信任" : "否認") . "】に投票しました。\n";
			if ($get_user[1] === "trust"){
				$is_team_trust ++;
			}
			array_splice($room_data,16,0,$save_data);   
		}

		//投票者の初期化
		$is_vote = array();
		$room_data[10] = "\n";
		$room_info['vote_user'] = array();

		if ($is_team_trust > (count($room_info['users']) / 2)){
			array_splice($room_data,16,0,"system,warning,red,このチーム(" . implode(",",$room_info['team_member']) . ")は信任されました。\n");   
			$room_info['scene'] = "mission";
			$room_data[5] = "mission\n";
		} else {
			array_splice($room_data,16,0,"system,warning,red,このチーム(" . implode(",",$room_info['team_member']) . ")は否認されました。\n");
			$room_info['scene'] = "team";
			$room_info['team_member'] = array();
			$room_data[5] = "team\n";
			$room_data[9] = "\n";
			foreach($room_info['users'] as $set_key_user){
				$is_team["$set_key_user"] = FALSE;
			}

		}

		$file_access = fopen("./dat/" . $room_info['file'],"w");
		foreach($room_data as $lines){
			fwrite($file_access,$lines);
		}
		fclose($file_access);

	}        
}
//チームメンバーが全員ミッションを選んだら、
//ミッション成功判定を行う
if($room_info['scene'] === "mission"
	&& count($room_info['mission_user']) >= count($room_info['team_member']) ){
		//失敗かどうかを判定する    
		$count_falsed = 0;    
		foreach($room_info['mission_vote'] as $is_falsed){
			if ($is_falsed === "falsed"){
				$count_falsed += 1;
			}
		}
		if ($count_falsed === 0){
			$save_data = "system,warning,red,このミッションは【成功】しました。\n";
			array_unshift($room_info['victory_point'],"resistance");
		} else {
			$save_data = "system,warning,red,このミッションは、" . $count_falsed . "人の「失敗」への投票で、【失敗】しました。\n";
			array_unshift($room_info['victory_point'],"spy");
		}
		array_splice($room_data,16,0,$save_data);

		//Missionを初期化する
		$room_info['scene'] = "team";
		$room_info['mission'] += 1;
		$room_info['team_member'] = array();

		//リーダーの決定

		//デバック用の代入
		//$room_info['not_leader'] = $room_info['users'];


		$result = elect_leader($room_info['not_leader']);
		$room_info['now_leader'] = $result[0];
		$room_info['not_leader'] = $result[1];
		$room_log = array_splice($room_data,16);
		save_room_info($room_info,$room_log);
		$room_data = array_merge($room_data,$room_log);

		//そのユーザーがリーダーがどうか判定する
		$is_browse_leader = FALSE;
		if ($room_info['states'] === "prosessing" && $room_info['now_leader'] === $_SESSION["name" . $room_info['file']]){
			$is_browse_leader = TRUE;
		}


	}

//勝敗をカウントする
$count_success = 0;
$count_not_success = 0;
foreach($room_info['victory_point'] as $victory_point_item){
	if ($victory_point_item === "resistance"){

		$count_success ++;

	} elseif ($victory_point_item === "spy") {

		$count_not_success ++;

	}

}

//もし、ゲームの終了条件なら、ゲームを終了する
if ($count_success >= 3 || $count_not_success >= 3){
	set_state($room_info,"end",TRUE);
	$room_data[14] = implode(",",$room_info['victory_point']);

	if ($count_success >= 3){
		$room_info['states'] = "end";
		$room_data[1] = "end\n";

		$room_info['scene'] = "end";
		$room_data[5] = "end\n";

		$room_info['mission_victory'] = "registance";
		$room_data[15] = "registance\n";

		$save_data = "system,warning,red,やりましたね！スパイの妨害を勝ち抜き、【レジスタンス側の勝利】です。\n";

	} elseif ($count_not_success >= 3){
		$room_info['states'] = "end";
		$room_data[1] = "end\n";

		$room_info['scene'] = "end";
		$room_data[5] = "end\n";

		$room_info['mission_victory'] = "spy";
		$room_data[15] = "spy\n";

		$save_data = "system,warning,red,やりましたね！無事、レジスタンスを妨害し、【スパイ側の勝利】です。\n";
	}

	$file_access = fopen("./dat/" . $room_info['file'],"w");
	array_splice($room_data,16,0,$save_data);

	foreach($room_data as $lines){

		fwrite($file_access,$lines);

	}

	fclose($file_access);

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
	return ($user_count > 4) ? $get_select_member[$user_count - 5][$mission - 1] : 1 ;
}

//------------------------------
//セッションが存在するときの処理
//------------------------------
//
//発言するための機能もここに含まれる
//
if(isset($_SESSION[$room_file])){
	$user_name = $_SESSION["name$room_file"];
	if(isset($_POST['say']) && $_POST['say'] !== ""){
		$_POST['say'] = str_replace(",","",$_POST['say']);
		if(!isset($_POST['color'])){
			$_POST['color'] = "black";
		}
		//エスケープ処理
		$_POST['color'] = escape_string($_POST['color'],20);
		$_POST['say']   = escape_string($_POST['say'],600);
		if(isset($_POST['spysay']) && $is_your_spy && $_POST['spysay'] === "on"){
			$save_data = "$user_name,spysay,".$_POST['color'].",".$_POST['say']."\n";
		}else{
			$save_data = "$user_name,say,".$_POST['color'].",".$_POST['say']."\n";
		}
		array_splice($room_data,16,0,$save_data);    
		$_SESSION["color$room_file"] = $_POST['color'];

		$exsist_user = FALSE;
		foreach(explode(",",(trim($room_data[2]))) as $user_list){
			if ($_SESSION["name$room_file"] === $user_list){
				$exsist_user = TRUE;
			}
		}

		if (!$exsist_user){
			session_destroy();
			die("不正な操作 - 参加していないユーザーから書き込もうとしました");
		}

		$file_access = fopen("./dat/" . $room_file,"w");
		foreach($room_data as $lines){
			fwrite($file_access,$lines);
		}
		fclose($file_access);
	}
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <link rel="stylesheet" href="./main.css" />
    <title><?php 
echo $room_info['name'] . " - レジスタンス・チャット";
?></title>
</head>
<body>
    <div id="main">
    <!-- START MAIN -->
    <div id="header">
    <h1> <?php echo $room_info['name'] . " - レジスタンス・チャット"; ?> </h1>
    <h2> <?php 
switch ($room_info['states']){
case "waiting":
	echo "待機中";
	break;
case "prosessing":
	echo "Mission #" . $room_info['mission']. " - ";
	switch ($room_info['scene']){
	case "team":
		echo "チームを編成します。";
		break;
	case "vote":
		echo "チームを信任するか、選んでください。";
		break;
	case "mission":
		echo "ミッションを成功させるかどうか、選んでください。";
		break;
	}
	break;
	case "end":
		echo "終了しました";
		break;
}
?>
    </h2>

<?php
if($room_info['states'] === "waiting"){
	$rest_people = ($room_info['people'] - count($room_info['users']));
	echo "<p class='message'>参加者があと" . $rest_people . "人必要です。</p>";  
}
if($is_your_connection){
	switch($room_info['scene']){
	case "vote":
		if($is_vote[$_SESSION["name" . $room_info["file"]]]){
			echo "<p>あなたは既に投票しています</p>";
		} else {
			echo "<p>
				<form action='./show.php?file=$room_file' method='POST'>
				<input type='hidden' name='command' value='vote' />
				<input type='radio' name='vote' value='trust'/>信任
				<input type='radio' name='vote' value='veto' />否認
				<input type='submit' name='button_vote'value='投票' />
				</form>
				</p>
				";
		}
		break;
	case "mission":
		if($is_team[$_SESSION["name" . $room_info["file"]]]){
			if($is_mission[$_SESSION["name" . $room_info["file"]]]){
				echo "<p>あなたは既にミッションを遂行しました。</p>";
			} else {
				echo "<p>
					<form action='./show.php?file=$room_file' method='POST'>
					<input type='hidden' name='command' value='mission'/>
					<input type='radio' name='vote' value='success'/> 成功
					";
				if ($is_your_spy){                        
					echo "
						<input type='radio' name='vote' value='falsed' /> 失敗
						";
				}
				echo "<input type='submit' name='button_vote'value='遂行' />
					</form>
					</p>
					";
			}

		} else {
			echo "<p>現在、チームによってミッションを遂行しています。</p>";
		}
	}
}
?>

    <ul class="menu">
<?php

echo "<li><a href='./show.php?file=$room_file'>更新する</a></li>";

?>

	<li><a href='./index.php'>玄関に戻る</a></li>
    </ul>

<?php
if(!isset($_SESSION[$room_file])){
	if($room_info['states'] === "waiting"){
		echo "
			<form action='./show.php?file=$room_file' method='POST'>
			名前：<input type='textarea' name='name'/>
			<input type='submit' value='参加する' />
			</form>
			";
	} elseif($room_info['states'] == "prosessing"){

		echo "<h2>既にゲームが開始しています。</h2>";

	}
} else {
	echo "
		<form action='./show.php?file=$room_file' method='POST'>
		$user_name <input type='textarea' name='say'style='width:80%' /><br />";

	$color_list = array("black","maroon","purple","green","olive","navy","teal");

	foreach ($color_list as $color_item){
		echo "<input type='radio' name='color' value='$color_item'";
		if($_SESSION["color$room_file"] === $color_item){
			echo " checked";
		}

		echo "/><span style='color:$color_item'>■ </span>";
	}
		    /* ゲームバランスため、一時的に使えなくする
		    if ($is_your_spy){
		    echo "<input type='CHECKBOX' name='spysay' value='on' />スパイだけに伝える";
		    }
		     */
	echo "<input type='submit' value='発言する' />
		</form>
		";
}
?>
    <h2>勝敗</h2>
<?php 

echo "<ul id='victory_info'>";
echo "<li id='sucess'>成功:" . $count_success . "</li>";
echo "<li id='falsed'>失敗:"  . $count_not_success . "</li>";
echo "</ul>";
?>
    <h2>参加者たち</h2>
    <ul class='users'>
<?php 
switch($room_info['states']){
case "waiting":
	foreach($room_info['users'] as $show_user){
		echo "<li>$show_user</li>";
	}
	break;
case "prosessing":
	if ($room_info['scene'] === "team" && $is_browse_leader){
		echo "<form action='./show.php?file=$room_file' method='POST'>
			<input type='hidden' name='command' value='select_member' />";

		foreach($room_info['users'] as $show_user){
			echo "<li><input type='CHECKBOX' name='select_user[]' value='$show_user' />";

			if($show_user === $room_info['now_leader']){
				echo "<span class='leader'>【リーダー】</span>";
			}

			if($is_your_spy && $is_spy["$show_user"]){
				echo "<span class='spy'>【スパイ】</span>";
			}

			if($is_team["$show_user"]){
				echo "<span class='team'>【チーム】</span>";
			}

			echo "$show_user</li>";
		}
		echo "<input type='submit' value='選択する' />";
		echo "</form>";  
		echo "<p>" . select_member((int)$room_info['mission'],count($room_info['users'])) . "人選んでください。</p>";
	} else {    
		foreach($room_info['users'] as $show_user){
			echo "<li>";

			if($show_user === $room_info['now_leader']){
				echo "<span class='leader'>【リーダー】</span>";
			}

			if($is_your_spy && $is_spy["$show_user"]){
				echo "<span class='spy'>【スパイ】</span>";
			}
			if($is_team["$show_user"]){
				echo "<span class='team'>【チーム】</span>";
			}


			echo "$show_user</li>";
		}
	}
	break;
case "end":
	foreach($room_info['users'] as $show_user){
		echo "<li>";
		if ($is_spy[$show_user]){
			echo "【スパイ】";
		}
		echo "$show_user</li>";
	}
	break;


}
?>
    </ul>

    </div>
    <div id="log">
    <h2>ログ</h2>
    <ul>
<?php
if(!isset($room_data[16])){
	echo "<li>まだ何も発言されていません。</li>";
} else {
	$room_log = array_splice($room_data,16);
	foreach($room_log as $log_line){
		$log_array = explode(",",$log_line);
		switch($log_array[1]){
		case "say":
			echo "<li style='color:".$log_array[2]."'><span class='name'>".$log_array[0].":</span>".$log_array[3]."</li>";
			break;
		case "spysay":
			if ($is_your_spy){
				echo "<li style='color:".$log_array[2]."'><span class='name'>".$log_array[0]." (スパイに向けて) :</span>".$log_array[3]."</li>";
			}    
			break;
		case "warning":
			echo "<li class='warning'>" . $log_array[3] . "</li>";
			break;
		case "message":
			echo "<li class='message'>" . $log_array[3] . "</li>";
			break;
		}
	}
}
?>
    </ul>
    </div>
<!-- END MAIN -->
</div>
</body>
</html>
