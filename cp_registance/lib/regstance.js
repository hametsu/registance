var reflesh_time = new Date/1e3|0 ;

function reflesh_post() {

	$.getJSON('./ajaxpush.php?file=" . $room_info['file'] ."&time=' + reflesh_time,
	function(resent_log){
		if(resent_log.length !== 0){
			for (var i = 0,max = resent_log.length;i < max; i++){
				switch(){
				
				}
			}
		}
	});
	
}


