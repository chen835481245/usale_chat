<?php
/**
 * http请求和一些支付相关的方法
 */
namespace GatewayWorker\Lib;

class HttpRequest
{
	public $ch;
	public $timeOut;
	function __construct($timeOut=30)
	{
		$this->timeOut=$timeOut;
	}
	function getHttpResponsePOST($url, $para,$isHttps=false,$header='')
	{
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
        if(!empty($header)){
            curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        }
		curl_setopt($curl,CURLOPT_RETURNTRANSFER, 1);// 显示输出结果
		curl_setopt($curl,CURLOPT_POST,true); // post传输数据
		curl_setopt($curl,CURLOPT_POSTFIELDS,$para);// post传输数据
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeOut);

        if($isHttps){
            curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 信任任何证书
            curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 检查证书中是否设置域名
        }
		$responseText = curl_exec($curl);
		//var_dump( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
		curl_close($curl);
		return $responseText;
	}

	function getHttpResponseGET($url)
	{
		$curl = curl_init($url);
		curl_setopt($curl, CURLOPT_HEADER, 0 ); // 过滤HTTP头
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($curl, CURLOPT_CONNECTTIMEOUT, $this->timeOut);
		$data = curl_exec($curl);
		//var_dump( curl_error($curl) );//如果执行curl过程中出现异常，可打开此开关，以便查看异常内容
		curl_close($curl);
		return $data;
	}
	/**
	 * 把数组所有元素，按照“参数=参数值”的模式用“&”字符拼接成字符串，并对字符串做urlencode编码
	 * @param $para 需要拼接的数组
	 * return 拼接完成以后的字符串
	 */
	function createLinkstringUrlencode($para) {
		$arg  = "";
		while (list ($key, $val) = each ($para)) {
			$arg.=$key."=".urlencode($val)."&";
		}
		//去掉最后一个&字符
		$arg = substr($arg,0,count($arg)-2);

		//如果存在转义字符，那么去掉转义
		if(get_magic_quotes_gpc()){$arg = stripslashes($arg);}

		return $arg;
	}
	/**
	 * 银联签名
	 * @param $req 签名数组
	 */
	function sign($req)
	{
		$sign='';
		foreach($req as $key=>$val){
			$sign.="&".$key."=".$val;
		}
		$sign=substr($sign, 1);
		$sign.=$GLOBALS['spConfig']['unionKey'];
		$sign=md5($sign);
		return $sign;
	}








}