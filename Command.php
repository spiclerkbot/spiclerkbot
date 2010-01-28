<?php

function runCommand( $command, $msg, $nuh, $nick ) {
	if( function_exists( 'doCommand' . ucfirst($command) ) ) {
		$res = call_user_func( 'doCommand' . ucfirst($command), $msg, $nuh, $nick );
		if( $res != false ) { return $res; }
	}
}

function doCommandTest( $msg, $nuh, $nick ) {
	echo "Hello, $nick!";
	return "Hello, $nick!";
}

function doCommandAddadminuser( $msg, $nuh, $nick ) {
	global $dev_list, $admin_list, $dbr, $dbw;
	
	if( !in_array( $nuh, $dev_list ) ) {
		return "You do not have the rights to operate this command. Please see one of the developers.";
	}
	return;
	
	$m = explode(" ",$msg);
	$nick = $m[0];
	$host = $m[1];
	
	$res = $dbr->insert(
		'admin_users',
		array(
			'au_nick' => $nick,
			'au_host' => $host,
		)
	);
	
	if( !$res ) return "Error in MySQL, please debug.";
	else return "Done!";
}

