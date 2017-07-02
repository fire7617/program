<?php


//富邦銀行
include_once(SYSTEM_ROOT."/lib/interface/bank.interface.php");
include_once(SYSTEM_ROOT."/lib/transMethods.class.php");
include_once(SYSTEM_ROOT."/lib/file.class.php");
require_once(SYSTEM_ROOT."/lib/bank/STAuth.phar");

Class TFP  implements iBank {
	public $bankId = '';
	public $bankObj;

	private $ftpSite        = '';
	private $uploadUsername = '';
	private $uploadPassword = '';

	private $downloadUsername= '';
	private $downloadPassword= '';


	private $MerchantID     = ''; //特約商店代碼  //正式機

	private $SubMID         = '';
	private $TerminalID     = '';//正式機

	private $AcquirerID     = '';//預設收單行代碼
	private $CurrencyCode   = '901';//貨幣種類 901=>台幣
	private $auth3DPage     = '';
	private $mauth3DPage 	= '';

	private $NotifyType     = "API";

	public $successCode     = '000';
	public $responseCodeKey = 'ResCode';
	private $lineBreak      = "\r\n";
	private $dataPath       = '';
	public $response        = null;

	public function __construct(){
		$this->bankObj = new SSLServer();
	}

	public function getBankId() :string{
		return $this->bankId;
	}

	public function getResponseKeyData(){
		return array('key' => $this->responseCodeKey, 'success_code' => $this->successCode );
	}

	/*取得交易授權*/
	public function getAuth( $param = array() ){
		if( !$this->checkParam('OrderID,PAN,CVV2,TransDate,TransTime,TransAmt,InstallCnt,TransMode', $param) )
			return false;

		$data = $param;
		$data['TransCode']   = '00';
		$data['NotifyURL']   = ( isset($param['Driver']['0']) && $param['Driver'] == 'mobile' )?$this->mauth3DPage:$this->auth3DPage;
		$data['NotifyType']  = $this->NotifyType;
		$data['NotifyParam'] = "OrderID={$param['OrderID']},TransAmt={$param['TransAmt']},InstallCnt={$param['InstallCnt']}";

		return $this->execute( $data );
	}


	/*取消交易授權*/
	public function cancelAuth( $params = array() ){
		if( count($params) == 0 )
			return false;

		$str = '';
		$count = 0;
		$totalMoney = 0;
		$seq = '';
		foreach( $params as $param ){
			$count ++;
			$param['TerminalID'] = $this->TerminalID;
			$this->ajustDefaultParam( $param );
			$totalMoney += $param['TransAmt'];
			$temp       = "D{$param['TerminalID']}{$param['OrderID']}{$param['TransDate']}{$param['TransTime']}{$param['ApproveCode']}{$param['TransAmt']}";
			$blank      = $this->fillChar(225);
			$str        .= $temp.$blank. $this->lineBreak;
			$seq        = $param['seq'];
		}
		$seq = sprintf("%04d",$seq);
		$str      =  $this->getFileHeader( "BV" , $seq ).$str.$this->getTailerStr($count, 0,0, abs($totalMoney), 0,0);
		$time     =  date("Ymd");
		$fileName = "BV{$this->MerchantID}_{$time}{$seq}";
		if( !$this->writeFile($str,$fileName.'.dat') )
			return false;

		if( !$this->zipFile( $fileName ) )
			return false;

		if( !TransMethods::sendByFTP($this->dataPath.$fileName.'.zip',$this->ftpSite,$this->uploadUsername,$this->uploadPassword,$fileName.'.zip') )
			return false;


		$this->response = array(
			'OrderID'       => '',
			'Seq'           => $seq,
			'FileName'      => $fileName,
			'ReplyFileName' => $this->getReplyFileMark('cancelAuth')."{$this->MerchantID}_{$time}{$seq}.dat"
		);


		return true;
	}

	function ajustDefaultParam( &$param = array() ){
		if( isset($param['OrderID']) ){
			$len = strlen($param['OrderID']);
			if( $len < 25 )
				$param['OrderID'] = $param['OrderID'].$this->fillChar( 25 - $len );
		}


		if( isset($param['TransAmt']) ){
			$len = strlen($param['TransAmt']);
			if( $len < 11 )
				$param['TransAmt'] = sprintf("%011d",$param['TransAmt']);
		}

		if( isset($param['ApproveCode']) ){
			$len = strlen($param['ApproveCode']);
			if( $len < 8 )
				$param['ApproveCode'] = $param['ApproveCode'].$this->fillChar( 8 - $len );
		}


		if( isset($param['TerminalID']) ){
			$len = strlen($param['TerminalID']);
			if( $len < 16 )
				$param['TerminalID'] = $param['TerminalID'].$this->fillChar( 16 -$len );
		}
	}

	/*請款-批次*/
	public function requestPayment( $params = array() ){
		if( count($params) == 0 )
			return false;

		$str = '';
		$code = '00';
		$count = 0;
		$totalMoney = 0;
		$seq = '';

		foreach( $params as $param ){
			$param['TerminalID'] = $this->TerminalID;
			$this->ajustDefaultParam( $param );
			$count ++;
			$totalMoney += $param['TransAmt'];
			$temp = "D{$param['TerminalID']}{$param['OrderID']}{$code}{$param['TransDate']}{$param['TransTime']}{$param['ApproveCode']}{$param['TransAmt']}";
			$blank = $this->fillChar(223);
			$seq = $param['seq'];

			$str .= $temp.$blank. $this->lineBreak;
		}


		$seq = sprintf("%04d",$seq);
		$str =  $this->getFileHeader( "BM", $seq ).$str.$this->getTailerStr($count,$count,0, abs($totalMoney),abs($totalMoney),0);
		$time = date("Ymd");

		$fileName = "BM{$this->MerchantID}_{$time}{$seq}";
		if( !$this->writeFile($str,$fileName.'.dat') )
			return false;

		if( !$this->zipFile($fileName) )
			return false;

		if( !TransMethods::sendByFTP($this->dataPath.$fileName.'.zip',$this->ftpSite,$this->uploadUsername,$this->uploadPassword,$fileName.'.zip') )
			return false;

		return true;
	}

	public function getTailerStr($count,$authCount,$refundCount,$totalMoney,$authTotalMoney,$refundTotalMoney){
		return "T".sprintf("%08d",$count).sprintf("%08d",$authCount).sprintf("%08d",$refundCount).sprintf("%013d",$totalMoney).sprintf("%013d",$authTotalMoney).sprintf("%013d",$refundTotalMoney).$this->fillChar(42,'0').$this->fillChar(194).$this->lineBreak;
	}


	private function getFileHeader( $mark, $seq ){
		$time = date("Ymd");

		$MerchantID = $this->MerchantID;
		$len = strlen($MerchantID);
		if( $len < 16 )
			$MerchantID = $MerchantID.$this->fillChar( 16 - $len);

		$str = "H{$mark}{$MerchantID}{$time}{$seq}";
		$blank = $this->fillChar(269);

		return $str.$blank.$this->lineBreak;
	}


	function fillChar($num = 0, $char = ' '): string{
		$blank = '';
		for($i = 0; $i < $num; $i++)
			$blank .= $char;

		return $blank;
	}

	/*取消請款*/
	public function cancelPayment( $params = array() ){}


	/*退貨申請*/
	public function refund( $params = array() ){
		if( count($params) == 0 )
			return false;

		$str = '';
		$count = 0;
		$totalMoney = 0;
		$code = '01';
		$seq = '';
		foreach( $params as $param ){
			$count ++;
			$param['TerminalID'] = $this->TerminalID;
			$this->ajustDefaultParam( $param );
			$totalMoney += $param['TransAmt'];
			$temp = "D{$param['TerminalID']}{$param['OrderID']}{$code}{$param['TransDate']}{$param['TransTime']}{$param['ApproveCode']}{$param['TransAmt']}";
			$blank = $this->fillChar(223);
			$seq = $param['seq'];

			$str .= $temp.$blank. $this->lineBreak;
		}

		$seq = sprintf("%04d",$seq);
		$str = $this->getFileHeader( "BM" , $seq ).$str.$this->getTailerStr($count, 0, $count, abs($totalMoney), 0,abs($totalMoney));
		$time = date("Ymd");
		$fileName = "BM{$this->MerchantID}_{$time}{$seq}";

		if( !$this->writeFile($str,$fileName.'.dat') )
			return false;

		if( !$this->zipFile( $fileName ) )
			return false;

		if( !TransMethods::sendByFTP($this->dataPath.$fileName.'.zip',$this->ftpSite,$this->uploadUsername,$this->uploadPassword,$fileName.'.zip') )
			return  false;

		return true;
	}


	/*退貨取消申請*/
	public function cancelRefund( $param = array() ){}

	/*訂單狀態查詢*/
	public function getInfo( $param = array() ){}
	/* 錯誤代碼轉換*/
	public function getErrorText( $errorCode ):string{}

	public function checkParam( $checkFieldsCsv = '', $param = array() ){
		$fields = explode(',',$checkFieldsCsv);

		$flag = 0;
		foreach($fields as $row)
			if( !isset($param[ $row ]['0']) )
				$flag = 1;

		return ($flag)? true:false;
	}



	public function execute( $data = array() ){
		$rs = $this->sendData( $data );

		$response = $this->getResponse( $data );
		return ($rs && $response != "" )? $response:false;
	}


	public function sendData( $param ){
		return ( $this->bankObj->STAuth( $this->setDefaultData($param) ,1) == 0 )? true: false;
	}

	function send3DData( $param = array() ){
		return ( $this->bankObj->Get3DResponse($param,1) == 0 )? true: false;
	}

	public function execute3D( $data = array() ){
		$rs = $this->send3DData( $data );
		$response = $this->getResponse( $data );
		$this->response = $response;
		return ($rs && $response != "" )? $response:false;
	}

	public function getResponse( $data ){
		$response = $this->bankObj->getResponse();
		$this->response = $response;
		return ( $response != "" )? $response:false;
	}

	public function setDefaultData( $param = array() ){
		$data = array();
		$data = $param;
		$data['MerchantID']   = $this->MerchantID;
		$data['SubMID']       = $this->SubMID;
		$data['TerminalID']   = $this->TerminalID;
		$data['AcquirerID']   = $this->AcquirerID;
		$data['CurrencyCode'] = $this->CurrencyCode;

		return $data;
	}

	public function writeFile($str, $fileName){
        $dir = $this->dataPath;
        mkdirs($dir);
        $fp = fopen($dir.$fileName, 'w');
        fwrite($fp, $str);
        fclose($fp);

        if( file_exists($dir.$fileName) )
        	return true;

        return false;
    }
    public function zipFile( $fileName ){
    	$dir = $this->dataPath;
    	$originFile = $dir.$fileName.'.dat';
    	$zipFile    = $dir.$fileName.'.zip';
    	if( file_exists( $originFile ) ){
    		shell_exec( "zip -j $zipFile $originFile" );
    	}

    	if( file_exists($zipFile) )
        	return true;

        return false;
    }

	function readBatchFile( $param ) : array{
		$replyFileName = $this->getReplyFileName($param);
		$filePath = $this->dataPath.$replyFileName.'.dat';

		if( !file_exists( $filePath ) ){
			echo "There is no Match Bacth file({$fileName})";
			return false;
		}

		$fi = new VipFileIterator( $filePath );
		if( !$fi -> valid() ){
			echo "VipFileIterator is not a resource: filePath:{$filePath}\n";
			return false;
		}

		$log = array(
			'Type'       => $param['type'],
			'ResCode'    => '',
			'ReasonCode' => '',
			'Header'     => '',
			'Data'       => array()
		);

		$orderData = array();
		foreach($fi as $key => $str){
			$mark = substr($str,0,1);
			if( $mark == 'H' ){
				$log['Header'] = substr($str,0,36);

				$log['ResCode'] = substr($str,31,2);
				if( $log['ResCode'] == 'R0' ){
					$log['ReasonCode'] = substr($str,33,3);
					break;
				}
			}

			if( $mark == 'D')
				$log['Data'][] = $this->processRequestBatchData( $param['type'], $str );
		}

		return $log;
	}


	function processRequestBatchData( $type, $str ):array {
		switch( $type){
			case  'cancelAuth':
					return array(
						'OrderID'          => substr($str,77,14),
						'TransAmt'         => (int)substr($str,64,11),
						'ResCode'          => substr($str,118,3),
						'CancelDate'       => substr($str,190,8),
						'CancelTime'       => substr($str,198,6),
					);
				break;
			case 'requestPayment':

					return array(
						'OrderID'          => substr($str,77,14),
						'TransAmt'         => (int)substr($str,66,11),
						'ResCode'          => substr($str,118,3),
						'RequestTime'      => substr($str,190,14),
						'RequestValidDate' => substr($str,204,8),
						'RequestResCode'   => substr($str,212,3)
					);
				break;

			case 'refund':
				return array(
					'OrderID'         => substr($str,77,14),
					'TransAmt'        => (int)substr($str,66,11),
					'ResCode'         => substr($str,118,3),
					'RefundDate'      => substr($str,190,8),
					'RefundTime' 	  => substr($str,198,6),
				);
				break;
		}

		return array();
	}


	function getReplyFileMark( $type ){
		switch( $type ){
			case 'cancelAuth':
				return 'RV';
				break;
			case 'requestPayment':
				return 'RM';
				break;
			case 'refund':
				return 'RM';
				break;
			default:
				echo "There is no matched type({$type}) in 	.";
				exit();
				break;
		}

		return '';
	}


	function getReplyFileName($param){

		if( !isset($param['type']) ){
			echo "there is no param named type in getReplyFileName\n";
			return false;
		}

		if( !isset($param['code'])){
			echo "there is no param named code in getReplyFileName\n";
			return false;
		}


		if( !isset($param['time']) ){
			echo "there is no param named TransDate in getReplyFileName\n";
			return false;
		}

		$date = date("Ymd",$param['time']);
		$mark = $this->getReplyFileMark( $param['type'] );

		return "{$mark}{$this->MerchantID}_{$date}{$param['code']}";
	}

	function fetchZipFile( $param ){
		$replyFileName = $this->getReplyFileName( $param );
		$filePath = $this->dataPath."/{$replyFileName}.zip";
		$receivefilePath = $this->dataPath.$replyFileName.".zip";
		TransMethods::getByFTP($receivefilePath,$this->ftpSite,$this->downloadUsername,$this->downloadPassword,$replyFileName.".zip");

		if( !file_exists($receivefilePath) ){
			echo "there is no file named {$receivefilePath} in fetchZipFile\n";
			return false;
		}

		return true;
	}

	function unzipFile( $param ){
		$fileName = $this->getReplyFileName( $param );
		$filePath = $this->dataPath."/{$fileName}.zip";

		$zipFile  =  $this->dataPath.$fileName.'.zip';

		if( !file_exists($zipFile) )
			return false;

		if( file_exists($this->dataPath."/".$fileName.".dat") )
			return true;

    	shell_exec( "unzip  $zipFile -d ".$this->dataPath."/" );
    	if( file_exists($this->dataPath."/".$fileName.".dat") )
    		return true;

    	return false;
	}
}
