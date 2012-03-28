<?php
//ini_set("display_errors","off");
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
require_once './lib/room_info.php';

$room_file = $_GET['file'];
$room_file = str_replace("/","",$room_file);
/*
$room_file = "1332850862.dat";
$_POST = array("name" => "opera",
			   "pass" => "opera");
$_SESSION = array("name" . "data/" . $room_file => "chrome");
$_POST = array("command" => "select_member",
			   "select_user" => array("firefox","chrome","opera"));
 */
if (!file_exists("data/$room_file") && !isset($_GET['file'])){
	die("そのようなファイルは存在しません");
}

$roominfo = RoomInfo::getInstance();
$roominfo->loadfile("data/$room_file");

session_start();
//---------------------------------
//参加者が現れたときの処理
//
//もしwaitingでなければ参加できない
//---------------------------------
if(!isset($_SESSION[$room_file])){
$is_already_user = $roominfo->is_already_user($_POST['name']);
if($roominfo->get_states() === "waiting"){
	$_POST['name'] = escape_string($_POST['name'],40);
	$_POST['pass'] = escape_string($_POST['pass'],40);
	//名前の重複チェック
	
	if($_POST['name'] !== "" and isset($_POST['name'])){
		if($is_already_user !== false){
			if ($is_already_user->pass !== $_POST['pass']){
				die("名前とパスワードが一致しません");
			} else {
				$_SESSION["name" . $roominfo->get_filename()] = $_POST['name'];
				$_SESSION[$room_file] = TRUE;
				}
			}
			if ($_POST['pass'] === ""){
				die ("パスワードが入力されていません。");
			}
		if ($is_already_user === FALSE){
			//セッションの保存
		$_SESSION["name" . $roominfo->get_filename()] = $_POST['name'];
		$_SESSION[$room_file] = TRUE;
		
		$save_data = $_POST['name']."さんが入室しました。";
		
		$roominfo->add_user($_POST['name'],$_POST['pass']);	
		$roominfo->add_log("system","warning","red",$save_data);
		if ($_POST['want_spy'] === "want") {
			$roominfo->set_want_spy_user($_POST['name']);
		}
		$roominfo->write_room_data();
		}
	}
} else {
		if($is_already_user !== FALSE){
			if($is_already_user->pass !== $_POST['pass']){
				die("名前とパスワードが一致しません");
			} elseif ($is_already_user->pass === $_POST['pass']
				&& $is_already_user !== FALSE
				&& $_POST['pass'] !== NULL)
			{
				$_SESSION['name' . $roominfo->get_filename() ] = $_POST['name'];
				$_SESSION[$room_file] = TRUE;
			}
		}
	}
}




//もし参加者が規定数を超えたなら、状態を変える

if (($roominfo->get_states() === "waiting")  && (count($roominfo->get_users()) >= $roominfo->get_room_people())) {



	//---------------------------------
	//
	//システムの初期化
	//
	//----------------------------------
	rewrite_room_dat("processing",$room_file);
	$roominfo->set_waiting_to_processing();
	$roominfo->set_scene("team");
	$roominfo->add_log("system","warning","red","【" . $roominfo->get_now_leader() . "】が、リーダーとして選出されました。");
	$roominfo->write_room_data();
}

//対象の名前がコネクションしているかどうか、
//またその対象の名前がリーダーか

$is_your_connection = $roominfo->is_user($_SESSION["name" . $roominfo->get_filename()]);
$is_browse_leader   = $roominfo->is_leader($_SESSION["name" . $roominfo->get_filename()]);

$is_your_spy = $roominfo->is_spy($_SESSION["name" . $roominfo->get_filename()]);
//
// いらなくなる予定
// (動作次第、削除を行う) 
//
//$is_team = set_team_list($room_info);
//$is_vote = set_is_vote($room_info);
//$is_mission = set_is_mission($room_info);


//コマンドによって挙動を変更する
if ($roominfo->get_states() === "waiting"){
	switch($_POST['command']){
	case "logout":
		$result_bool = $roominfo->logout_user($_SESSION["name" . $roominfo->get_filename()],$_POST["pass"]);
		if ($result_bool){
			$is_your_connection = FALSE;
			$roominfo->add_log("system","warning","red",$_SESSION["name" . $roominfo->get_filename()] . "さんが退出しました。");
			session_destroy();
			$_SESSION[$room_file] = NULL;
			$_SESSION["name" . $roominfo->get_filename()] = NULL;
			$roominfo->write_room_data();
		} else {
			die("パスワードが一致しませんでした");
		}
		break;
	}
} elseif ($roominfo->get_states() === "processing" 
	&& isset($_POST['command'])){

	switch($roominfo->get_scene()){
	case "team":
		if ($is_browse_leader 
			&& ($_POST['command'] === 'select_member') 
			&& (count($_POST['select_user']) === $roominfo->get_need_team_member())){

				//チームを選択する
				$save_data = $_SESSION["name" . $roominfo->get_filename()] . "さんは、【" . implode("、",$_POST['select_user']) . "】を、チームとして選びました。";
				$roominfo->add_log("system","warning","red",$save_data);
				$roominfo->set_scene("vote");
				$roominfo->set_team_member($_POST['select_user']);
				$roominfo->write_room_data();
				}
		break;
	case "vote":
		if($_POST['command'] === "vote"
			&& $is_your_connection){
				if($roominfo->get_user_vote($_SESSION["name".$roominfo->get_filename()]) !== FALSE
				&& $roominfo->get_user_vote($_SESSION["name". $roominfo->get_filename()]) === NULL){
					$roominfo->set_vote_user($_SESSION["name".$roominfo->get_filename()],$_POST['vote']);
					$roominfo->write_room_data($room_info,$room_data);
				}
			} 
		break;

	case "mission":
		if($_POST['command'] === "mission"
			&& $is_your_connection
			&& $roominfo->get_user_mission($_SESSION['name'.$roominfo->get_filename()]) === NULL
			&& $roominfo->is_team_member($_SESSION['name' . $roominfo->get_filename()])){
				$roominfo->set_mission_user($_SESSION['name' . $roominfo->get_filename()],$_POST['vote']);
				$roominfo->write_room_data();
			}
		break;
	}
}
//もし投票者と参加者が一緒になり、かつ信任が過半数なら、
//次の状態に移行する
if($roominfo->get_scene() === "vote"){
	if($roominfo->count_vote() >= $roominfo->count_user()){
		$is_team_trust = 0;
		$save_data = "<ul>";
		$save_data .= "<li><h3>投票結果</h3></li>";
		//投票者の統計をログに流す
		foreach($roominfo->get_users() as $get_user){
			$save_data .= "<li class='member'>" . $get_user->username . "さん ::【" . ($get_user->vote === "trust" ? "信任" : "不信任") . "】に投票。</li>";
			if ($get_user->vote === "trust"){
				$is_team_trust ++;
			}
		}
		$save_data .= "</ul>";
		$roominfo->add_log("system","message","red",$save_data);
		
		//投票者の初期化
		$roominfo->reset_vote_user();

		if ($is_team_trust > (count($roominfo->get_users_array()) / 2)){
			$roominfo->add_log("system","warning","red","【" . $roominfo->get_now_leader() . "】が選んだチーム(" . implode("、",$roominfo->get_team_member()) . ")は信任されました。");   
			$roominfo->set_scene("mission");
		} else {
			$roominfo->add_log("system","warning","red","【" . $roominfo->get_now_leader() . "】が選んだチーム(" . implode("、",$roominfo->get_team_member()) . ")は不信任にされました。");
			
			$roominfo->set_scene("team");
			$roominfo->reset_team_member();

			$is_browse_leader   = $roominfo->is_leader($_SESSION["name" . $roominfo->get_filename()]);

			$roominfo->add_log("system","warning","red","【" . $roominfo->get_now_leader() . "】が、リーダーとして選出されました。");

		}
			$roominfo->write_room_data($room_info,$room_data);
	}        
}

//チームメンバーが全員ミッションを選んだら、
//ミッション成功判定を行う
if($roominfo->get_scene() === "mission"
	&& $roominfo->count_mission_user() >= $roominfo->count_team_member() ){
		//失敗かどうかを判定する    
		$count_falsed = $roominfo->count_failure();    
		
		if ($count_falsed === 0){
			$save_data = "このミッションは【成功】しました。";
			$roominfo->add_victory_point("registance");
			$roominfo->set_victory_history($roominfo->get_team_member(),$roominfo->get_now_leader(),"registance",0);
		} else {
			if ($roominfo->get_mission_no() === 4 
				&& count($roominfo->get_users_array()) > 6) {
				if ($count_falsed === 1) {
					$save_data = "このミッションは、" . $count_falsed . "人の「失敗」への投票がありましたが、無事成功しました。";
				    $roominfo->add_victory_point("registance");	
					$roominfo->set_victory_history($roominfo->get_team_member(),$roominfo->get_now_leader(),"registance",1);
				} else {
					$save_data = "このミッションは、" . $count_falsed . "人の「失敗」への投票で、【失敗】しました。";
					$roominfo->add_victory_point("spy");
					$roominfo->set_victory_history($roominfo->get_team_member(),$roominfo->get_now_leader(),"spy",$count_falsed);
				}
				}else{
			$save_data = "このミッションは、" . $count_falsed . "人の「失敗」への投票で、【失敗】しました。";
			$roominfo->add_victory_point("spy");
			$roominfo->set_victory_history($roominfo->get_team_member(),$roominfo->get_now_leader(),"spy",$count_falsed);
			}
		}
		$roominfo->add_log("system","warning","red",$save_data);
		
		//履歴をセットする

		//Missionを初期化する
		$roominfo->set_scene("team");
		$roominfo->add_log("system","warning","red","【" . $roominfo->get_now_leader() . "】が、リーダーとして選出されました。");
		
		//リーダーの決定
		//デバック用の代入
		if ($debug_mode){
			$room_info['not_leader'] = $room_info['users'];
		}

		$roominfo->write_room_data();

		//そのユーザーがリーダーがどうか判定する
		$is_browse_leader = $roominfo->is_leader($_SESSION["name" . $roominfo->get_filename()]);
	}


$result = $roominfo->get_victory_point_count_array();
$count_success = $result['registance'];
$count_not_success = $result["spy"];

//もし、ゲームの終了条件なら、ゲームを終了する
if ($roominfo->get_states() === "processing"){
	$failure_no = $roominfo->get_failure_team_no();
	if ($count_success >= 3 || $count_not_success >= 3 || $failure_no > 5){
		rewrite_room_dat("end",$room_file);
		$roominfo->set_states("end");
		$roominfo->set_scene("end");
	if ($count_success >= 3){
		$roominfo->set_game_victory("registance");
		$save_data = "やりましたね！スパイの妨害を勝ち抜き、【レジスタンス側の勝利】です。";

	} elseif ($count_not_success >= 3 || $failure_no > 5){
		$roominfo->set_game_victory("spy");
		if ($failure_no > 5){
			$roominfo->add_log("system","warning","red","既に信任投票が五回になりました。このメンバーは機能不全に陥っていると見なされ、スパイ側の勝利となります。");
		}
		$save_data = "やりましたね！無事、レジスタンスを妨害し、【スパイ側の勝利】です。";
	}
	$roominfo->add_log("system","warning","red",$save_data);
	$roominfo->add_log("system","warning","red","今回は、【" . implode("、",$roominfo->get_spylist()) . "】の方々がスパイでした。おつかれさま！");
	$roominfo->write_room_data($room_info,$room_data);
}
}

//------------------------------
//セッションが存在するときの処理
//------------------------------
//
//発言するための機能もここに含まれる
//

if(isset($_SESSION[$room_file])){
	$user_name = $_SESSION["name". $roominfo->get_filename()];
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
		$roominfo->add_log($user_name,$_POST['type'],$_POST['color'],$_POST['say']);
		$_tempSESSION = $_SESSION;
		session_destroy();
		session_start();
		$_SESSION = $_tempSESSION;
		$_SESSION["name" . $roominfo->get_filename()] = $user_name;
		$_SESSION[$room_file] = TRUE;
		$_SESSION["color$room_file"] = $_POST['color'];

		$exsist_user = FALSE;
		$exsist_user = $roominfo->is_user($user_name);
		if (!$exsist_user){
			session_destroy();
			die("不正な操作 - 参加していないユーザーから書き込もうとしました");
		}

		$roominfo->write_room_data();
	}
}

?>

<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN" "http://www.w3.org/TR/html4/loose.dtd">
<html lang="ja">
<head>
    <meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
    <link rel="stylesheet" href="./main.css" />
    <title><?php 
echo $roominfo->get_name() . " - レジスタンス・チャット";
?></title>
<script type="text/javascript" src="./lib/jquery-1.7.1.min.js"></script>
<script type="text/javascript">

var reflesh_time = new Date/1e3|0 ;
var now_reflash_time = reflesh_time + 0;

<?php
echo "var file_name =\"" . $room_file . "\";\n";
echo "var post_data =\"" . $_POST['say'] . "\";\n";
?>
$(function(){
	setInterval(function(){
		now_reflesh_time = reflesh_time + 0;
			$.getJSON('./ajaxpush.php?file=' + file_name + '&time=' + now_reflesh_time,
					function(resent_log){
			if(post_data !== ""){
				for(var i = 0,max =resent_log.length; i < max;i++){
					if (resent_log[i]["message"] === post_data){
						now_reflesh_time = resent_log[i]["time"];
						post_data = "";
					}
				}
			}
			if(resent_log.length !== 0){
			for (var i = 0,max = resent_log.length;i < max; i++){
				resent_log[i]["time"] = resent_log[i]["time"];
				switch(resent_log[i]["comd"]){
				case "say":
					if (resent_log[i]["time"] > now_reflesh_time){
						//echo "<li style='color:".$log_array[2]."'><div class='sayitem' style='border:2px solid ". $log_array[2] . "'><span class='name'>".$log_array[0].":</span><p>".$log_array[3]."</p></div></li>";
						$("<li/>").css("color",resent_log[i]["color"]).css("display","hiddden").html("<div class='sayitem " + resent_log[i]["color"] + "'><span>" + resent_log[i]["name"] + ":</span><p>" + resent_log[i]["message"] + "</p></div>").fadeIn("slow").prependTo("#show_log");
					}
					break;
				case "warning":
					if (resent_log[i]["time"] > now_reflesh_time) {
						$("<li/>").addClass("warning").html(resent_log[i]["message"]).fadeIn("slow").prependTo("#show_log");
					}	
					if($("textarea#say").val() == "") {
						location.replace(location.href);
					}	
					break;
				case "message":
					if (resent_log[i]["time"] > now_reflesh_time) {
						$("<li/>").addClass("message").html(resent_log[i]["message"]).fadeIn("slow").prependTo("#show_log");
					}	
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
    <h1> <?php echo $roominfo->get_name() . " - レジスタンス・チャット"; ?> </h1>
	<h2> <?php 
//以下、message.phpに移行
	echo $roominfo->get_room_states_message();
?>
    </h2>

<?php
if($roominfo->get_states() === "waiting"){
	$rest_people = ($roominfo->get_room_people() - count($roominfo->get_users_array()));
	echo "<p class='message'>参加者があと" . $rest_people . "人必要です。</p>";  
}
if($is_your_connection){
	switch($roominfo->get_scene()){
	case "vote":
		if($roominfo->get_user_vote($_SESSION["name" . $roominfo->get_filename()]) !== FALSE
		&& $roominfo->get_user_vote($_SESSION["name" . $roominfo->get_filename()]) !== NULL){
			echo "<p>あなたは既に投票しています。<br />(現在、" . $roominfo->count_vote() . "人が投票しています)</p>";
		} else {
			echo "<p>
				<form action='./show.php?file=" . $room_file
				. "' method='POST'>
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
		if($roominfo->is_team_member($_SESSION["name" . $roominfo->get_filename()])){
			if($roominfo->get_user_mission($_SESSION["name" . $roominfo->get_filename()])){
				echo "<p>あなたは既にミッションを遂行しました。</p>";
			} else {
				echo "<p>
					<form action='./show.php?file=$room_file' method='POST'>
					<input type='hidden' name='command' value='mission'/>
					<input type='radio' name='vote' value='success'/> 成功
					";
				if ($roominfo->is_spy($_SESSION["name" . $roominfo->get_filename() ])){                        
					echo "
						<input type='radio' name='vote' value='failure' /> 失敗
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

echo "<li><a href='./show.php?file=" . $room_file . "'>更新する</a></li>";
?>
	<li><a href='./index.php'>玄関に戻る</a></li>
    </ul>

<?php
if(!isset($_SESSION[$room_file])){
		echo "
			<form action='./show.php?file=$room_file' method='POST'>
			名前：<input type='textarea' name='name'/><br />
			簡易パスワード:<input type='textarea' name='pass' />
			<select name='want_spy'>
				<option value='not'>スパイを希望しない</option>
				<option value='want'>スパイを希望する</option>
			</select>
			<input type='submit' value='参加する' />
			</form>
			<p style='font-size:75%;text-align:center;'>簡易パスワードは再ログインの為だけに使います。</p>
			<p style='font-size:75%;text-align:center;'>重要なパスワードを入力しないでください。</p>
			";
	if($roominfo->get_states() === "processing"){

		echo "<h2>既にゲームが開始しています。</h2>";

	}
} else {
	echo "
		<form action='./show.php?file=$room_file' method='POST'>
		$user_name <textarea name='say'style='width:95%' rows='2' id='say' /></textarea><br />";

	$color_list = array("black","maroon","purple","green","olive","navy","teal");
	foreach ($color_list as $color_item){
		echo "<input type='radio' name='color' value='$color_item'";
		if($_SESSION["color" . $room_file ] === $color_item){
			echo " checked";
		}

		echo "/><span style='color:$color_item'>■ </span>";
	}
		    /* ゲームバランスため、一時的に使えなくする
		    if ($is_your_spy){
		    echo "<input type='CHECKBOX' name='spysay' value='on' />スパイだけに伝える";
		    }
			 */
	echo "<select name='type'>
			<option value='say'>通常の発言</option> 
			<option value='dialog'>独り言</option>   
			</select>";
	echo "<input type='submit' value='発言する' />
		</form>
		";
	/*
	if ($_SESSION[$room_file]) {
		echo "
			<h3>観察者チャット</h3>
			<form action='./show.php?file=$room_file' method='POST'>
				
			</form>
			";
	}
	 */
}
?>
    <h2>勝敗</h2>
<?php 

echo "<ul id='victory_info'>";
echo "<li id='sucess'>成功:" . $count_success . "</li>";
echo "<li id='falsed'>失敗:"  . $count_not_success . "</li>";
echo "</ul>";
?>
	<h2>履歴</h2>
	<ul id="history_list">
<?php
if($roominfo->get_mission_no() > 1) {
	$counter = 1;
	foreach($roominfo->get_victory_history() as $history_item){
		echo "<li class='" . $history_item["victory_point"] . "'>";
		echo "<span class='name'>Mission";
		echo $counter;
		echo "</span>";
		echo "<ul><li><span>リーダー </span>:: 【" .  $history_item["team_leader"] . "】</li>";
		echo "<li><span>メンバー</span> :: 【" . implode("、",$history_item["team_member"]) . "】</li>";
		echo "<li>";
		echo $history_item["failure_member"] > 0 ? $history_item["failure_member"] . "人の投票により" : "";
		echo "ミッションは【";
	    echo $history_item["victory_point"] === "registance" ? "成功" : "失敗";
		echo "】</li></ul>";
		echo "</li>";
		$counter++;
	}
	
	}
?>
	</ul>
    </div>
    <div id="log">
    <h2>ログ</h2>
    <ul id="show_log">
<?php
$room_data = $roominfo->get_raw_roomdata();
if(!isset($room_data[16])){
	echo "<li>まだ何も発言されていません。</li>";
} else {
	$room_log = array_splice($room_data,16);
	foreach($room_log as $log_line){
		$log_array = explode(",",$log_line);
		switch($log_array[1]){
		case "say":
			echo "<li style='color:".$log_array[2]."'><div class='sayitem ". $log_array[2] . "'><span>".$log_array[0].":</span><p>".$log_array[3]."</p></div></li>";
			break;
		/*
		case "spysay":
			if ($is_your_spy){
				echo "<li style='color:".$log_array[2]."'><span class='name'>".$log_array[0]." (スパイに向けて) :</span>".$log_array[3]."</li>";
			}    
			break;
		 */
		case "dialog":
			switch($roominfo->get_states()){
			case "waiting":
			case "processing":
				if ($log_array[0] === $_SESSION["name" . $roominfo->get_filename()]) {
					echo "<li class='dialog' style='color:" . $log_array[2] ."'>" . "<span class='name'>" . $log_array[0] . "</span><p>" . $log_array[3] . "</p></li>" ;
				}
				break;
			case "end":
					echo "<li class='dialog' style='color:" . $log_array[2] ."'>" . "<span class='name'>" . $log_array[0] . "</span>" . $log_array[3] . "</li>" ;
			}
			break;
		case "warning":
			echo "<li class='warning'>" . $log_array[3] . "</li>";
			break;
		case "message":
			echo "<li class='message'>" . $log_array[3] . "</li>";
			break;
		case "viewsay":
			break;
		}
	}
}
?>
    </ul>
    </div>
	<div id="sanka_list">
<?php
if ($roominfo->get_states() === "processing"
	&& isset($_SESSION["name" . $roominfo->get_filename()])){
		echo "<h2>貴方の陣営</h2><ul>";
		if ($roominfo->is_spy($_SESSION["name" . $roominfo->get_filename()])){
		echo "<li class='your_party'><span class='spy'>スパイ</span></li>";
		} else {
		echo "<li class='your_party'><span class='name'>レジスタンス</span></li>";
		}
		echo "</ul>";
	}
?>
    <h2>参加者たち</h2>
    <ul class='users'>
<?php 
switch($roominfo->get_states()){
case "waiting":
	foreach($roominfo->get_users() as $show_user){
		echo "<li>" . $show_user->username . "</li>";
	}
	break;
case "processing":
	if ($roominfo->get_scene() === "team" && $is_browse_leader){
		echo "<form action='./show.php?file=$room_file' method='POST'>
			<input type='hidden' name='command' value='select_member' />";
		foreach($roominfo->get_users() as $show_user){
			echo "<li><input type='CHECKBOX' name='select_user[]' value='" . $show_user->username . "' />";

			if($show_user->username === $roominfo->get_now_leader()){
				echo "<span class='leader'>【リーダー】</span>";
			}

			if($is_your_spy && $roominfo->is_spy($show_user->username)){
				echo "<span class='spy'>【スパイ】</span>";
			}

			if($roominfo->is_team_member($show_user->username)){
				echo "<span class='team'>【チーム】</span>";
			}

			echo $show_user->username . "</li>";
		}
		echo "<input type='submit' value='選択する' />";
		echo "</form>";  
		echo "<p>" . $roominfo->get_need_team_member() . "人選んでください。</p>";
	} else {    
		foreach($roominfo->get_users() as $show_user){
			echo "<li>";
			if($show_user->username === $roominfo->get_now_leader()){
				echo "<span class='leader'>【リーダー】</span>";
			}

			if($is_your_spy && $roominfo->is_spy($show_user->username)){
				echo "<span class='spy'>【スパイ】</span>";
			}

			if($roominfo->is_team_member($show_user->username)){
				echo "<span class='team'>【チーム】</span>";
			}


			echo $show_user->username . "</li>";
		}
	}
	break;
case "end":
	foreach($roominfo->get_users() as $show_user){
		echo "<li>";
		if ($roominfo->is_spy($show_user->username)){
			echo "【スパイ】";
		}
		echo $show_user->username ."</li>";
	}
	break;
}
?>
    </ul>
<?php

if ($roominfo->get_states() === "processing") {
	echo '<h2>信任投票回数</h2>';
	echo '<ul><li>' . $roominfo->get_failure_team_no() . "回目</li></ul>";
	echo '
	<h2>スパイの人数</h2>
	<ul>
	<li style="border:none;">
	';
	echo $roominfo->count_spy();
	
	echo '人
		</li>
	</ul>
	<h2>選ばれる人数</h2>
	<ul>';
	$counter = 0;
	foreach($roominfo->get_need_team_array() as $team_number){
		$counter ++;
		if ($roominfo->get_mission_no() === $counter) {
			echo "<li id='now_mission'>";
		} else {
			if ($roominfo->get_mission_no() < $counter) {
				echo "<li>";
			} else {
				echo "<li style='color:#AAA;'>";
			}
		}
	echo "M#$counter :: $team_number</li>";
	}
}
?>
	</ul>
<h2>システム</h2>
<?php
if ($roominfo->get_states() === "waiting" && isset($_SESSION["name" . $roominfo->get_filename()])) {
	echo "<h3>退出する</h3>";
	echo "<form action='./show.php?file=$room_file' method='POST'>
		  <input type='hidden' name='command' value='logout' />
		  Pass <input type='textarea' name='pass' size='5'/>
		  <input type='submit' value='退出する' />
		  ";
	echo "<p>※ゲームが始まると退出できなくなります※</p>";
	echo "</form>";
}

?>
	</div>

<!-- END MAIN -->
</div>
</body>
</html>
