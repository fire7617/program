<?php

use Minishlink\WebPush\WebPush;

Class GCM{

	private $auth               =  null;
	public  $notifications      =  null;
	private $iconUrl            = '';
	private $url                = '';
	private $title              = '';
	private $requireInteraction = '1';
	function __construct(){
		$this->auth = array(
			'GCM' => '',
			'VAPID' => array(
				'subject' =>'',
				'publicKey' => '',
				'privateKey' => ''
			));
	}


	function set_notification( $notification ){
		if( !isset($notification['userPublicKey']['0']) ||
			!isset($notification['userAuthToken']['0']) ||
			!isset($notification['body']['0'])          ||
			!isset($notification['endpoint']['0']) )
			return false;

		$temp = array();
		$temp['title']               = isset($notification['title'])??$this->title;
		$temp['requireInteraction']  = isset($notification['requireInteraction'])?? $this->requireInteraction;
		$temp['icon']                = isset($notification['icon'])??$this->iconUrl;
		$temp['url']                 = isset($notification['url'])?? $this->url;
		$temp['body']                = $notification['body'];

		$this->notification["endpoint"]             = $notification['endpoint'];
		$this->notification["userPublicKey"]        = $notification['userPublicKey'];
		$this->notification["userAuthToken"]        = $notification['userAuthToken'];
		$this->notification['payload']              = json_encode( $temp );
		return true;

	}
	function send(){
		$webPush = new WebPush($this->auth);

		if( $this->notification == null )
			return false;

		return $webPush->sendNotification(
					$this->notification['endpoint'],
					$this->notification['payload'],
					$this->notification['userPublicKey'],
					$this->notification['userAuthToken'],
					true
				);

	}
}
