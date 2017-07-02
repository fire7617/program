<?php

CLASS Bank_Api
{

	/**
	  * 數據庫對像
	  *
	  **/
	public $obj;
	public $Db;

	public $bankObj = null;//銀行物件
	public $bankLogObj = null; //銀行Log物件

	public $bankResponseSuccessCode = '';
	public $bankResponseKey = '';

	public $errorCode = '';
	public $batchFile = false;


	function __construct( $obj ,$libName ){
		$this->obj = $obj;
		$this->Db  = $obj->loadDb(5);

		if(  $libName == '' || !file_exists(SYSTEM_ROOT."/lib/bank/".$libName."bank.class.php") ){
			exit("There is no bank class !!!<br/>");
		}

		include_once(SYSTEM_ROOT."/lib/bank/".$libName."bank.class.php");
		$this->bankObj                 = new $libName();
		$temp                          = $this->bankObj->getResponseKeyData();
		$this->bankResponseKey         = $temp['key'];
		$this->bankResponseSuccessCode = $temp['success_code'];

		if( file_exists(SYSTEM_ROOT."/lib/banklog/".$libName."_log.class.php") ){
			include_once(SYSTEM_ROOT."/lib/banklog/".$libName."_log.class.php");
			$bankLogName= "{$libName}_log";
			$this->bankLogObj = new $bankLogName( $this->obj );
		}
	}

	function checkResponse( $response ){
		if( isset($response['MessageType']['0']) && $response['MessageType'] == 'V3D_REDIRECT' )
			return true;

		$this->errorCode = $response[ $this->bankResponseKey ];
		if( $response != false
			&& is_array( $response )
			&& $this->bankResponseSuccessCode == $response[ $this->bankResponseKey ] ){
			return true;
		}

		return false;
	}


	function getAuth( $param = array() ){
		$response = $this->bankObj->getAuth( $param );

		$param['Pan'] = '';
		$param['CVV2'] = '';
		$param['ExpireDate'] = '';

		$this->saveInfo( 'auth', $param, $this->bankObj->response );
		return ( $this->checkResponse( $response ) )? $response: false;
	}

	function requestPayment( $param = array() ){
		$response = $this->bankObj->requestPayment( $param );
		$this->saveInfo( 'requestPayment', $param, $this->bankObj->response );

		$status =  ($this->bankObj->getBankId() == '012')?4:3;
		if( $response == true )
				$this->bankLogObj->setOrderPayStatus( array('OrderID' => $param['0']['OrderID'], 'status' => $status) );

		if( $this->bankObj->getBankId() == '012' ){//富邦
			$data = array(
				'bank_id' => $this->bankObj->getBankId(),
				'type'    => 'requestPayment',
				'code'	  => sprintf("%04d",$param['0']['seq']),
				'data'    => json_encode($param),
				'status'  => '0'
			);

			$this->bankLogObj->saveBatchFileLog( $data );
		}
	}

	function cancelAuth( $param = array() ){
		$response = $this->bankObj->cancelAuth( $param );
		if( $response == true )
			foreach($param as $row)
				$this->bankLogObj->setOrderPayStatus( array('OrderID' => $row['OrderID'], 'status' => '2') );

		$this->saveInfo( 'cancelAuth', $param, $this->bankObj->response );
		if( $this->bankObj->getBankId() == '012'){//富邦
			$data = array(
				'bank_id' => $this->bankObj->getBankId(),
				'type'    => 'cancelAuth',
				'code'	  => sprintf("%04d",$param['0']['seq']),
				'data'    => json_encode($param),
				'status'  => '0',
			);

			$this->bankLogObj->saveBatchFileLog( $data );
		}
	}

	function refund( $param = array() ){
		$response = $this->bankObj->refund( $param );
		if( $response == true )
			foreach($param as $row)
				$this->bankLogObj->setOrderPayStatus( array('OrderID' => $row['OrderID'], 'status' => '2') );

		//log
		$this->saveInfo( 'refund', $param, $this->bankObj->response );
		if( $this->bankObj->getBankId() == '012'){//富邦
			$data = array(
				'bank_id' => $this->bankObj->getBankId(),
				'type'    => 'refund',
				'code'	  => sprintf("%04d",$param['0']['seq']),
				'data'    => json_encode($param),
				'status'  => '0',
			);

			$this->bankLogObj->saveBatchFileLog( $data );
		}
	}

	function execute3D( $param ){
		$rs = $this->bankObj->execute3D( $param );
		$this->saveInfo( '3Dauth', $param, $this->bankObj->response );
		if( $rs != false && $this->checkResponse( $rs ) )
			return $rs;

		return false;
	}


	function processBatchFile( $param ){
		if( isset($param['bank_id']['0']) && $param['bank_id'] == '012' ){
			if( !($zipRs = $this->bankObj->fetchZipFile( $param )))
				return false;

			if( !($unzipRs = $this->bankObj->unzipFile( $param )) )
				return false;

			$data = $this->bankObj->readBatchFile( $param );
			if( $data === false )
				return false;

			$data['id']   = $param['id'];
			$data['type'] = $param['type'];
			$this->bankLogObj->proecessBatchFile( $data );
		}
	}


	function saveInfo( $type, $param, $res ){
		$bankId = $this->bankObj->getBankId();
		$error_code = isset($res[ $this->bankResponseKey ])?$res[ $this->bankResponseKey ]:'999';

		$jrs = json_encode($res);
		$jrs = ($jrs == false)? '':$jrs;
	}
}