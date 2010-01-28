<?php

function get_admin_list () {
	global $dbr;
	
	$result = $dbr->select(
		'admin_users',
		'au_host'
	);
	$list = Database::mysql2array($result);
	
	$list2 = array();
	
	foreach( $list as $lu ) {
		$list2[] = $lu['au_host'];
	}
	
	return $list2;
}

function get_dev_list () {
	global $dbr;
	
	$result = $dbr->select(
		'dev_users',
		'du_host'
	);
	$list = Database::mysql2array($result);
	
	$list2 = array();
	
	foreach( $list as $lu ) {
		$list2[] = $lu['du_host'];
	}
	
	return $list2;
}

function get_clerk_list () {
	global $dbr;
	
	$result = $dbr->select(
		'clerk_users',
		'cl_user'
	);
	$list = Database::mysql2array($result);
	
	$list2 = array();
	
	foreach( $list as $lu ) {
		$list2[] = $lu['cl_user'];
	}
	
	return $list2;
}

function get_cu_list () {
	global $edbr;
	
	$result = $edbr->select(
		array('user_groups','user'),
		'user_name',
		array(array('ug_group','=','checkuser')),
		array('ORDER BY' => 'user_name ASC'),
		array('ug_user' => 'user_id')
	);
	$list = Database::mysql2array($result);
	$list2=array();
	foreach($list as $cu) {
		$list2[] = $cu['user_name'];
	}
	return $list2;
}

