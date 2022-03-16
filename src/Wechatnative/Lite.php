<?php

namespace MartinYeung\PhalapiHelper\Wechatnative;

use PhalApi\Exception\BadRequestException;

/**
 * 微信支付native扩展
 *
 * @author : Martin 2022-03-01
 */
class Lite {
	protected $appid;
	protected $secret;
	protected $mch_id;
	protected $mch_key;
	protected $notify_url;
	protected $time_expire;

	protected $openid; //openid
	protected $out_trade_no; //商户订单号
	protected $body; //商品描述
	protected $total_fee; //总金额

	/**
	 *
	 * @param string $appid App应用appid
	 * @param string $secret App应用后台生成的秘钥，不要随便修改
	 * @param string $mch_id 商户号
	 */

	public function __construct() {
		$this->appid = \PhalApi\DI()->config->get('app.Wechatnative')['appid'];
		$this->secret = \PhalApi\DI()->config->get('app.Wechatnative')['secret_key'];
		$this->mch_id = \PhalApi\DI()->config->get('app.Wechatnative')['mch_id'];
		$this->mch_key = \PhalApi\DI()->config->get('app.Wechatnative')['mch_key'];
		$this->notify_url = \PhalApi\DI()->config->get('app.Wechatnative')['notify_url'];

		if (!$this->appid) {
			throw new BadRequestException('请配置appid', 600);
		}
		//if (!$this->secret) {
		//	throw new BadRequestException('请配置secret_key', 600);
		//}
		if (!$this->mch_id) {
			throw new BadRequestException('请配置mch_id', 600);
		}
		if (!$this->mch_key) {
			throw new BadRequestException('请配置mch_key', 600);
		}
		if (!$this->notify_url) {
			throw new BadRequestException('请配置notify_url', 600);
		}
        if(!$this->time_expire) {
            $this->time_expire = date('YmdHis', time() + 86400); //订单支付的过期时间(eg:一天过期)
        }
	}

	/**
	 * 微信支付
	 *
	 * @desc 商户先调用该接口在微信支付服务后台生成预支付交易单，返回正确的预支付交易后调起支付。
	 * @return array
	 * @return int ret 状态码：200表示数据获取成功
	 * @return array data 返回数据,数据获取失败时为空
	 * @return string data.appId 微信分配的小程序ID
	 * @return string data.timeStamp 时间戳从1970年1月1日00:00:00至今的秒数,即当前的时间
	 * @return string data.nonceStr 随机字符串，长度为32个字符以下。
	 * @return string data.package 统一下单接口返回的 prepay_id 参数值
	 * @return string data.signType 签名算法，暂支持 MD5
	 * @return string data.paySign 签名,具体签名方案参见小程序支付接口文档;
	 * @return string msg 错误提示信息
	 */

	public function WxPay($total_fee, $body, $out_trade_no='',  $notify_url='') {
		if (!$this->mch_id) {
			throw new BadRequestException('请配置商户号', 600);
		}
		if (!$this->mch_key) {
			throw new BadRequestException('请配置支付秘钥', 600);
		}
		if (!$this->notify_url) {
			throw new BadRequestException('请配置支付结果接收网址', 600);
		}
		if ($total_fee < 0.01) {
			throw new BadRequestException('付款金额最低0.01', 600);
		}
		if (!$total_fee) {
			throw new BadRequestException('付款金额不能为空', 600);
		}
		if (!$body) {
			$body = '商品充值';
		}

        if(!$out_trade_no) {
            $out_trade_no = date("YmdHis");
            $chars = '0123456789';
            $max = strlen($chars) - 1;
            PHP_VERSION < '4.2.0' && mt_srand((double)microtime() * 1000000);
            for ($i = 0; $i < 4; $i++) {
                $out_trade_no .= $chars[mt_rand(0, $max)];
            }
        }
        if($notify_url) {
            $this->notify_url = $notify_url;
        }

		$this->out_trade_no = $out_trade_no;
		//$this->openid = $openid;
		$this->body = $body;
		$this->total_fee = $total_fee;
		// 统一下单接口
		$res = $this->weixinnative();
		return $res;
	}
	// 统一下单接口
	private function unifiedorder() {
		$url = 'https://api.mch.weixin.qq.com/pay/unifiedorder';
		$parameters = array(
            'appid' => $this->appid, // App应用ID
			'body' => $this->body, // 商品描述
			'mch_id' => $this->mch_id, // 商户号
			'nonce_str' => $this->createNoncestr(), // 随机字符串
			'notify_url' => $this->notify_url, // 通知地址  确保外网能正常访问
			'out_trade_no' => $this->out_trade_no, // 商户订单号
			'spbill_create_ip' => $this->get_client_ip(), // 终端IP
			'attach' => $this->get_client_ip(), // 终端IP
			'total_fee' => floatval($this->total_fee * 100), // 总金额 单位 分
			'time_expire' => $this->time_expire, // 交易结束时间
			'trade_type' => 'NATIVE',// 交易类型
            'product_id' => 1, // 此参数为二维码中包含的商品ID，商户自行定义。
		);
		// 统一下单签名
		$parameters['sign'] = $this->getSign($parameters);
		$xmlData = $this->arrayToXml($parameters);
		$xml = $this->postXmlCurl($xmlData, $url, 60);
		$return = $this->xmlToArray($xml);
		return $return;
	}

	private static function postXmlCurl($xml, $url, $second = 30) {
		$ch = curl_init();
		// 设置超时
		curl_setopt($ch, CURLOPT_TIMEOUT, $second);
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); //严格校验
		// 设置header
		curl_setopt($ch, CURLOPT_HEADER, false);
		// 要求结果为字符串且输出到屏幕上
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// post提交方式
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $xml);

		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 20);
		curl_setopt($ch, CURLOPT_TIMEOUT, 40);
		set_time_limit(0);
		// 运行curl
		$data = curl_exec($ch);
		// 返回结果
		if ($data) {
			curl_close($ch);
			return $data;
		} else {
			$error = curl_errno($ch);
			curl_close($ch);
			throw new BadRequestException('curl出错', $error);
		}
	}
	// 数组转换成xml
	private function arrayToXml($arr) {
		$xml = "<root>";
		foreach ($arr as $key => $val) {
			if (is_array($val)) {
				$xml .= "<" . $key . ">" . $this->arrayToXml($val) . "</" . $key . ">";
			} else {
				$xml .= "<" . $key . ">" . $val . "</" . $key . ">";
			}
		}
		$xml .= "</root>";
		return $xml;
	}
	// xml转换成数组
	private function xmlToArray($xml) {
		// 禁止引用外部xml实体
		libxml_disable_entity_loader(true);
		$xmlstring = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
		$val = json_decode(json_encode($xmlstring), true);
		return $val;
	}
	// 微信app接口
	private function weixinnative() {
		// 统一下单接口
		$return = $this->unifiedorder();
        //p($return);
        if($return['return_code'] == 'FAIL') {
            throw new BadRequestException($return['return_msg'],  -41003 - 400);
        }

        if(!$this->verifySign($return)) {
            throw new BadRequestException('返回数据校验异常',  -41003 - 400);
        }
        $prepayId = $return['prepay_id'];
        $codeUrl = $return['code_url'];

        //$codeUrl = $this->getShortUrl($codeUrl);
		return [
            'prepayId' => $prepayId,
            'codeUrl' => $codeUrl,
        ];
	}
    // 转换短链接
    public function getShortUrl($longUrl) {
		$url = 'https://api.mch.weixin.qq.com/tools/shorturl';
		$parameters = array(
            'appid' => $this->appid, // App应用ID
			'mch_id' => $this->mch_id, // 商户号
			'long_url' => $longUrl, // weixin：//wxpay/bizpayurl?sign=XXXXX&appid=XXXXX&mch_id=XXXXX&product_id=XXXXXX&time_stamp=XXXXXX&nonce_str=XXXXX 需要转换的URL，签名用原串，传输需URLencode
			'nonce_str' => $this->createNoncestr(), // 随机字符串
		);
		// 统一下单签名
		$parameters['sign'] = $this->getSign($parameters);
		$xmlData = $this->arrayToXml($parameters);
		$xml = $this->postXmlCurl($xmlData, $url, 60);
		$return = $this->xmlToArray($xml);

        if($return['return_code'] == 'FAIL') {
            throw new BadRequestException($return['return_msg'],  -41003 - 400);
        }

        if(!$this->verifySign($return)) {
            throw new BadRequestException('返回数据校验异常',  -41003 - 400);
        }

		return $return['short_url'];
    }
	// 作用：产生随机字符串，不长于32位
	private function createNoncestr($length = 32) {
		$chars = "abcdefghijklmnopqrstuvwxyz0123456789";
		$str = "";
		for ($i = 0; $i < $length; $i++) {
			$str .= substr($chars, mt_rand(0, strlen($chars) - 1), 1);
		}
		return $str;
	}
	// 作用：验证签名
    public function verifySign($obj) {
        if(!isset($obj['sign'])) {
            return false;
        }
        $sign = $obj['sign'];
        unset($obj['sign']);
        return $this->getSign($obj) === $sign;
    }
	// 作用：生成签名
	public function getSign($obj) {
		foreach ($obj as $k => $v) {
			$Parameters[$k] = $v;
		}
		// 签名步骤一：按字典序排序参数
		ksort($Parameters);
		$String = $this->formatBizQueryParaMap($Parameters, false);
		// 签名步骤二：在string后加入KEY
		$String = $String . "&key=" . $this->mch_key;
		// 签名步骤三：MD5加密
		$String = md5($String);
		// 签名步骤四：所有字符转为大写
		$result_ = strtoupper($String);
		return $result_;
	}
	// 作用：格式化参数，签名过程需要使用
	private function formatBizQueryParaMap($paraMap, $urlencode) {
		$buff = "";
		ksort($paraMap);
		foreach ($paraMap as $k => $v) {
			if ($urlencode) {
				$v = urlencode($v);
			}
			$buff .= strtolower($k) . "=" . $v . "&";
		}
		if (strlen($buff) > 0) {
			$reqPar = substr($buff, 0, strlen($buff) - 1);
		}
		return $reqPar;
	}

    private function postJson($url, $data){
        $ch = curl_init();
        $header = array("Accept-Charset: utf-8");
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $tmpInfo = curl_exec($ch);
        if (curl_errno($ch)) {
            return false;
        }else{
            return $tmpInfo;
        }
    }

    /**
	 * 获取当前服务器的IP
	 *
	 * @return Ambigous <string, unknown>
	 */
	private function get_client_ip() {
		if (isset($_SERVER['REMOTE_ADDR'])) {
			$cip = $_SERVER['REMOTE_ADDR'];
		} elseif (getenv("REMOTE_ADDR")) {
			$cip = getenv("REMOTE_ADDR");
		} elseif (getenv("HTTP_CLIENT_IP")) {
			$cip = getenv("HTTP_CLIENT_IP");
		} else {
			$cip = "127.0.0.1";
		}
		return $cip;
	}
}
