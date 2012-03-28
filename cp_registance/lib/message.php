<?php
	function room_states_message($room_info){
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
	}
