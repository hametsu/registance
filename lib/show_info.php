<?php
require_once("eseUtil.php");
require_once("userclass.php");
require_once("singletonclass.php");

class ShowInfo extends Singleton {

	public $RoomInfo;
	public $Session;
	private $Debug;
	private $Success_Number;
	private $Failure_Number;

	public function initialization($roominfo,$_SESSION,$debug){
		$this->RoomInfo = $roominfo;
		$this->Session  = $_SESSION;
		$this->Debug    = $debug;
	
		$result = $this->RoomInfo->get_victory_point_count_array();
		$this->Success_Number = $result['registance'];
		$this->Failure_Number = $result['spy'];
	}

	public function get_success_number(){
		return $this->$Success_number;
	}

	public function get_failure_number(){
		return $this->$Failure_Number;
	}

	public function get_session_name() {
		return 'name' . $this->RoomInfo->get_filename();
	}

	public function is_set_name() {
		return (isset($this->Session[$this->get_session_name()])); 
	}

	private function count_user_in_room() {
		return count($this->RoomInfo->get_users());
	}

	public function can_playing_member() {
		return ( ($this->Debug 
				   and
				  $this->count_user_in_room() >= 3)
			     or 
			     ($this->count_user_in_room() >= 5
				   and
				  $this->count_user_in_room() <= 10));
	}

	public function is_your_connection() {
		return $this->RoomInfo->is_user($this->Session[$this->get_session_name()]);
	}

	public function is_leader_who_browse_now() {
		return $this->RoomInfo->is_leader($this->Session[$this->get_session_name()]);
	}

	public function show_vote_start_button() {
	if($this->RoomInfo->get_states() === "waiting"){
	if ($this->is_set_name() and $this->can_playing_member()){
		if (!$this->RoomInfo->is_vote_start($this->Session[$this->get_session_name()])) {
			echo "<p class='message'>参加者が集まりましたのでプレイ可能です。</p>"; 
		   if ($this->is_your_connection()) {
		echo "<form action='./show.php?file=" . $this->RoomInfo->cgi_file. "' method='POST'>
			  <input type='hidden' name='command' value='vote_start' />
			  <input type='submit' name='button_vote' value='開始準備が出来たらボタンを押してください' />
			  </form>
			  ";
		   }
		} else {
		echo "<p class='message'>既に貴方は開始準備の合図をしました。他の人たちが準備できるまで、暫くお待ちください。</p>";
		}
		}	
	}
	}

	public function show_say_form() {
	if(!$this->is_your_connection()){
		echo "
			<form action='./show.php?file=" . $this->RoomInfo->cgi_file . "' method='POST'>
			名前：<input type='textarea' name='name'/><br />
			簡易パスワード:<input type='textarea' name='pass' />
			<select name='want_spy'>
				<option value='not'>どちらでもいい</option>
				<option value='want'>スパイを希望する</option>
				</select>
			<input type='hidden' name='comd' value='login' />
			<input type='submit' value='参加する' />
			</form>
			<p class='caption'>簡易パスワードは再ログインの為だけに使います。重要なパスワードを入力しないでください。</p>
			";
	if($this->RoomInfo->get_states() === "processing"){

		echo "<h2>既にゲームが開始しています。</h2>";

	}
	echo "<p>観戦者コメントを残すことができます。</p>";
	echo "<form action='./show.php?file=" . $this->RoomInfo->cgi_file . "' method='POST'>
		名前：<input type='textarea' name='name' value='". $this->Session["show" . $this->RoomInfo->get_filename()] .
		"' /><br /><textarea name='show_say' style='width:95%' rows='2' id='say'></textarea>
		 <input type='hidden' name='comd' value='show_say' />
		 <input type='submit' value='発言する(CTRL+ENTER)' id='submit_say'>
		 </form>
		";  
} else {
	echo "<span class='warning' style='display:none;' id='say_warning'>文字数が多すぎます！</span>
		<form action='./show.php?file=" . $this->RoomInfo->cgi_file . "' method='POST' name='say'>
		";
		//<span style='color:#AAA;' id='counttext'>0</span>
	echo "<span class='name'>";
	if ($this->RoomInfo->is_room_anonymous() === "false") {
		$show_name_string = $this->Session[$this->get_session_name()];
	} else {
		$show_name_string = $this->RoomInfo->get_username_to_anonymous($this->Session[$this->get_session_name()]);
		$show_name_string .= "(" . $this->Session[$this->get_session_name()] . ")";
	}
	echo $show_name_string;
	echo "</span><br /> <textarea name='say'style='width:95%' rows='2' id='say' /></textarea>";
	if ($this->RoomInfo->is_room_anonymous() !== "false"
		and $this->RoomInfo->get_states() === "waiting") {
			$color_list = array("black");
			echo "<p class='caption'>ゲームが開始したら、色が選べるようになります。</p>";
		} else {
			$color_list = array("black","maroon","purple","green","olive","navy","teal","gray","fuchsia","orangered");
		}
		foreach ($color_list as $color_item){
		echo "<input type='radio' name='color' value='$color_item'";
		if($this->Session["color" . $this->RoomInfo->cgi_file ] === $color_item){
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
	echo "<input type='submit' value='発言する(CTRL+ENTER)' id='submit_say'/>
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
	}

	public function show_history_list() {
	if($this->RoomInfo->get_mission_no() > 1) {
	$counter = 1;
	foreach($this->RoomInfo->get_victory_history() as $history_item){
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
	}

	public function show_vote_and_mission() {
	if($this->is_your_connection() ){
	switch($this->RoomInfo->get_scene()){
	case "vote":
		if($this->RoomInfo->get_user_vote($this->Session[$this->get_session_name()]) !== FALSE
		&& $this->RoomInfo->get_user_vote($this->Session[$this->get_session_name()]) !== NULL){
			echo "<p class='message'>あなたは既に「";
			echo $this->RoomInfo->get_user_vote($this->Session[$this->get_session_name()]) === "trust" ? "信任" : "不信任";
			echo "」へ投票しています。<br /><br />投票を変更したい場合は、もう一度投票を行って下さい。</p>";
		}
		echo    "<p>(現在、" . $this->RoomInfo->count_vote() . "人が投票しています)</p>";
		echo 	"<div class='vote'>
				<form action='./show.php?file=" . $this->RoomInfo->cgi_file
				. "' method='POST'>
				<input type='hidden' name='command' value='vote' />
				<input type='radio' name='vote' value='trust'/>信任
				<input type='radio' name='vote' value='veto' />不信任
				<br /><br />
				<div align='right'>
					<input type='submit' name='button_vote'value='投票' />
				</div>	
				</form>
				</div>
				";
		break;
	case "mission":
		if($this->RoomInfo->is_team_member($this->Session[$this->get_session_name()])){
			if($this->RoomInfo->get_user_mission($this->Session[$this->get_session_name()])){
				echo "<p class='message'>あなたは既にミッションを遂行しました。</p>";
			} else {
				echo "
					<div class='vote'>
					<form action='./show.php?file=". $this->RoomInfo->cgi_file . " ' method='POST'>
					<input type='hidden' name='command' value='mission'/>
					<input type='radio' name='vote' value='success'/> 成功
					";
				if ($this->RoomInfo->is_spy($this->Session[$this->get_session_name()])){                        
					echo "
						<input type='radio' name='vote' value='failure' /> 失敗
						";
				}
				echo "<input type='submit' name='button_vote'value='遂行' />
					</form>
					</div>
					";
			}

		} else {
			echo "<p>現在、チームによってミッションを遂行しています。</p>";
		}
	}
}
	}

	public function show_processing_info() {
	
	if ($this->RoomInfo->get_states() === "processing") {
	echo '<div class="wrap_float">';
	echo '<ul><li><span class="system_info">信任投票回数</span></li>
		  <li>' . $this->RoomInfo->get_failure_team_no() . "回目</li></ul></div>";
	echo '<div class="wrap_float">
	<ul>
	<li><span class="system_info">今回のスパイ</span></li>
	<li style="border:none;">
	';
	echo $this->RoomInfo->count_spy();
	
	echo '人
		</li>
		</ul>
	</div>
	<div class="wrap_oneline">
	<div style="margin-bottom:5px;">
	<span class="system_info" style="height:100%">選ばれる人数</span>';
	if (count($this->RoomInfo->get_users()) > 6) {
		echo "<span style='font-size:8px;color:#666;border-bottom:1px solid #666'>
			  「※」は、スパイ側が2つ以上の「失敗」を必要とすることを意味します。</span>
	";
	}
	echo '<br /></div>
	<ul id="mission_count">';
	$counter = 0;
	foreach($this->RoomInfo->get_need_team_array() as $team_number){
		$counter ++;
		if ($this->RoomInfo->get_mission_no() === $counter) {
			echo "<li id='now_mission'>";
		} else {
			if ($this->RoomInfo->get_mission_no() < $counter) {
				echo "<li>";
			} else {
				echo "<li style='color:#AAA;'>";
			}
		}
		echo "M$counter :: $team_number 人"; 
			if (count($this->RoomInfo->get_users()) > 6
				&& $counter === 4) {
				echo "※";
				}
			"</li>";
	}
	echo "</ul>";
	echo "</div>";
	}
	
	}

	public function latest_system_time() {

		$room_data = $this->RoomInfo->get_raw_roomdata();
		if(!isset($room_data[16])) {
			return 0;
		} else {
			$room_log = array_splice($room_data,16);
			foreach($room_log as $log_line) {
				$log_array = explode(",",$log_line);
				if ($log_array[1] === 'warning') {
					return $log_array[4];
				}
			}
		}
			return 0;
	}

	public function system_limit_time() {
		$latest_time = $this->latest_system_time();
		$limit_time = 900;
		$return_val = $limit_time - (time() - $latest_time);
		return $return_val <= 0 ? 0 : $return_val;
	}

}
