<?php

interface iBank{
	/*取得交易授權*/
	public function getAuth( $param = array() );
	/*取消交易授權*/
	public function cancelAuth( $param = array() );
	/*請款*/
	public function requestPayment( $param = array() );
	/*取消請款*/
	public function cancelPayment( $param = array() );
	/*退貨*/
	public function refund( $param = array() );
	/*取消退貨*/
	public function cancelRefund( $param = array() );
	/*訂單狀態查詢*/
	public function getInfo( $param = array() );
	/* 錯誤代碼轉換*/
	public function getErrorText( $errorCode ):string;
	/*取得銀行代碼*/
	public function getBankId():string;
}
