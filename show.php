<?php
ini_set("display_erors","on");
ini_set("session.gc_maxlifetime","1800");


//ページのキャッシュを無効にする
header('Last-Modified:' . gmdate('D, d M Y H:i:s') . 'GMT');
header('pragma: no-cache');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon,26 Jul 1997 05:00:00 GMT');

require_once './lib/registance.php';
require_once './lib/setlist.php';
require_once './lib/message.php';
include_once './lib/eseUtil.php';

$room_file = $_GET['file'];
$room_file = str_replace("/","",$room_file);

if (!file_exists("data/$room_file") && !isset($_GET['file'])){
	die("そのようなファイルは存在しません");
}

$room_data = eseFile("data/$room_file");
$room_info = init_room_data($room_data,$room_file);
$room_info['file'] = $_GET['file'];

session_start();
//---------------------------------
//参加者が現れたときの処理
//
//もしwaitingでなければ参加できない
//---------------------------------
if(!isset($_SESSION[$room_file])){
if($room_info['states'] === "waiting"){
	$_POST['name'] = escape_string($_POST['name'],40);
	$_POST['pass'] = escape_string($_POST['pass'],40);
	//
	//名前の重複チェック
	//
	//
		$is_already_user = FALSE;
		if($_POST['name'] !== "" and isset($_POST['name'])){
			foreach($room_info['users'] as $name_check){
				if($name_check['name'] === $_POST['name']){
					if ($name_check['pass'] !== $_POST['pass']){
						die("名前とパスワードが一致しません");
					} else {
						$_SESSION["name$room_file"] = $_POST['name'];
						$_SESSION[$room_file] = TRUE;
						$is_already_user = TRUE;
					}
				}
			}
			if ($_POST['pass'] === ""){
				die ("パスワードが入力されていません。");
			}
		if (!$is_already_user){
		//セッションの保存
		$_SESSION["name$room_file"] = $_POST['name'];
		$_SESSION[$room_file] = TRUE;
		array_unshift($room_info['users'],array('name' => $_POST['name'] , 'pass' => $_POST['pass']));
		
		//ログに参加者として保存            
		if(trim($room_data[2]) === ""){
			$room_data[2] = $_POST['name'] . "\n";
		} else {
			$room_data[2] = $_POST['name'] . "," . $room_data[2];
		}
		$save_name = $_POST['name'];
		$save_data = "system,warning,red,".$_POST['name']."さんが入室しました。";
		if(!isset($room_data[16])){
			$room_data[16] = $save_data . "," . (string) time() . "\n";
		} else {
			array_splice($room_data,16,0,$save_data . "," . (string) time() . "\n");    
		} 
		write_room_data($room_info,$room_data);
		}
	}
} else {
	foreach($room_info['users'] as $name_check){
		if($name_check['name'] === $_POST['name']){
			if($name_check['pass'] !== $_POST['pass']){
				die("名前とパスワードが一致しません");
			} else {
				$_SESSION['name' . $room_file] = $_POST['name'];
				$_SESSION[$room_file] = TRUE;
			}
		}
	}
}
}




//もし参加者が規定数を超えたなら、状態を変える
if (($room_info['states'] === "waiting")  && (count($room_info['users']) >= $room_info["people"])) {



	//---------------------------------
	//
	//システムの初期化
	//
	//----------------------------------
	reflesh_state($room_info,"prosessing",TRUE);
	$room_info["states"] = "prosessing";
	$room_info = set_waiting_to_processing($room_info);

	$room_info = set_scene("team",$room_info);
	write_room_data($room_info,$room_data);

}

//対象の名前がコネクションしているかどうか、
//またその対象の名前がリーダーか

$is_your_connection = is_your_connection($room_info,$_SESSION);
$is_browse_leader   = is_your_leader($room_info,$_SESSION);

//スパイリストをセットする
$is_your_spy = FALSE;
$is_spy = array();
if ($room_info['states'] === "prosessing" or $room_info['states'] === "end"){
	foreach($room_info['users'] as $set_key_user ){
		$is_spy[$set_key_user['name']] = FALSE;
	}

	foreach($room_info['userrole'] as $set_key_user ){
		$is_spy[$set_key_user['name']] = TRUE;
	}

	if($is_spy[$_SESSION["name" . $room_info['file']]]) {
		$is_your_spy = TRUE;
	}
}

//
// lib/setlist.php に収録
//
$is_team = set_team_list($room_info);
$is_vote = set_is_vote($room_info);
$is_mission = set_is_mission($room_info);

//コマンドによって挙動を変更する
if ($room_info['states'] === "prosessing" && isset($_POST['command'])){
	switch($room_info['scene']){
	case "team":
		if ($is_browse_leader 
			&& ($_POST['command'] === 'select_member') 
			&& (count($_POST['select_user']) === select_member($room_info['mission'],count($room_info['users'])))){

				//チームを選択する
				$save_data = $_SESSION["name$room_file"] . "さんは、【" . implode("、",$_POST['select_user']) . "】を、チームとして選びました。";
				$room_data = set_log($room_data,"system","warning","red",$save_data);

				foreach($_POST['select_user'] as $set_user){
					$is_team[$set_user] = TRUE;
				}
				$room_info['scene'] = "vote";
				$room_info['team_member'] = $_POST['select_user'];
				write_room_data($room_info,$room_data);
				}
		break;
	case "vote":
		if($_POST['command'] === "vote"
			&& $is_your_connection){
				if(!$is_vote['name' . $room_info['file']]){
					$is_vote[$_SESSION['name' . $room_info['file']]] = TRUE;
					$set_vote_user = array($_SESSION["name" . $room_info['file']] , $_POST['vote']);
					array_unshift($room_info['vote_user'],$set_vote_user);
					write_room_data($room_info,$room_data);
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
				write_room_data($room_info,$room_data);
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
			$save_data = "system,message,red," . $get_user[0] . "さんは、【" . ($get_user[1] === "trust" ? "信任" : "不信任") . "】に投票しました。";
			if ($get_user[1] === "trust"){
				$is_team_trust ++;
			}
		$room_data = set_log($room_data,"system","message","red",$save_data);
		}

		//投票者の初期化
		$is_vote = array();
		$room_info['vote_user'] = array();

		if ($is_team_trust > (count($room_info['users']) / 2)){
			$room_data = set_log($room_data,"system","warning","red","system,warning,red,このチーム(" . implode(",",$room_info['team_member']) . ")は信任されました。" . ","  . (string) time() . "\n");   
			$room_info['scene'] = "mission";
		} else {
			$room_data = set_log($room_data,"system","warning","red","このチーム(" . implode(",",$room_info['team_member']) . ")は不信任にされました。" . ","  . (string) time() . "\n");
			$room_info['scene'] = "team";
			$room_info['team_member'] = array();
			foreach($room_info['users'] as $set_key_user){
				$is_team[$set_key_user['name']] = FALSE;
			}

		}
		write_room_data($room_info,$room_data);
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
			$save_data = "このミッションは【成功】しました。";
			array_unshift($room_info['victory_point'],"resistance");
		} else {
			$save_data = "このミッションは、" . $count_falsed . "人の「失敗」への投票で、【失敗】しました。";
			array_unshift($room_info['victory_point'],"spy");
		}
		$room_data = set_log($room_data,"system","warning","red",$save_data);

		//Missionを初期化する
		$room_info = set_scene("team",$room_info);
		$is_team = set_team_list($room_info);
		
		//リーダーの決定
		//デバック用の代入
		if ($debug_mode){
			$room_info['not_leader'] = $room_info['users'];
		}

		write_room_data($room_info,$room_data);

		//そのユーザーがリーダーがどうか判定する
		$is_browse_leader = FALSE;
		if ($room_info['states'] === "prosessing" && $room_info['now_leader'] === $_SESSION["name" . $room_info['file']]){
			$is_browse_leader = TRUE;
		}
	}


$result = count_victory($room_info['victory_point']);
$count_success = $result['success'];
$count_not_success = $result["not_success"];

//もし、ゲームの終了条件なら、ゲームを終了する
if ($room_info['states'] === "processing"){
if ($count_success >= 3 || $count_not_success >= 3){
	reflesh_state($room_info,"end",TRUE);
	$room_info['states'] = "end";
	$room_info['scene'] = "end";

	if ($count_success >= 3){
		$room_info['mission_victory'] = "registance";

		$save_data = "やりましたね！スパイの妨害を勝ち抜き、【レジスタンス側の勝利】です。";

	} elseif ($count_not_success >= 3){
		$room_info['mission_victory'] = "spy";
		$save_data = "やりましたね！無事、レジスタンスを妨害し、【スパイ側の勝利】です。";
	}
	$room_data = set_log($room_data,"system","warning","red",$save_data);
	write_room_data($room_info,$room_data);
}
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
		/*
		if(isset($_POST['spysay']) && $is_your_spy && $_POST['spysay'] === "on"){
			//$save_data = "$user_name,spysay,".$_POST['color'].",".$_POST['say'];
		}else{
			$save_data = $_POST['color'].",".$_POST['say'];
		}
		 */
		$room_data = set_log($room_data,$user_name,"say",$_POST['color'],$_POST['say']);
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

		write_room_data($room_info,$room_data);
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
<script type="text/javascript" src="./lib/jquery-1.7.1.min.js"></script>
<script type="text/javascript">

var reflesh_time = new Date/1e3|0 ;
<?php
echo "var file_name =\"" . $room_info['file'] . "\";\n";
?>
$(function(){
		setInterval(function(){
				$.getJSON('./ajaxpush.php?file=' + file_name + '&time=' + reflesh_time,
					function(resent_log){
			if(resent_log.length !== 0){
			for (var i = 0,max = resent_log.length;i < max; i++){
				switch(resent_log[i]["comd"]){
				case "say":
				//echo "<li style='color:".$log_array[2]."'><span class='name'>".$log_array[0].":</span>".$log_array[3]."</li>";
					$("<li/>").css("color",resent_log[i]["comd"]).css("display","hiddden").html("<span class='name'>" + resent_log[i]["name"] + ":</span>" + resent_log[i]["message"]).fadeIn("slow").prependTo("#show_log");
					break;
				case "warning":
					$("<li/>").addClass("warning").text(resent_log[i]["message"]).fadeIn("slow").prependTo("#show_log");
					location.reload();
					break;
				case "message":
					break;
					}
			//END FOR
			}
			}
		});
			reflesh_time = new Date/1e3|0;
		},9000);
		});
</script>
</head>
<body>
    <div id="main">
    <!-- START MAIN -->
    <div id="header">
    <h1> <?php echo $room_info['name'] . " - レジスタンス・チャット"; ?> </h1>
	<h2> <?php 
//以下、message.phpに移行
	echo room_states_message($room_info);
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
			echo "<p>あなたは既に投票しています。<br />(現在、" . $vote_count . "人が投票しています)</p>";
		} else {
			echo "<p>
				<form action='./show.php?file=$room_file' method='POST'>
				<input type='hidden' name='command' value='vote' />
				<input type='radio' name='vote' value='trust'/>信任
				<input type='radio' name='vote' value='veto' />不信任
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
		echo "
			<form action='./show.php?file=$room_file' method='POST'>
			名前：<input type='textarea' name='name'/><br />
			簡易パスワード:<input type='textarea' name='pass' />
			<input type='submit' value='参加する' />
			</form>
			<p style='font-size:75%;text-align:center;'>簡易パスワードは再ログインの為だけに使います。</p>
			";
	if($room_info['states'] == "prosessing"){

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
		echo "<li>" . $show_user['name'] . "</li>";
	}
	break;
case "prosessing":
	if ($room_info['scene'] === "team" && $is_browse_leader){
		echo "<form action='./show.php?file=$room_file' method='POST'>
			<input type='hidden' name='command' value='select_member' />";

		foreach($room_info['users'] as $show_user){
			echo "<li><input type='CHECKBOX' name='select_user[]' value='" . $show_user['name'] . "' />";

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


			echo $show_user['name'] . "</li>";
		}
	}
	break;
case "end":
	foreach($room_info['users'] as $show_user){
		echo "<li>";
		if ($is_spy[$show_user['name']]){
			echo "【スパイ】";
		}
		echo $show_user['name']."</li>";
	}
	break;


}
?>
    </ul>

    </div>
    <div id="log">
    <h2>ログ</h2>
    <ul id="show_log">
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
