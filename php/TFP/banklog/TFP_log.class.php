<?php

Class TFP_Log{
	/**
	  * 數據庫對像
	  *
	  **/
	public $obj;
	public $Db;

	function __construct( $obj ){
		$this->obj = $obj;
		$this->Db  = $obj->loadDb(5);
	}


	function saveBatchFileLog( $param = array() ){

	}

	function setOrderPayStatus( $param = array() ){
		if( !isset($param['OrderID']) )
			return;
	}



	function proecessBatchFile( $param = array() ){
		switch( $param['type'] ){
			case 'requestPayment':
				$this->processRequestPayment( $param );
				break;

			case 'cancelAuth':
				$this->processCancelAuth( $param );
				break;

			case 'refund':
				$this->proccessRefund( $param );
				break;
		}
	}
	/*
	 	$log = array(
			'type'       => ''
			'ResCode'    => '',
			'ReasonCode' => '',
			'Header'     => '',
			'Data'       => array()
		);
		Data =>
			return array(
				'OrderID'          => substr($str,77,40),
				'TransAmt'         => substr($str,66,11),
				'ResCode'          => substr($str,118,3),
				'RequestTime'      => substr($str,190,14),
				'RequestValidDate' => substr($str,204,8),
				'RequestResCode'   => substr($str,$212,3)
			);

	*/

	function processRequestPayment( $param = array() ){
		$status = ($param['ResCode'] == 'S0')? 1:2;
		$sql = '';
		if( $param['ResCode'] != 'R0' ){
			foreach( $param['Data'] as $row ){
				if( $row['ResCode'] == '000' && $row['RequestResCode'] == '000' ){

				}
			}
		}

		if( $param['ResCode'] == 'R0' )
			$this->recoverOrder( $param );

		$this->replyBatchLog( array(
			'id'	  => $param['id'],
			'ResCode' => $param['ResCode'],
			'status'  => $status,
			'data'    => $param['Data']
			)
		);
	}

	/*
			array(
				'OrderID'          => substr($str,77,40),
				'TransAmt'         => substr($str,66,11),
				'ResCode'          => substr($str,118,3),
				'CancelDate'       => substr($str,190,8),
				'CancelTime'       => substr($str,198,6),
			);

	*/
	function processCancelAuth( $param = array() ){
		$status = ($param['ResCode'] == 'S0')? 1:2;

		if( $param['ResCode'] != 'R0' ){
			foreach( $param['Data'] as $row ){
				if( $row['ResCode'] == '000' ){
					$time = strtotime("{$row['CancelDate']} {$row['CancelTime']}");
				}
			}
		}


		if( $param['ResCode'] == 'R0' )
			$this->recoverOrder( $param );

		$this->replyBatchLog( array(
			'id'	  => $param['id'],
			'ResCode' => $param['ResCode'],
			'status'  => $status,
			'data'    => $param['Data']
			)
		);
	}

	/*
	array(
			'OrderID'         => substr($str,77,40),
			'TransAmt'        => substr($str,66,11),
			'ResCode'         => substr($str,118,3),
			'RefundDate'      => substr($str,190,8),
			'RefundTime' 	  => substr($str,198,6),
		);
	*/

	function proccessRefund( $param = array() ){
		$status = ($param['ResCode'] == 'S0')? 1:2;

		if( $param['ResCode'] != 'R0' ){
			foreach( $param['Data'] as $row ){
				if( $row['ResCode'] == '000' ){
					$time = strtotime("{$row['RefundDate']} {$row['RefundTime']}");
				}
			}
		}


		if( $param['ResCode'] == 'R0' )
			$this->recoverOrder( $param );

		$this->replyBatchLog( array(
			'id'	  => $param['id'],
			'ResCode' => $param['ResCode'],
			'status'  => $status,
			'data'    => $param['Data']
			)
		);
	}

	function replyBatchLog( $param ){

	}

	function recoverOrder( $param = array() ){
	}
}