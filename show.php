<?php
//ini_set("display_errors","off");
ini_set("session.gc_maxlifetime","1800");

//ページのキャッシュを無効にする
header('Last-Modified:' . gmdate('D, d M Y H:i:s') . 'GMT');
header('pragma: no-cache');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon,26 Jul 1997 05:00:00 GMT');

require_once './config/debug.php';
require_once './lib/registance.php';
require_once './lib/setlist.php';
require_once './lib/message.php';
include_once './lib/eseUtil.php';
require_once './lib/room_info.php';
require_once './lib/show_info.php';

$room_file = $_GET['file'];
$room_file = str_replace("/","",$room_file);
$is_filter = $_GET['filter'] === "on";
/*
$room_file = "1334743693.dat";
$_POST = array("name" => "opera",
			   "pass" => "opera");
$_SESSION = array("name" . "data/" . $room_file => "chrome");
$_POST = array("command" => "select_member",
			   "select_user" => array("firefox","chrome","opera"));
 */
if (!file_exists("data/$room_file") && !isset($_GET['file'])){
	die("そのようなファイルは存在しません");
}

//初期化
$roominfo = RoomInfo::getInstance();
$roominfo->loadfile("$room_file",FALSE);

session_start();
//---------------------------------
//参加者が現れたときの処理
//
//もしwaitingでなければ参加できない
//---------------------------------
if(!isset($_SESSION[$room_file]) and $_POST['comd'] === 'login'){
$is_already_user = $roominfo->is_already_user($_POST['name']);
if($roominfo->get_states() === "waiting"
   && count($roominfo->get_users()) < $roominfo->get_room_people() ){
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
		$roominfo->add_log("system","message","red",$save_data);
		//if ($_POST['want_spy'] !== "not") {
			$roominfo->set_want_spy_user($_POST['name'],$_POST['want_spy']);
		//}
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

if (($roominfo->get_states() === "waiting") 
	&& (count($roominfo->get_users()) <= count($roominfo->get_vote_start()))
	&& ($roominfo->can_start_game() or ($debug and count($roominfo->get_users()) >= 3 ))
) {



	//---------------------------------
	//
	//システムの初期化
	//
	//----------------------------------
	rewrite_room_dat("processing",$room_file);
	$roominfo->set_room_people(count($roominfo->get_users()));
	$roominfo->set_waiting_to_processing();
	$roominfo->set_scene("team");
	$leader_anonymous_or_not = $roominfo->is_room_anonymous() === "false" ? $roominfo->get_now_leader() : $roominfo->get_username_to_anonymous($roominfo->get_now_leader());
	$roominfo->add_log("system","warning","red","【" . $leader_anonymous_or_not. "】が、リーダーとして選出されました。");
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
	case "vote_start":
		$roominfo->set_vote_start($_SESSION["name" . $roominfo->get_filename()]);
		$roominfo->add_log("system","message","red","【" . $_SESSION["name" . $roominfo->get_filename()] . "】さんは、開始準備が出来たようです。");
		$roominfo->write_room_data();
		break;
	case "logout":
		$result_bool = $roominfo->logout_user($_SESSION["name" . $roominfo->get_filename()],$_POST["pass"]);
		if ($result_bool){
			$is_your_connection = FALSE;
			$roominfo->add_log("system","message","red",$_SESSION["name" . $roominfo->get_filename()] . "さんが退出しました。");
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
				
				$anonymous_or_not = $roominfo->is_room_anonymous() === "false" ? $_SESSION["name" . $roominfo->get_filename()] : $roominfo->get_username_to_anonymous($_SESSION["name" . $roominfo->get_filename()]);

				//チームを選択する
				$save_data = $anonymous_or_not . "さんは、【" . implode("、",$_POST['select_user']) . "】を、チームとして選びました。";
				$roominfo->add_log("system","warning","red",$save_data);
				$roominfo->set_scene("vote");
				$roominfo->set_team_member($_POST['select_user']);
				$roominfo->write_room_data();
				}
		break;
	case "vote":
		if($_POST['command'] === "vote"
			&& $is_your_connection){
				if($roominfo->get_user_vote($_SESSION["name".$roominfo->get_filename()]) !== FALSE){
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
			$save_data .= "<li class='member'>";
			$save_data .= $roominfo->is_room_anonymous() === "false" ? $get_user->username : $get_user->anonymous_name;
			$save_data .= "さん ::【" . ($get_user->vote === "trust" ? "信任" : "不信任") . "】に投票。</li>";
			if ($get_user->vote === "trust"){
				$is_team_trust ++;
			}
		}
		$save_data .= "</ul>";
		$roominfo->add_log("system","message","red",$save_data);
		
		//投票者の初期化
		$roominfo->reset_vote_user();
		$leader_anonymous_or_not = $roominfo->is_room_anonymous() === "false" ? $roominfo->get_now_leader() : $roominfo->get_username_to_anonymous($roominfo->get_now_leader());
		if ($is_team_trust > (count($roominfo->get_users_array()) / 2)){
			$roominfo->add_log("system","warning","red","【" . $leader_anonymous_or_not . "】が選んだチーム(" . implode("、",$roominfo->get_team_member()) . ")は信任されました。");   
			$roominfo->parse_team_to_username();
			$roominfo->set_scene("mission");
		} else {
			$roominfo->add_log("system","warning","red","【" . $leader_anonymous_or_not . "】が選んだチーム(" . implode("、",$roominfo->get_team_member()) . ")は不信任にされました。");
			
			$roominfo->set_scene("team");
			$roominfo->reset_team_member();

			$is_browse_leader   = $roominfo->is_leader($_SESSION["name" . $roominfo->get_filename()]);
			$leader_anonymous_or_not = $roominfo->is_room_anonymous() === "false" ? $roominfo->get_now_leader() : $roominfo->get_username_to_anonymous($roominfo->get_now_leader());
			$roominfo->add_log("system","warning","red","【" . $leader_anonymous_or_not . "】が、リーダーとして選出されました。");

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
		$team_anonymous_or_not = $roominfo->parse_team_to_anonymous();
		$leader_anonymous_or_not = $roominfo->is_room_anonymous() === "false" ? $roominfo->get_now_leader() : $roominfo->get_username_to_anonymous($roominfo->get_now_leader());
		if ($count_falsed === 0){
			$save_data = "このミッションは【成功】しました。";
			$roominfo->add_victory_point("registance");
			$roominfo->set_victory_history($team_anonymous_or_not,$leader_anonymous_or_not,"registance",0);
		} else {
			if ($roominfo->get_mission_no() === 4 
				&& count($roominfo->get_users_array()) > 6) {
				if ($count_falsed === 1) {
					$save_data = "このミッションは、" . $count_falsed . "人の「失敗」への投票がありましたが、無事成功しました。";
				    $roominfo->add_victory_point("registance");	
					$roominfo->set_victory_history($team_anonymous_or_not,$leader_anonymous_or_not,"registance",1);
				} else {
					$save_data = "このミッションは、" . $count_falsed . "人の「失敗」への投票で、【失敗】しました。";
					$roominfo->add_victory_point("spy");
					$roominfo->set_victory_history($team_anonymous_or_not,$leader_anonymous_or_not,"spy",$count_falsed);
				}
				}else{
			$save_data = "このミッションは、" . $count_falsed . "人の「失敗」への投票で、【失敗】しました。";
			$roominfo->add_victory_point("spy");
			$roominfo->set_victory_history($team_anonymous_or_not,$leader_anonymous_or_not,"spy",$count_falsed);
			}
		}
		$roominfo->add_log("system","warning","red",$save_data);
		$pre_team = $roominfo->get_team_member();
		
		//履歴をセットする
		//Missionを初期化する
		$roominfo->set_scene("team");
		$leader_anonymous_or_not = $roominfo->is_room_anonymous() === "false" ? $roominfo->get_now_leader() : $roominfo->get_username_to_anonymous($roominfo->get_now_leader());
		$roominfo->add_log("system","warning","red","【" . $leader_anonymous_or_not . "】が、リーダーとして選出されました。");
		
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

$in_spy = false;
foreach($pre_team as $team_check) {
	if ($roominfo->is_spy($team_check)) {
		$in_spy = true;
	}
}

$last_victory_point = $roominfo->get_victory_point();
$last_victory_item = $last_victory_point[count($last_victory_point) - 1];

if ($in_spy && $last_victory_item === "registance") {
	$check_not_success = $count_not_success + 1;
}


//もし、ゲームの終了条件なら、ゲームを終了する
if ($roominfo->get_states() === "processing"){
	$failure_no = $roominfo->get_failure_team_no();
	if ($count_success >= 3 || $check_not_success >= 3 || $failure_no > 5){
		$in_double_spy = false;
		if ($roominfo->is_room_double_spy()) {
			foreach($pre_team as $team_check) {
				if ($roominfo->is_double_spy($team_check)){
					$in_double_spy = true;
				}
			}
		}
		rewrite_room_dat("end",$room_file);
		$roominfo->set_states("end");
		$roominfo->set_scene("end");
	if ($in_double_spy && ($count_success + $check_not_success ) === 5) {
		$roominfo->set_game_victory("double_spy");
		$save_data = "お見事！スパイもレジスタンスも出し抜き、貴方自身の勝利を手に入れました！【二重スパイ】の勝利です。";
	} elseif ($count_success >= 3){
		$roominfo->set_game_victory("registance");
		$save_data = "やりましたね！スパイの妨害を勝ち抜き、【レジスタンス側の勝利】です。";

	} elseif ($check_not_success >= 3 || $failure_no > 5){
		$roominfo->set_game_victory("spy");
		if ($failure_no > 5){
			$roominfo->add_log("system","warning","red","既に信任投票が五回になりました。このメンバーは機能不全に陥っていると見なされ、スパイ側の勝利となります。");
		}
		if ($in_spy) {
			$roominfo->add_log("system","warning","red","失敗が2つの状態で、チームの中にスパイがいたので、自動的に失敗となります。");
		}
		$save_data = "やりましたね！無事、レジスタンスを妨害し、【スパイ側の勝利】です。";
	}
		$roominfo->add_log("system","warning","red",$save_data);
		$show_spy_list = $roominfo->is_room_anonymous() !== "false" ? $roominfo->get_anonymous_spylist() : $roominfo->get_spylist();
		if ($roominfo->is_room_double_spy() && count($roominfo->get_users()) >= 7) {
			$double_spy_name = array_pop($show_spy_list);
		}
		$roominfo->add_log("system","warning","red","今回は、【" . implode("、",$show_spy_list) . "】の方々がスパイでした。おつかれさま！");
		if ($roominfo->is_room_double_spy() && count($roominfo->get_users()) >= 7) {
			$roominfo->add_log("system","warning","red","そして、二重スパイは【" . $double_spy_name . "】でした！");
		}	
	$roominfo->write_room_data($room_info,$room_data);
}
}

//--------------------------------
//セッションが存在しないときの処理
//--------------------------------
//
//観戦者の発言もここに含まれる
//

if(isset($_POST['show_say']) 
	&& $_POST['show_say'] !== "" 
	&& $_POST['comd'] === 'show_say'){
	$_POST['name']     = escape_string($_POST['name'],30);
	$_POST['show_say'] = str_replace(",","、",$_POST['show_say']);
	$_POST['show_say'] = escape_string($_POST['show_say'],600);
	$roominfo->add_log($_POST['name'],'show_say','white',$_POST['show_say']);
	$_tempSESSION = $_SESSION;
	session_destroy();
	session_start();
	$_SESSION = $_tempSESSION;
	$_SESSION['show' . $roominfo->get_filename()] = $_POST['name'];
	$roominfo->write_room_data();
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
		$_POST['say'] = str_replace(",","、",$_POST['say']);
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
		$exsist_user = FALSE;
		$exsist_user = $roominfo->is_user($user_name);
		if (!$exsist_user){
			session_destroy();
			die("不正な操作 - 参加していないユーザーから書き込もうとしました");
		}
		
		$_tempSESSION = $_SESSION;
		session_destroy();
		session_start();
		$_SESSION = $_tempSESSION;
		$_SESSION["name" . $roominfo->get_filename()] = $user_name;
		$_SESSION[$room_file] = TRUE;
		$_SESSION["color$room_file"] = $_POST['color'];


		if (     $roominfo->get_states() === "processing"
			and  $roominfo->is_room_anonymous() !== "false") {

				$user_name = $roominfo->get_username_to_anonymous($user_name);
			}

		$roominfo->add_log($user_name,$_POST['type'],$_POST['color'],$_POST['say']);
		$roominfo->write_room_data();
	}
}

//
//処理の終了 -> $showinfoに$roominfoを読み込ませ、メッセージを吐き出させるようにする
//

$showinfo = ShowInfo::getInstance();
$showinfo->initialization($roominfo,$_SESSION,$debug);

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
<script type="text/javascript" src="./lib/shortcut.js"></script>
<script type="text/javascript">
	/*
 	* SHORTCUT.JSの処理
 	*/
shortcut.add("Ctrl+Enter",function(){
		$("#submit_say").trigger("click");
	});

var reflesh_time = new Date/1e3|0 ;
var now_reflash_time = reflesh_time + 0;
var preset_title = document.title;
<?php
echo "var file_name =\"" . $room_file . "\";\n";
echo "var post_data =\"" . $_POST['say'] . "\";\n";
?>
$(function(){

		/*
		 * 文字数をカウントするための文字列群
	$("textarea#say").keyup(function(){
		var counter = $(this).val().length;
		if (counter === 0) { counter = "0" }
			$("#counttext").text(counter);
		console.log(counter);
	});
		 */
		
		/*
		文字数以上なら、warningだけ表示する
		 */
	$("input#submit_say").click(function(){
		console.log();
		if ($("textarea#say").val().length > 500){
			$("span#say_warning").fadeIn("slow").delay(1000).fadeOut("slow");
			return false;
		} else {
			return true;
		}
	});
<?php
	echo "var limit_time = " . $showinfo->system_limit_time() . ";\n";
?>

setInterval(function(){
		if (limit_time > 0) {
			limit_time --;
			var limit_minit = Math.floor(limit_time / 60).toString();
			var limit_second = (limit_time % 60).toString();
			if (limit_minit < 10) {
				limit_minit = "0" + limit_minit;
			}
			if (limit_second < 10) {
				limit_second = "0" + limit_second;
			}

			$("#limit_timer").fadeOut(300).fadeIn(300).text("" + limit_minit + ":" + limit_second);
		}
		
		if (limit_time < 1) {
			$("#limit_timer").css("color","#F00");
		}
	},1000);

		/*
		 * ログを更新する際に使われる、Pushの関数
		 */ 
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
						$("<li/>").css("color",resent_log[i]["color"]).css("display","hiddden").html("<div class='sayitem " + resent_log[i]["color"] + "'><span class='log_name'>" + resent_log[i]["name"] + ":</span><p>" + resent_log[i]["message"] + "</p></div>").fadeIn("slow").prependTo("#show_log");
					}
					break;
				case "warning":
					if (resent_log[i]["time"] > now_reflesh_time) {
						$("<li/>").addClass("warning").html(resent_log[i]["message"]).fadeIn("slow").prependTo("#show_log");
					}	
					if($("textarea#say").val() == "") {
						location.replace(location.href);
					} else {
						document.title = "(*)" + preset_title;
						$("#warning_reload").fadeIn("slow");
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
	<h2> <?php echo $roominfo->get_room_states_message(); ?> </h2>
<?php
	if ($roominfo->is_room_anonymous() !== "false"){
		echo "<h3>この部屋は、進行時に入室時のハンドルが隠されます。</h3>";
	}

	if ($roominfo->is_blind_spy()) {
		echo "<h3>この部屋は、スパイ同士はお互いの仲間がわかりません。</h3>";
	}

	if ($roominfo->is_room_double_spy()) {
		echo "<h3>7人以上で、「二重スパイ」が現れます。";
		echo "「二重スパイ」は、スパイ同士からはスパイと表示されています。また、二重スパイは、Mission5の時点で、チームに選ばれ、かつそのチームが信任された場合に勝利となります。</h3>";
	}
?>
	<p class="message" style="display:none;" id="warning_reload">ステータスが更新されました。リロードしてみてください。</p>
<?php

//
//　開始準備ボタンを表示する
//

$showinfo->show_vote_start_button();

//
//　投票状態等の表示
//

$showinfo->show_vote_and_mission();

?>

    <ul class="menu">
<?php

echo "<li><a href='./show.php?file=" . $room_file . "'>更新する</a></li>";

?>
	<li><a href='./index.php'>玄関に戻る</a></li>
	<li><a href='./doc/' target='registance_help'>HELP</a></li>
	</ul>
	<p id="limit_timer">00:00</p>
	<p class="caption">議論時間の目安</p>
	<p class="caption">(あくまで目安です。0分になっても、強制的にゲームは進行しません）</p>
<?php

//
//　発言用フォーム表示部分
//

	$showinfo->show_say_form();

?>
    <h2>勝敗</h2>
	<ul id="victory_info">
		<li id="sucess">成功:<?php echo $count_success; ?> </li>
		<li id="falsed">失敗:<?php echo $count_not_success ?> </li>
	</ul>
	
	<h2>履歴</h2>
		<ul id="history_list">

<?php
	//履歴の表示
	$showinfo->show_history_list();
?>
	</ul>
	</div>
<!-- HEADER END -->
<!-- SANKA LIST START -->
	<div id="sanka_list">
	<div class="wrap_oneline">
    <span class="system_info">参加者たち</span>
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
		echo "<div id='elect_member'> <form action='./show.php?file=$room_file' method='POST'>
			<input type='hidden' name='command' value='select_member' />";
		foreach($roominfo->get_users() as $show_user){
			echo "<li><input type='CHECKBOX' name='select_user[]' value='" . ($roominfo->is_room_anonymous() === "false" ? $show_user->username : $show_user->anonymous_name) . "' />";

			if($show_user->username === $roominfo->get_now_leader()){
				echo "<span class='leader'>【リーダー】</span>";
			}

			if($is_your_spy && $roominfo->is_spy($show_user->username)
			   && !$roominfo->is_blind_spy()){
				echo "<span class='spy'>【スパイ】</span>";
			}

			if($roominfo->is_team_member($show_user->username)){
				echo "<span class='team'>【チーム】</span>";
			}

			if($roominfo->is_room_anonymous() === "false"){
			echo $show_user->username;
			} else {
			echo $show_user->anonymous_name;
			}

			echo"</li>";
		}
		echo "<input type='submit' value='選択する' />";
		echo "</form></div>";  
		echo "<p>" . $roominfo->get_need_team_member() . "人選んでください。</p>";
	} else {    
		foreach($roominfo->get_users() as $show_user){
			echo "<li>";
			if($show_user->username === $roominfo->get_now_leader()){
				echo "<span class='leader'>【リーダー】</span>";
			}

			if($is_your_spy && $roominfo->is_spy($show_user->username)
			   && !$roominfo->is_blind_spy()){
				echo "<span class='spy'>【スパイ】</span>";
			}

			if($roominfo->is_team_member($show_user->username)){
				echo "<span class='team'>【チーム】</span>";
			}

			if($show_user->vote !== NULL){
				echo "<span style='font-weight:bold;color:red'>";
			}

			if($roominfo->is_room_anonymous() === "false") {
				echo $show_user->username;
			} else {
				echo $show_user->anonymous_name;
			}

			if($show_user->vote !== NULL){
				echo "</span>";
			}

			echo "</li>";
		}
	}
	break;
case "end":
	foreach($roominfo->get_users() as $show_user){
		echo "<li>";

		if ($roominfo->is_double_spy($show_user->username)
		    && $roominfo->is_room_double_spy() ){
			echo "【二重スパイ】";
		} elseif ($roominfo->is_spy($show_user->username)){
			echo "【スパイ】";
		}

		if ($roominfo->is_room_anonymous() !== "false") {
			echo $show_user->anonymous_name;
			echo "(" . $show_user->username . ")";
		} else {
			echo $show_user->username;
		}
		echo "</li>";
	}
	break;
}
?>
	</ul>
	</div>
<?php
if ($roominfo->get_states() === "processing"
	&& isset($_SESSION["name" . $roominfo->get_filename()])){
		echo "<div class='wrap_float'>";
		echo "<ul><li><span class='system_info'>貴方の陣営</span></li>";

		if ($roominfo->is_double_spy($_SESSION["name" . $roominfo->get_filename()])
			&& $roominfo->is_room_double_spy()) {
		echo "<li class='your_party'><span class='spy'>二重スパイ</span></li>";
		} elseif ($roominfo->is_spy($_SESSION["name" . $roominfo->get_filename()])){
		echo "<li class='your_party'><span class='spy'>スパイ</span></li>";
		} else {
		echo "<li class='your_party'><span class='name'>レジスタンス</span></li>";
		}
		echo "</ul></div>";
	}
?>

<?php

//
//Processing時に表示する情報の一覧
//

$showinfo->show_processing_info();

?>
<?php

//<h2>システム</h2>
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
<a href="https://twitter.com/share" class="twitter-share-button" data-url="http://www45045u.sakura.ne.jp/registance/show.php?file='. $room_file . '" data-via="ResistanceChat" data-lang="ja">ツイート</a>
<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
</div>

<!--LOG_FILTER START --!>
<div id="log_filter">
<?php
	echo "<form action='./show.php' method='GET'> 
		 <input type='hidden' name='file' value='$room_file'>";
?>
		<input type='hidden' name='filter' value='on'>
		<div class="wrap_oneline">
		<ul>
		<li>
			<h3>フィルター</h3>
		</li>
		<li><input type="checkbox" name="system" />システム(投票結果など
)</li>
		<li><input type="checkbox" name="dialog" />独り言</li>
		<li><input type="submit" value="だけを表示" /></li>
		</ul>
	</div>
	</form>

	<form action="./show.php" method="GET">
<?php
	echo "<input type='hidden' name='file' value='$room_file'>";
?>
	<div class="wrap_oneline">
		<ul>
			<li><h3>検索</h3></li>
			<li><input type="textarea" name="search" />を</li>
			<li><input type="submit" value="検索" /></li>
		</ul>
	</div>
	</form>
<!-- LOG_FILTER END--!>
</div>


<!-- LOG START -->
    <div id="log">
    <h2>ログ</h2>
    <ul id="show_log">
<?php
	$room_data = $roominfo->get_raw_roomdata();
if(!isset($room_data[16])){
	echo "<li>まだ何も発言されていません。</li>";
} else {
	$room_log = array_splice($room_data,16);
	if (mb_strlen($_GET['search'],"UTF8") > 100) {
		$_GET['search'] = NULL;
	}
	foreach($room_log as $log_line){
		if (!isset($_GET['search'])
			or (strpos($log_line,$_GET['search']) !== False)) {
				$log_array = explode(",",$log_line);
				if (isset($_GET['search'])){
					$log_array[3] = str_replace($_GET['search'],"<span class='search_result'>" . $_GET['search'] . "</span>",$log_array[3]);
				}
		switch($log_array[1]){
		case "say":
			if (!$is_filter){
			echo "<li style='color:".$log_array[2]."'><div class='sayitem ". $log_array[2] . "'><span class='log_name'>".$log_array[0].":</span><p>".$log_array[3]."</p></div><span class='timestamp'>" . date("G時i分s秒に発言",$log_array[4]) ."</span></li>";
			}
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
				if ($log_array[0] === $_SESSION["name" . $roominfo->get_filename()]
				or $log_array[0] === $roominfo->get_username_to_anonymous($_SESSION["name" . $roominfo->get_filename()])) {
					if (!$is_filter or ($is_filter and $_GET['dialog'] === "on")){
						echo "<li class='dialog' style='color:" . $log_array[2] .";border: 2px dashed " . $log_array[2] . "'>" . "<span class='name'>" . $log_array[0] . "</span><p>" . $log_array[3] . "</p></li>" ;
					}
				}
				break;
			case "end":
					echo "<li class='dialog' style='color:" . $log_array[2] ."'>" . "<span class='name'>" . $log_array[0] . "</span>" . $log_array[3] . "</li>" ;
			}
			break;
			case "warning":
			if(!$is_filter or ($is_filter and $_GET['system'] === "on")){
				echo "<li class='warning'>" . $log_array[3] . "</li>";
			}
			break;
			case "message":
			if (!$is_filter or ($is_filter and $_GET['system'] === "on")){
				echo "<li class='message'>" . $log_array[3] . "</li>";
			}
			break;
			case "show_say":
				if(($roominfo->get_states() === 'waiting'
					or $roominfo->get_states() === 'end')
					or (!$showinfo->is_your_connection())){
					echo "<li class='show_say' style='color:#CCC;'><div class='sayitem show_say'><p><span class='name'>" . $log_array[0] . "(一般市民)の感想 : </span></p><p>" . $log_array[3] . "</p></div></li>";
					}
			break;
		}
		}
	//FOREACH END
	}
// IF END
}
?>
    </ul>
    </div>
<!-- END MAIN -->
</div>
</body>
</html>
