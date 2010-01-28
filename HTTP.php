<?php


class HTTP {
	private $ch;
	private $uid;
	public $postfollowredirs;
	public $getfollowredirs;
	private $baseurl;
	private $tokencache = array();

	/**
	 * Our constructor function.  This just does basic cURL initialization.
	 * @return void
	 **/
	function __construct ($baseurl) {
		$this->baseurl = $baseurl;
		$this->ch = curl_init();
		$this->uid = dechex(rand(0,99999999));
		curl_setopt($this->ch,CURLOPT_COOKIEJAR,'/tmp/spibot.cookies.'.$this->uid.'.dat');
		curl_setopt($this->ch,CURLOPT_COOKIEFILE,'/tmp/spibot.cookies.'.$this->uid.'.dat');
		curl_setopt($this->ch,CURLOPT_MAXCONNECTS,100);
		curl_setopt($this->ch,CURLOPT_CLOSEPOLICY,CURLCLOSEPOLICY_LEAST_RECENTLY_USED);
		$this->postfollowredirs = 0;
		$this->getfollowredirs = 1;
	}

	/**
	 * Post to a URL.
	 * @param $url The URL to post to.
	 * @param $data The post-data to post, should be an array of key => value pairs.
	 * @return Data retrieved from the POST request.
	 **/
	function post ($url,$data) {
		$time = microtime(1);
		curl_setopt($this->ch,CURLOPT_URL,$url);
		curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,$this->postfollowredirs);
		curl_setopt($this->ch,CURLOPT_MAXREDIRS,10);
		curl_setopt($this->ch,CURLOPT_HEADER,0);
		curl_setopt($this->ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($this->ch,CURLOPT_TIMEOUT,30);
		curl_setopt($this->ch,CURLOPT_CONNECTTIMEOUT,10);
		curl_setopt($this->ch,CURLOPT_POST,1);
		curl_setopt($this->ch,CURLOPT_POSTFIELDS, $data);
		curl_setopt($this->ch,CURLOPT_HTTPHEADER, array('Expect:'));
		$data = curl_exec($this->ch);
		//global $logfd; if (!is_resource($logfd)) $logfd = fopen('php://stderr','w'); fwrite($logfd,'POST: '.$url.' ('.(microtime(1) - $time).' s) ('.strlen($data)." b)\n");
		echo 'POST: '.$url.' ('.(microtime(1) - $time).' s) ('.strlen($data)." b)\n";
		return $data;
	}

	/**
	 * Get a URL.
	 * @param $url The URL to get.
	 * @return Data retrieved from the GET request.
	 **/
	function get ($url) {
		$time = microtime(1);
		curl_setopt($this->ch,CURLOPT_URL,$url);
		curl_setopt($this->ch,CURLOPT_FOLLOWLOCATION,$this->getfollowredirs);
		curl_setopt($this->ch,CURLOPT_MAXREDIRS,10);
		curl_setopt($this->ch,CURLOPT_HEADER,0);
		curl_setopt($this->ch,CURLOPT_RETURNTRANSFER,1);
		curl_setopt($this->ch,CURLOPT_TIMEOUT,30);
		curl_setopt($this->ch,CURLOPT_CONNECTTIMEOUT,10);
		curl_setopt($this->ch,CURLOPT_HTTPGET,1);
		$data = curl_exec($this->ch);
		echo 'GET: '.$url.' ('.(microtime(1) - $time).' s) ('.strlen($data)." b)\n";
		return $data;
	}

	function getpage( $title, $bool = false ) {
		$url = $this->basurl . "api.php?action=query&prop=revisions&titles=".urlencode($title)."&rvprop=content&limit=1&format=php";
		$x = unserialize($this->get( $url ));
		if( !is_array( $x['query']['pages'] ) ) { die("ERROR WHEN GETTING $title!!!"); }

		foreach( $x['query']['pages'] as $key => $page ) {
			if( $key == "-1" ) return false;
			if( $bool = true ) return true;
			return $page['revisions']['0']['*'];
		}
	}

	function getedittoken () {
		$tokens = $this->gettokens('Main Page');
		if ($tokens['edittoken'] == '') $tokens = $this->gettokens('Main Page',true);
		$this->edittoken = $tokens['edittoken'];
		return $tokens['edittoken'];
	}

	/**
	 * This function returns the various tokens for a certain page.
	 * @param $title Page to get the tokens for.
	 * @param $flush Optional - internal use only.  Flushes the token cache.
	 * @return An associative array of tokens for the page.
	 **/
	function gettokens ($title,$flush = false) {
		if (!is_array($this->tokencache)) $this->tokencache = array();
		foreach ($this->tokencache as $t => $data) if (time() - $data['timestamp'] > 6*60*60) unset($this->tokencache[$t]);
		if (isset($this->tokencache[$title]) && (!$flush)) {
			return $this->tokencache[$title]['tokens'];
		} else {
			$tokens = array();
			$x = $this->get($this->baseurl.'api.php?action=query&format=php&prop=info&intoken=edit|delete|protect|move|block|unblock|email&titles='.urlencode($title));
			$x = unserialize($x);
			foreach ($x['query']['pages'] as $y) {
				$tokens['edittoken'] = $y['edittoken'];
				$tokens['deletetoken'] = $y['deletetoken'];
				$tokens['protecttoken'] = $y['protecttoken'];
				$tokens['movetoken'] = $y['movetoken'];
				$tokens['blocktoken'] = $y['blocktoken'];
				$tokens['unblocktoken'] = $y['unblocktoken'];
				$tokens['emailtoken'] = $y['emailtoken'];
				$this->tokencache[$title] = array(
						'timestamp' => time(),
						'tokens' => $tokens
								 );
				return $tokens;
			}
		}
	}

	function edit ($page,$data,$summary = '',$minor = false,$bot = true) {

		$params = Array(
			'action' => 'edit',
			'format' => 'php',
			'title' => $page,
			'text' => $data,
			'token' => $this->getedittoken(),
			'summary' => $summary,
			($minor?'minor':'notminor') => '1',
			($bot?'bot':'notbot') => '1'
		);

		$x = $this->post($this->baseurl."api.php",$params);
		$x = unserialize($x);

		if ($x['edit']['result'] == 'Success') return true;
		else return false;
	}
	
	function login( $user, $pass ) {
		$res = unserialize($this->post(
			$this->baseurl.'api.php',
			array(
				'action'=>'login',
				'lgname' => $user, 
				'lgpassword' => $pass,
				'format' => 'php'
			)
		));
		
		if( $res['login']['result'] == "Success" ) { 
			echo "Logged in!\n\n";
		}
		else {
			echo "ERROR! {$res['login']['result']}\n\n";
			die();
		}
	}

	/**
	 * Our destructor.  Cleans up cURL and unlinks temporary files.
	 **/
	function __destruct () {
		curl_close($this->ch);
		@unlink('/tmp/cluewikibot.cookies.'.$this->uid.'.dat');
	}
}
