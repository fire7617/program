<?php
include_once(__DIR__."/transMethods.class.php");

Class Line{
	public $url = 'https://api.line.me/v2/bot/message/push';//line bot

	//101名品會

	private $token = '',$to = '';

	/**
	 * @param array $data
	 */
	function __construct( $token, $to ){
		$this->token = $token;
		$this->to    = $to;
	}

	function sendMsg( $msg = '' ){
		if( !isset($msg['0']) )
			return false;


        $header = array("Content-Type: application/json; charser=UTF-8", "Authorization:Bearer " . $this->token);

        $msg = json_encode(
			array(
				'to'	   => $this->to,
				"messages" => array(array("type" => "text", "text" => $msg))
			)
		);

		return TransMethods::sendByCurl( $this->url, $msg, 5, $header );
	}
}