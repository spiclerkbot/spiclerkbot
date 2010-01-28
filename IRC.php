<?php

class IRC {
	
	public $f;
	private $chan;

	function __construct ( $User, $Nick, $Pass, $Server, $Port, $Gecos, $Channel ) {
		$this->f = fsockopen( $Server, $Port, $errno, $errstr, 30 );

		if( !$this->f ) { die( $errstr . ' (' . $errno . ")\n" ); }

		$this->sendToIrc( 'NICK ' . $Nick . "\n" );
		$this->sendToIrc( 'USER ' . $User . ' "' . $Server . '" "localhost" :' . $Gecos . "\n" );
		$this->sendToIrc( 'PASS ' . $Pass . "\n" ); 

		$this->chan = $Channel;
	}
	
	public function sendToIrc( $msg ) {
		fwrite( $this->f, $msg );
	}

	public function sendPrivmsg( $msg ) {
		fwrite( $this->f, "PRIVMSG " . $this->chan . " :$msg\n" );
	}
	
	public function sendPong( $payload ) {
		fwrite( $this->f, "PONG " . $payload );
	}
	
	public function joinChan() {
		fwrite( $this->f, 'JOIN ' . $this->chan . "\n" );
	}

	public static function parseLine( $line, $trigger, $feed = false ) {
		$return = array();
		$return['trueraw'] = $line;
		$return['truerawmsg'] = explode(" ",$line);
		unset( $return['truerawmsg'][0], $return['truerawmsg'][1], $return['truerawmsg'][2] );
		$return['truerawmsg'] = substr( implode( ' ', $return['truerawmsg'] ), 1 );
		
		if( $feed ) {
			$line = str_replace(array("\n","\r","\002"),'',$line);
			$line = preg_replace('/\003(\d\d?(,\d\d?)?)?/','',$line);
		}
		else {
			$line =  str_replace(array("\n","\r"),'',$line);
			$line = preg_replace('/'.chr(3).'.{2,}/i','',$line); 
		}
		
		$return['raw'] = $line;
		
		/*
			Data for a privmsg:
			$d[0] = Nick!User@Host format.
			$d[1] = Action, e.g. "PRIVMSG", "MODE", etc. If it's a message from the server, it's the numerial code
			$d[2] = The channel somethign was spoken in
			$d[3] = The text that was spoken
		*/
		$d = $return['message'] = explode( ' ', $line );
		$return['n!u@h'] = $d[0];
 
		unset( $return['message'][0], $return['message'][1], $return['message'][2] );
		$return['message'] = substr( implode( ' ', $return['message'] ), 1 );
 
		$return['nick'] = substr( $d[0], 1 );
		$return['nick'] = explode( '!', $return['nick'] );
		$return['nick'] = $return['nick'][0];
 
		$return['cloak'] = explode( '@', $d[0] );
		$return['cloak'] = @$return['cloak'][1];
		
		$return['user'] = explode( '!', $d[0] );
		$return['user'] = explode( '@', $return['user'][1] );
		$return['user'] = $return['user'][0];
 
		$return['chan'] = strtolower( $d[2] );
		
		$return['type'] = $return['payload'] = $d[1];
		 
		if ( substr( $return['message'], 0, 1 ) == $trigger ) {
			$return['command'] = explode( ' ', substr( strtolower( $return['message'] ), 1) );
			$return['command'] = $return['command'][0];
 
			//Get the parameters
			$return['param'] = explode( ' ', $return['message'] );
			unset( $return['param'][0] );
			$return['param'] = implode( ' ', $return['param'] );
			$return['param'] = trim( $return['param'] );		
		}
		
		/*
			End result: 
			$return['raw'] = Raw data
			$return['message'] = The text that appears in the channel
			$return['n!u@h'] = The person who said the line, in N!U@H format
			$return['nick'] = The nick who said the line
			$return['cloak'] = The cloak of the person who said the line
			$return['user'] = The username who said the line
			$return['chan'] = The channel the line was said in
			$return['type'] = The action that was done (eg PRIVMSG, MODE)
			$return['payload'] = For pings, this is $d[1]
			$return['command'] = The command that was said, eg !status (excuding !)
			$return['param'] = Parameters of the command
		*/
		return $return;
	}
	
	public static function parseRC( $msg ) {
		if (preg_match('/^\[\[((Talk|User|Wikipedia|Image|MediaWiki|Template|Help|Category|Portal|Special)(( |_)talk)?:)?([^\x5d]*)\]\] (\S*) (http:\/\/en\.wikipedia\.org\/w\/index\.php\?(oldid|diff)=(\d*)&(rcid|oldid)=(\d*).*|http:\/\/en\.wikipedia\.org\/wiki\/\S+)? \* ([^*]*) \* (\(([^)]*)\))? (.*)$/S',$msg,$m)) {

			$return = array();
			
			//print_r($m);
			
			$return['namespace'] = $m[2];
			$return['pagename'] = $m[5];
			$return['fullpagename'] = $m[1].$m[5];
			$return['basepagename'] = explode('/', $return['fullpagename']);
			$return['basepagename'] = $return['basepagename'][0];
			$return['flags'] = str_split($m[6]);
			$return['action'] = $m[6];
			$return['url'] = $m[7];
			$return['revid'] = $m[9];
			$return['oldid'] = $m[11];
			$return['username'] = $m[12];
			$return['len'] = $m[14];
			$return['comment'] = $m[15];
			$return['timestamp'] = time( 'u' );
			$return['is_new'] = false;
			$return['is_minor'] = false;
			$return['is_bot'] = false;
			$return['is_delete'] = false;
			$return['actionpage'] = null;
			
			if( in_array( 'N', $return['flags'] ) ) {
				$return['is_new'] = true;
			}
			
			if( in_array( 'M', $return['flags'] ) ) {
				$return['is_minor'] = true;
			}
			
			if( in_array( 'B', $return['flags'] ) ) {
				$return['is_bot'] = true;
			}
			
			if( $return['action'] == 'delete' ) {
				$return['is_delete'] = true;
				$tmp = explode('[[', $return['comment']);
				$tmp = explode(']]', $tmp[1]);
				$return['actionpage'] = $tmp[0];
				$return['actionpageprefix'] = explode('/',$return['actionpage']);
				$return['actionpageprefix'] = $return['actionpageprefix'][0];
			}
			
			return $return;
		}
	}
}
