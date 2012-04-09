<?php
ini_set("display_errors","on");
require_once("./config/debug.php");
include_once("./lib/eseUtil.php");

//room.datが存在するかを保持する。
$room_exist = file_exists("./data/room.dat");


if ($room_exist){

	$room_file = eseFile("./data/room.dat");

	$waiting_room = array();
	$prosessing_room = array();


	foreach ($room_file as $room_data){
		$room_data = str_replace("\n","",$room_data);
		$room_arraydata =  explode(",",$room_data);
		switch($room_arraydata[2]){
		case "waiting":
			array_push($waiting_room,$room_data);
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
	<script type="text/javascript" src="./lib/jquery-1.7.1.min.js"></script>
<script type="text/javascript">
$(function(){
	$('input#makeroom').click(function(){
		return confirm("「" + $("input#roomname").val() + "」の名前で、" + $("select#roompeople").val() + "人の部屋を作成します。よろしいですか？");
	});
});
	</script>
	<meta http-equiv="Content-Type" content="text/html;charset=UTF-8">
	<title>レジスタンス・チャット</title>
</head>
<body>
<div id="fb-root"></div>
<script>(function(d, s, id) {
  var js, fjs = d.getElementsByTagName(s)[0];
  if (d.getElementById(id)) return;
  js = d.createElement(s); js.id = id;
  js.src = "//connect.facebook.net/ja_JP/all.js#xfbml=1";
  fjs.parentNode.insertBefore(js, fjs);
}(document, 'script', 'facebook-jssdk'));</script>

    <div id="header">
	<h1><img src="./regi-logo.png"></h1>
	<h2>過去ログ</h2>
		<p><a href="pastlog.php">過去に立てられた部屋</a></p>
		<p>始めての方や、レジスタンスに興味を持った方は、ここから過去のログを読むと、だいたいの流れを掴むことができるかもしれません。</a>
	<h2>連絡</h2>
	<p><a href="http://twitter.com/esehara/">似非原重雄</a></p>
	<p><a href="http://twitter.com/resistancechat/">レジスタンスチャット告知用アカウント</a></p>
	<p>mail to esehara at gmail dot com</p>
	<p>なんか変なところなどが見つかりましたら、教えてくれるとありがたいです。</p>
<div class="fb-like" data-href="http://www45045u.sakura.ne.jp/registance/" data-send="true" data-layout="button_count" data-width="450" data-show-faces="true"></div>
<br />
<a href="https://twitter.com/share" class="twitter-share-button" data-url="http://www45045u.sakura.ne.jp/registance/" data-via="ResistanceChat" data-lang="ja">ツイート</a>
<script>!function(d,s,id){var js,fjs=d.getElementsByTagName(s)[0];if(!d.getElementById(id)){js=d.createElement(s);js.id=id;js.src="//platform.twitter.com/widgets.js";fjs.parentNode.insertBefore(js,fjs);}}(document,"script","twitter-wjs");</script>
	<h2>概要</h2>
	<p>　一言で述べるならば、<a href="http://www.wolfg.x0.com/">汝は人狼なりや？</a>みたいなシステムです。しかし、人狼のシステムを改良しており、下のような違いがあります。</p>
	<ul>
	    <li>脱落者が出ない</li>
	    <li>司会者がいない</li>
	</ul>
	<p>　人狼は一時期、論理的かつ心理の読み合いという面白さから、製作者の周りでブームになったことがあります。ですが、人狼は、初日に狙われると、楽しみが半減してしまうという欠点が存在してしました。それら「排除」という観点を無くすことによって、全てのプレイヤーができるだけ長く参加できるようになっています。</p>
	<p>　このゲームのルールは、元々は<a href="http://sgrk.blog53.fc2.com/blog-entry-1957.html">レジスタンス</a>というテーブルゲームを参考に作っています。チャットもいいけど、リアルでやるのも楽しいので、是非購入しましょう。5人から遊べるし！！</p>
	<p>　また、<a href="doc/">詳細ドキュメント</a>も現在制作中です。</p>
	<h2>リンク</h2>
		<a href="http://www.segausers.gr.jp/~ceptg/sor/sow.cgi">革命物語</a>
	<p>革命物語は、人狼物語というスクリプトをベースとしたレジスタンスが遊べるサイトのようです。元のスクリプトからの開発・改造なだけあってわかりやすく遊べるところがとてもGoodです。こちらの表示が苦手な方は、こっちで遊ばれるのもいいかもしれません。</p>
	<h2>開発履歴</h2>
	<ul>
	    <li>2012/03/13 :: 公開</li>
	</ul>
    </div>
    <div id="log">
    <h2>新しい部屋を作成する</h2>
    <form action="./create_room.php" method="POST">
	<p>部屋名 : <input type="textarea" name="room_name" id="roomname"/></p>
	<p>参加者の指定 :
	<select name="people" id="roompeople">
<?php
	if ($debug) {
		echo '<option value="3">3</option>';
	}
?>
		<option value="5">5</option>
		<option value="6">6</option>
	    <option value="7">7</option>
	    <option value="8">8</option>
	    <option value="9">9</option>
	    <option value="10">10</option>
	</p>
	<input id="makeroom" type="submit" value="作成">
	</form>
    <h2>募集中の部屋</h2>
    <!-- 募集中の部屋を表示する -->
    <ul>
<?php

if (!$room_exist){
	echo "<p>ファイルは存在しません。</p>";        
} else {
	foreach ($waiting_room as $room_list) {
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
    </ul>
	<h2>勝利条件</h2>
	<p>このゲームでは、レジスタンス側とスパイ側に分かれます。レジスタンス側はミッションを三回成功させることが勝利条件です。スパイ側はミッションを三回失敗させることです。</p>
	<h2>ゲームの流れ</h2>
	<p>　ゲームの流れとして、三つにわけられます。「リーダーによるチームの選出」「チームに対する信任投票」「ミッションを成功させるか否か」、の三つです。以下の流れを最高でも5回（最低でも3回）行います。</p>
	<h3>リーダーによるチームの選出</h3>
	<p>　リーダーは、リーダーになったことのない人間から、ランダムに選択されます。リーダーは、ミッションに向かうチームを選択してください。</p>
	<h3>チームに対する信任投票</h3>
	<p>　チームの選出が決まったら、本当にそのメンバーにミッションをさせるのか、を投票します。もし、そのメンバーでよければ「信任」を押してください。しかし、スパイ側の人間が紛れ込んでいるのではないか、など、疑わしい場合は「否認」をしてください。信任が過半数の場合、そのチームメンバーにてミッションを行います。しかし、否認が同数以上の場合、チームは解散され、また選び直します。</p>
	<p>ちなみに、チームに対する信任投票は、記名性であり、どちらに投票したかの名前が出ます。もし、怪しい動きをしている人間がいたら、上手く推理に生かせるように頑張ってみてください。</p>
	<h3>ミッションを成功させるか否か</h3>
	<p>　ミッションに関しては、特別なことをする必要はありません。必要なのは「成功させるかどうか」の選択のみです。レジスタンス側の場合、成功だけの選択肢が表示されます（だってレジスタンス側には成功以外のメリットがありませんものね！市民！）。しかし、スパイ側の場合、失敗させるかどうかの選択肢が表示されます。一人が「失敗」に投票しただけで、失敗させることができます。ミッションにおいて、誰が失敗に投票したかは、わからないようになっています。</p>
	<p>　逆に言えば、ミッションを失敗させることが出来るのはスパイだけです。もしそのミッションが失敗したならば、スパイが紛れ込んでいる証拠です。推理し、できるだけ選ばないようにしましょう。</p>
	<p>　さて、ここで引っかかることがあるかもしれません。スパイはお互いにプライベートチャットのようなもので、お互いにチャットすることができません。というのは、お互いにチャットができると、簡単に戦略が立てられてしまうからです。</p>

    </div>
</body>
</html>
