<?php
/*header("location:aliyun.html");
exit;*/

// [ 应用入口文件 ]
// if($_SERVER['REQUEST_URI']=='/'){
// 	header('Location:/home/');
// 	die();	
// }
$allow_origin = array(
    'http://webbof.ewtouch.com',
    'http://shopbof.ewtouch.com',
    'http://wapbof.ewtouch.com',
);
$origin = isset($_SERVER['HTTP_ORIGIN'])? $_SERVER['HTTP_ORIGIN'] : '';

if(in_array($origin, $allow_origin)){
    header('Access-Control-Allow-Origin:'.$origin);
}
//header('Access-Control-Allow-Origin:*');
header('Access-Control-Allow-Credentials:true');

header('Access-Control-Allow-Headers: X-Requested-With, Content-Type');
header('Access-Control-Allow-Methods: GET, PUT, POST, DELETE');
header('Access-Control-Max-Age: ' . 3600 * 24);
# 定义应用目录
define('APP_PATH', __DIR__ . '/../application/');
# 定义应用缓存目录
define('RUNTIME_PATH', __DIR__ . '/../runtime/');
# 定义项目根目录
define('ROOT_PATH',__DIR__.DIRECTORY_SEPARATOR.'..'.DIRECTORY_SEPARATOR);
# 开启调试模式
define('APP_DEBUG', true);
#定义主域名
define('DOMIAN', 'http://api.szcxdzsw.com');
define('FRDOMAIN', 'http://www.szcxdzsw.com');
# 加载框架引导文件
require __DIR__ . '/../thinkphp/start.php';
