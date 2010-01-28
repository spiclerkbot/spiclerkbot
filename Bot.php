<?php

declare(ticks = 1);

function sig_handler($signo) {
	global $pids, $irc, $feed;

	switch ($signo) {
		case SIGCHLD:
			
			while (($x = pcntl_waitpid(0, $status, WNOHANG)) != -1) {
				if ($x == 0) break;
				$status = pcntl_wexitstatus($status);
				unset($pids[$x]);
			}
			break;
		case SIGHUP:
		case SIGTERM:
		case SIGINT:
			//print_r($pids);
			if( !in_array( posix_getpid(), $pids ) ) {//We are the parent
			
				foreach($pids as $pid) {
	 
					echo "Killing $pid\n";
					posix_kill($pid, SIGKILL);
				}

				echo "Killing ".posix_getpid()."\n";
				posix_kill(posix_getpid(), SIGKILL);

				fclose($irc);
				fclose($feed);
			}
			exit();
	}
}

pcntl_signal(SIGINT, "sig_handler");
pcntl_signal(SIGCHLD, "sig_handler");
pcntl_signal(SIGTERM, "sig_handler");
pcntl_signal(SIGHUP, "sig_handler");

$pids = array();

require_once( 'Archive.php' );
require_once( 'Approve.php' );
require_once( 'Config.php' );
require_once( 'priv/.Configdata.php' );
require_once( 'Command.php' );
require_once( 'Database.php' );
require_once( 'Functions.php' );
require_once( 'HTTP.php' );
require_once( 'IRC.php' );
require_once( 'List.php' );

$cnf = new Config;

$dbr = new Database( 
	$cnf->getConf('sqldbhost'), 
	$cnf->getConf('sqldbport'), 
	$cnf->getConf('sqldbuser'), 
	$cnf->getConf('sqldbpass'), 
	$cnf->getConf('sqldbname'), 
	true
);

$dbw = new Database( 
	$cnf->getConf('sqldbhost'), 
	$cnf->getConf('sqldbport'), 
	$cnf->getConf('sqldbuser'), 
	$cnf->getConf('sqldbpass'), 
	$cnf->getConf('sqldbname'), 
	false
);

$edbr = new Database( 
	$cnf->getConf('enwdbhost'), 
	$cnf->getConf('enwdbport'), 
	$cnf->getConf('enwdbuser'), 
	$cnf->getConf('enwdbpass'), 
	$cnf->getConf('enwdbname'), 
	true
);

$edbw = new Database( 
	$cnf->getConf('enwdbhost'), 
	$cnf->getConf('enwdbport'), 
	$cnf->getConf('enwdbuser'), 
	$cnf->getConf('enwdbpass'), 
	$cnf->getConf('enwdbname'), 
	false
);

$http = new HTTP( $cnf->getConf('baseurl') );

$irc = new IRC( 
	$cnf->getConf('ircuser'), 
	$cnf->getConf('ircnick'), 
	$cnf->getConf('ircpass'), 
	$cnf->getConf('ircserver'), 
	$cnf->getConf('ircport'), 
	$cnf->getConf('ircgecos'), 
	$cnf->getConf('ircchannel')
);

$http->login( $cnf->getConf('wikiuser'), $cnf->getConf('wikipass') );
die();

$admin_list = get_admin_list ();
$dev_list = get_dev_list();
$clerk_list = get_clerk_list();
$cu_list = get_cu_list();

$ircpid = pcntl_fork();
if ($ircpid == 0) {

	while (!feof($irc->f)) {
	
		$parsed = IRC::parseLine( fgets($irc->f,1024), $cnf->getConf('irctrigger') ); 
		
		if( $parsed['raw'] == "" ) continue;
		
		print_r($parsed);
		echo "IRC: " . $parsed['raw'] . "\n\n";
		
		if( @$parsed['type'] == 'PING' ) {
			$irc->sendPong( $parsed['payload'] );
		}
		
		if( @$parsed['type'] == '376' || @$parser['type'] == '422' ) {
			$irc->joinChan();
			sleep(5);
		}
		
		if( @$parsed['type'] == 'PRIVMSG' ) {
			if( strtolower( $parsed['chan'] ) == strtolower( $cnf->getConf('ircchannel') ) ) {
				if( isset( $parsed['command'] ) ) {
					echo "Running command {$parsed['command']}...\n\n";
					$res = runCommand( $parsed['command'], $parsed['param'], $parsed['cloak'], $parsed['nick'] );
					echo "Result: $res\n\n";
					if( $res ) {
						$irc->sendPrivmsg( $res );
					}
				}
			}
		}


	}
	posix_kill(posix_getppid(), SIGTERM);
	die();
}
else {
	$pids[$ircpid] = $ircpid;
}

sleep(10);

while(1) {

	$feed = new IRC( 
		$cnf->getConf('feeduser'), 
		$cnf->getConf('feednick'), 
		$cnf->getConf('feedpass'), 
		$cnf->getConf('feedserver'), 
		$cnf->getConf('feedport'), 
		$cnf->getConf('feedgecos'), 
		$cnf->getConf('feedchannel')
	);

	while (!feof($feed->f)) {
	
		$parsed = IRC::parseLine( fgets($feed->f,1024), $cnf->getConf('irctrigger'), true ); 
		
		//print_r($parsed);
		//echo "FEED: " . $parsed['raw'] . "\n\n";
		
		if( @$parsed['type'] == 'PING' ) {
			$feed->sendPong( $parsed['payload'] );
		}
		
		if( @$parsed['type'] == '376' || @$parser['type'] == '422' ) {
			$feed->joinChan();
			sleep(5);
		}
		
		if( @$parsed['type'] == 'PRIVMSG' ) {
			if( strtolower( $parsed['chan'] ) == strtolower( $cnf->getConf('feedchannel') ) ) {
				$result = IRC::parseRC( $parsed['message'] );
				
				//print_r($result);
				if( $result['is_delete'] && $result['actionpageprefix'] == "Wikipedia:Sockpuppet investigations") {
					echo "Looks like we have a deleted page...\n";
					$irc->sendPrivmsg("[[{$parsed['actionpage']}]] has been deleted, bot delisting.");
				}
				
				if( $result['basepagename'] == "Wikipedia:Sockpuppet investigations" ) {
					if( $result['is_new'] ) {
						if( preg_match( '/\/Archive$/', $result['pagename'] ) ) {
							$irc->sendPrivmsg($parsed['truerawmsg']);
						}
						else {
							$irc->sendPrivmsg("New case by [[User:{$result['username']}]]: [[{$result['fullpagename']}]]; {$result['url']}");
						}
					}
					else {
						$irc->sendPrivmsg($parsed['truerawmsg']);
					}
				}
			}
		}


	}
	
}


