<?php

Class TransMethods{
	static function sendByCurl( $url, $text, $timeOut = 0, $header = array() ){
		$ch = curl_init();

		if( count($header) > 0 )
			curl_setopt($ch, CURLOPT_HTTPHEADER, $header);

		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_BINARYTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 0);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeOut);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $text );
		$result = curl_exec($ch);

		curl_close($ch);

		return $result ;
	}

	static public function sendBySFTP( $ftpSite, $port,$username=null, $password=null, $local_file,$remote_file){

		$connection = @ssh2_connect($ftpSite, $port);
        if (! $connection )
            throw new Exception("Could not connect to $host on port $port.");

        if (! @ssh2_auth_password($connection, $username, $password))
            throw new Exception("Could not authenticate with username $username " .
                                "and password $password.");

        $sftp = @ssh2_sftp($connection);
        if (! $sftp)
            throw new Exception("Could not initialize SFTP subsystem.");

        $stream = @fopen("ssh2.sftp://$sftp$remote_file", 'w');

        if (! $stream)
            throw new Exception("Could not open file: $remote_file");

        $data_to_send = @file_get_contents($local_file);
        if ($data_to_send === false)
            throw new Exception("Could not open local file: $local_file.");

        if (@fwrite($stream, $data_to_send) === false)
            throw new Exception("Could not send data from file: $local_file.");

        @fclose($stream);
	}

	static function sendByFTP($filePath, $ftpSite, $username=null, $password=null, $outputfile=''){
		$rs = 0;
		$conn_id = ftp_connect($ftpSite,21,60) or die("Connect FTP Server Fail");
		$login_result = ftp_login($conn_id, $username, $password);
		// 使用被動模式，這個指令必須在ftp_login 後立即使用
		ftp_pasv($conn_id, true);

		if( ftp_put($conn_id, $outputfile, $filePath, FTP_BINARY) )
			$rs = 1;

		ftp_close($conn_id);
		return ($rs == 1)?true:false;

	}

	static function getByFTP($receiveFilePath = '', $ftpSite, $username=null, $password=null, $ftpFilePath = ''){
		#連接FTP
		// 登入
		$conn = null;
	    for($i=0; $i<5; $i++){
	        // 連線，參數分別是 HOST, PORT, TIMEOUT
	        $conn = ftp_connect($ftpSite, 21, 60);
	        if( ftp_login($conn, $username, $password) ){
	            break;
	        }else{
	            if( $i == 4 ){
	                echo 'FTP無法登入';;
	                exit;
	            }else{
	                sleep(1);
	                continue;
	            }
	        }
	    }
		// 使用被動模式，這個指令必須在ftp_login 後立即使用
		ftp_pasv($conn, true);
		// 目前路徑
		//$pwd= ftp_pwd($conn);
        for($i=0; $i < 5; $i++){
            // 取回檔案，使用二進位的方式
            if( ftp_get($conn, $receiveFilePath, $ftpFilePath , FTP_BINARY) ) {
                // echo "Successfully written to local_file\n";
                break;
            } else {
                sleep(1);
                // echo "There was a problem\n";
            }
        }

		// 關閉連結
		ftp_close($conn);
	}
}
