<?php
namespace Common\Library\Pay\Alipay;
use Common\Library\Pay\Alipay\lib\AlipaySubmit;
use Common\Library\Pay\Alipay\lib\AlipayWapSubmit;
// use Common\Library\Pay\Alipay\lib\AlipayNotify;
use Common\Library\Pay\Alipay\lib\AlipayNotifyMobile;

class Alipay {

    private $config = array();

    function __construct($config)
    {
        $this->config = $config;

        //合作身份者id，以2088开头的16位纯数字
        $this->partner = $this->config['pid'];

        //安全检验码，以数字和字母组成的32位字符
        $this->key = $this->config['key'];

        //卖家支付宝帐户
        $this->seller = $this->config['seller'];

        $this->domain = "http://{$_SERVER['HTTP_HOST']}";
        $this->wap_return_url = $this->domain . '/alipay/callback';
        $this->wap_notify_url = $this->domain . '/alipay/notify';

        $this->pc_return_url = $this->domain . '/alipay/callback';
        $this->pc_notify_url = $this->domain . '/alipay/notify';
    }

    private static function cfg() {
        //签名方式 不需修改
        if (!defined('ALI_SIGN_TYPE')) {
            define("ALI_SIGN_TYPE", strtoupper('MD5'));
        }

        //字符编码格式 目前支持 gbk 或 utf-8
        if (!defined('ALI_INPUT_CHARSET')) {
            define("ALI_INPUT_CHARSET", strtolower('utf-8'));
        }
        //ca证书路径地址，用于curl中ssl校验
        if (!defined('ALI_CACERT')) {
            define("ALI_CACERT", getcwd() . '/Application/Common/Library/Pay/Alipay/cacert.pem');
        }
        //访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
        if (!defined('ALI_TRANSPORT')) {
            define("ALI_TRANSPORT", 'http');
        }

        //支付类型
        if (!defined('ALI_PAYMENT_TYPE')) {
            define("ALI_PAYMENT_TYPE", 1);
        }
    }

    public function getBaseConfig() {
        self::cfg();
        $config = array();
        $config['partner'] = $this->partner;
        $config['key'] = $this->key;
        $config['sign_type'] = ALI_SIGN_TYPE;
        $config['input_charset'] = ALI_INPUT_CHARSET;
        $config['cacert'] = ALI_CACERT;
        $config['transport'] = 'http';


        return $config;
    }

    /**
     * wap支付
     * @param type $out_trade_no   商品唯一订单号
     * @param type $subject        商品名称
     * @param type $total_fee      商品总价
     * @param type $merchant_url   用户终端支付跳转的地址
     */
    public function doSubmitMobile($out_trade_no,$subject,$total_fee,$merchant_url="") {
        header('Content-type:text/html;charset=utf-8');
        self::cfg();
        $format = "xml";
        $v = "2.0";
        $req_id = date('Ymdhis');
        //请求业务参数详细
        $req_data = '<direct_trade_create_req><notify_url>' . $this->wap_notify_url . '</notify_url><call_back_url>' . $this->wap_return_url . '</call_back_url><seller_account_name>' . $this->seller . '</seller_account_name><out_trade_no>' . $out_trade_no . '</out_trade_no><subject>' . $subject . '</subject><total_fee>' . $total_fee . '</total_fee><merchant_url>' . $merchant_url . '</merchant_url></direct_trade_create_req>';

        //构造要请求的参数数组，无需改动
        $para_token = array(
            "service" => "alipay.wap.trade.create.direct",
            "partner" => trim($this->partner),
            "sec_id" => trim(ALI_SIGN_TYPE),
            "format" => $format,
            "v" => $v,
            "req_id" => $req_id,
            "req_data" => $req_data,
            "_input_charset" => trim(strtolower(ALI_INPUT_CHARSET))
        );


        //建立请求
        $alipayWapSubmit = new AlipayWapSubmit($this->getBaseConfig());
        $html_text = $alipayWapSubmit->buildRequestHttp($para_token);

        //URLDECODE返回的信息
        $html_text = urldecode($html_text);

        //解析远程模拟提交后返回的信息
        $para_html_text = $alipayWapSubmit->parseResponse($html_text);

        //获取request_token
        $request_token = $para_html_text['request_token'];

        /*         * ***********************根据授权码token调用交易接口alipay.wap.auth.authAndExecute************************* */
        $req_data = '<auth_and_execute_req><request_token>' . $request_token . '</request_token></auth_and_execute_req>';

        //构造要请求的参数数组，无需改动
        $parameter = array(
            "service" => "alipay.wap.auth.authAndExecute",
            "partner" => trim($this->partner),
            "sec_id" => trim(ALI_SIGN_TYPE),
            "format" => $format,
            "v" => $v,
            "req_id" => $req_id,
            "req_data" => $req_data,
            "_input_charset" => trim(strtolower(ALI_INPUT_CHARSET))
        );


        //建立请求
        $alipayWapSubmit = new AlipayWapSubmit($this->getBaseConfig());
        $html_text = $alipayWapSubmit->buildRequestForm($parameter, 'get', '正在为您跳转到支付宝……');
        return $html_text;
    }

    public function returnWap()
    {
        header('Content-type:text/html;charset=utf-8');
        $alipayNotify = new AlipayNotifyMobile($this->getBaseConfig());
        $verify_result = $alipayNotify->verifyReturn();

        if($verify_result) {//验证成功
            $out_trade_no = $_GET['out_trade_no'];    //商户订单号
            $trade_no = $_GET['trade_no'];            //支付宝交易号
            $result = $_GET['result'];                //交易状态

            //内部业务逻辑   
            return array($out_trade_no, $trade_no, $result);
        }else{
            return false;
        }
    }

    public function notifyWap()
    {
        header('Content-type:text/html;charset=utf-8');
        $alipayNotify = new AlipayNotifyMobile($this->getBaseConfig());
        $verify_result = $alipayNotify->verifyNotify();

        if($verify_result) {//验证成功
            $notify_data = @simplexml_load_string($_POST['notify_data'],NULL,LIBXML_NOCDATA);
            $notify_data_arrs = json_decode(json_encode($notify_data),true);

            if (!empty($notify_data_arrs['payment_type'])) {
                $out_trade_no = $notify_data_arrs['out_trade_no']; //商户订单号
                $trade_no = $notify_data_arrs['trade_no'];         //支付宝交易号
                $trade_status = $notify_data_arrs['trade_status']; //交易状态


                return array($out_trade_no, $trade_no, $trade_status, $notify_data_arrs);
            }
        }

        return false;
    }

/**
 * PC 业务
 

     * /**
     * 网页端支付功能
     * @param type $out_trade_no     商品唯一订单号
     * @param type $subject          商品名称
     * @param type $total_fee        商品总价
     * @param type $body             商品描述说明
     * @param type $show_url         商品展示地址
     * 
    public function doSubmit($out_trade_no, $subject, $total_fee, $body = "", $show_url = "") {
        header('Content-type:text/html;charset=utf-8');
        self::cfg();
        //构造要请求的参数数组，无需改动
        $parameter = array(
            "service" => "create_direct_pay_by_user",
            "partner" => trim($this->partner),
            "payment_type" => ALI_PAYMENT_TYPE,
            "notify_url" => $this->pc_notify_url,
            "return_url" => $this->pc_return_url,
            "seller_email" => $this->seller,
            "out_trade_no" => $out_trade_no, //商户网站订单系统中唯一订单号，必填
            "subject" => $subject, //订单名称 必填
            "total_fee" => $total_fee, //付款金额 必填
            "body" => $body, //订单描述
            "show_url" => $show_url, //商品展示地址 需以http://开头的完整路径
            "anti_phishing_key" => '', //防钓鱼时间戳
            "exter_invoke_ip" => '', //客户端的IP地址
            "_input_charset" => trim(strtolower(ALI_INPUT_CHARSET))
        );


        //建立请求
        $alipaySubmit = new AlipaySubmit($this->getBaseConfig());
        $html_text = $alipaySubmit->buildRequestForm($parameter, "get", "正在跳转为您跳转到支付宝……");
        return $html_text;
    }

    public function returnPC()
    {
        $alipayNotify = new AlipayNotify($this->getBaseConfig());
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
    }

    public function notifyPC()
    {
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
    }

 */

}

?>
