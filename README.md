#alipay-web-wap
#支付宝网页端和手机端支付封装类
##使用简介
#####第一步：配置Alipay.php文件中的cfg方法 根据注释说明填写相应的支付宝帐号及回调地址
#####第二步：调用 实例化类 $alipay = new Alipay();
######网页端调用：$alipay->doSubmit($out_trade_no, $subject, $total_fee);
######手机端调用：$alipay->doSubmitMobile($out_trade_no, $subject, $total_fee);

##关于网页端和手机端的同步和异步通知处理
####网页同步通知端

    $alipayNotify = new AlipayNotify(Alipay::getBaseConfig());
    $verify_result = $alipayNotify->verifyReturn();
    if ($verify_result) {                          //验证成功
    	$out_trade_no = $_GET['out_trade_no'];     //商户订单号
    	$trade_no = $_GET['trade_no'];             //支付宝交易号
    	$trade_status = $_GET['trade_status'];     //交易状态
    
        if($_GET['trade_status'] == 'TRADE_FINISHED' || $_GET['trade_status'] == 'TRADE_SUCCESS') {
    		//TO DO 处理自己的内部业务逻辑
        }
        else {
          echo "trade_status=".$_GET['trade_status'];
        }
    		
    	echo "验证成功<br />";
    } else {
       	echo "验证失败";
    }

####网页端异步通知    
    $alipayNotify = new AlipayNotify(Alipay::getBaseConfig());
    $verify_result = $alipayNotify->verifyNotify();
    
    
    if($verify_result) {//验证成功
    	$out_trade_no = $_POST['out_trade_no'];    //商户订单号
    	$trade_no = $_POST['trade_no'];            //支付宝交易号
    	$trade_status = $_POST['trade_status'];    //交易状态
    	if($_POST['trade_status'] == 'TRADE_FINISHED') {
            //TO DO 处理自己的内部业务逻辑
    	}else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
            //TO DO 处理自己的内部业务逻辑
    	}
    	echo "success";
    }else{
    	echo "fail";
    }
    
####手机端同步通知  
    $alipayNotify = new AlipayNotifyMobile(Alipay::getBaseConfig());
    $verify_result = $alipayNotify->verifyReturn();
    
    if($verify_result) {//验证成功
    	$out_trade_no = $_GET['out_trade_no'];    //商户订单号
    	$trade_no = $_GET['trade_no'];            //支付宝交易号
    	$result = $_GET['result'];                //交易状态
    	//TO DO 处理自己的内部业务逻辑	
    	echo "验证成功<br />";
    }else{
    	echo "验证失败";
    }

####手机端异步通知    
    $alipayNotify = new AlipayNotifyMobile(Alipay::getBaseConfig());
    $verify_result = $alipayNotify->verifyNotify();
    
    if($verify_result) {//验证成功
    	$notify_data = @simplexml_load_string($_POST['notify_data'],NULL,LIBXML_NOCDATA);
        $notify_data_arrs = json_decode(json_encode($notify_data),true);
    	
    	if (!empty($notify_data_arrs['payment_type'])) {
            $out_trade_no = $notify_data_arrs['out_trade_no']; //商户订单号
            $trade_no = $notify_data_arrs['trade_no'];         //支付宝交易号
            $trade_status = $notify_data_arrs['trade_status']; //交易状态
    
    
            $trade = Trade::getTradeByOutTradeNo($out_trade_no);
            $user = User::getUserByPk($trade->getUserId());
    
            if ($trade && $user) {
                if ($trade_status == 'TRADE_FINISHED') {
                    //TO DO 处理自己的内部业务逻辑
                } else if ($trade_status == 'TRADE_SUCCESS') {
                    //TO DO 处理自己的内部业务逻辑
                }
                echo "success";  //请不要修改或删除
            }
        }
    }else{
    	echo "fail";
    }