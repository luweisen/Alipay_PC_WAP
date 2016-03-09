<?php
namespace Common\Library\Pay\Alipay;
use Common\Library\Pay\Alipay\lib\AlipaySubmit;
use Common\Library\Pay\Alipay\lib\AlipayWapSubmit;

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
    }

    public function test()
    {
        self::cfg();

        var_dump(ALI_CACERT);
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

        //页面跳转同步通知页面路径
        if (!defined('ALI_RETURN_URL')) {
            define("ALI_RETURN_URL", "http://demo.demo.com/test/returnurlpc");
        }

        //服务器异步通知页面路径
        if (!defined('ALI_NOTIFY_URL')) {
            define("ALI_NOTIFY_URL", "http://demo.demo.com/test/notifyurlpc");
        }

        //wap端页面跳转同步通知页面路径
        if (!defined('ALI_WAP_RETURN_URL')) {
            define("ALI_WAP_RETURN_URL", "http://demo.demo.com/test/returnurlmobile");
        }

        //wap端服务器异步通知页面路径
        if (!defined('ALI_WAP_NOTIFY_URL')) {
            define("ALI_WAP_NOTIFY_URL", "http:/demo.demo.com/test/notifyurlmobile");
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
     * 网页端支付功能
     * @param type $out_trade_no     商品唯一订单号
     * @param type $subject          商品名称
     * @param type $total_fee        商品总价
     * @param type $body             商品描述说明
     * @param type $show_url         商品展示地址
     */
    public function doSubmit($out_trade_no, $subject, $total_fee, $body = "", $show_url = "") {
        header('Content-type:text/html;charset=utf-8');
        self::cfg();
        //构造要请求的参数数组，无需改动
        $parameter = array(
            "service" => "create_direct_pay_by_user",
            "partner" => trim($this->partner),
            "payment_type" => ALI_PAYMENT_TYPE,
            "notify_url" => ALI_NOTIFY_URL,
            "return_url" => ALI_RETURN_URL,
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
        echo $html_text;
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
        $req_data = '<direct_trade_create_req><notify_url>' . ALI_WAP_NOTIFY_URL . '</notify_url><call_back_url>' . ALI_WAP_RETURN_URL . '</call_back_url><seller_account_name>' . $this->seller . '</seller_account_name><out_trade_no>' . $out_trade_no . '</out_trade_no><subject>' . $subject . '</subject><total_fee>' . $total_fee . '</total_fee><merchant_url>' . $merchant_url . '</merchant_url></direct_trade_create_req>';

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
        $html_text = $alipayWapSubmit->buildRequestForm($parameter, 'get', '正在跳转为您跳转到支付宝……');
        echo $html_text;
    }

}

?>
