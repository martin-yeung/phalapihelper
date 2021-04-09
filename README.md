# phalapihelper
phalapi 2.* helper

//=======================================================config助手====================================================
@public/init.php
defined('API_ROOT') || define('API_ROOT', dirname(__FILE__) . '/..');

$host_domain = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ? $_SERVER['HTTP_X_FORWARDED_HOST'] : (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : $_SERVER['SERVER_NAME'].($_SERVER['SERVER_PORT'] != '80' ? ':'.$_SERVER['SERVER_PORT'] : ''));
if(strpos($host_domain, '.website.com') !== false && strpos($host_domain, '.rd.') === false) {
    define('APP_PRO', true);
} else {
    if(strpos($host_domain, 'website.co') !== false || strpos($host_domain, 'website.co') !== false) {
        define('APP_DEBUG', true);
    } else {
        define('APP_TEST', true);
    }
}
defined('APP_PRO') || define('APP_PRO', false);
defined('APP_TEST') || define('APP_TEST', false);
defined('APP_DEBUG') || define('APP_DEBUG', false);
defined('APP_ENV') || define('APP_ENV', APP_PRO ? 'pro' : (APP_TEST ? 'test' : 'debug'));
//var_dump(['APP_PRO'=>APP_PRO, 'APP_TEST'=>APP_TEST, 'APP_DEBUG'=>APP_DEBUG]);exit();


FileConfig用法【不用修改配置文件以兼容各个运行环境】

@config/di.php
//use PhalApi\Config\FileConfig;
use MartinYeung\PhalapiHelper\Config\FileConfig;


自动处理debug等
@config/sys.php
return array(
    /**
     * @var boolean 是否开启接口调试模式，开启后在客户端可以直接看到更多调试信息
     */
    'debug' => APP_DEBUG ? true : false,



//=======================================================微信小程序助手====================================================

//=======================================================微信支付App扩展====================================================