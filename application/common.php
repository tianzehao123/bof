<?php

function ajax($status = '1', $message = '', $data = '')
{
    return json(['status' => $status, 'message' => $message, 'data' => $data]);
}

/**
 * 获取客户端IP地址
 * @param integer $type 返回类型 0 返回IP地址 1 返回IPV4地址数字
 * @param boolean $adv 是否进行高级模式获取（有可能被伪装）
 * @return mixed
 */
function get_client_ip($type = 0, $adv = false)
{
    $type = $type ? 1 : 0;
    static $ip = NULL;
    if ($ip !== NULL) return $ip[$type];
    if ($adv) {
        if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $arr = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR']);
            $pos = array_search('unknown', $arr);
            if (false !== $pos) unset($arr[$pos]);
            $ip = trim($arr[0]);
        } elseif (isset($_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['REMOTE_ADDR'])) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
    } elseif (isset($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // IP地址合法验证
    $long = sprintf("%u", ip2long($ip));
    $ip = $long ? array($ip, $long) : array('0.0.0.0', 0);
    return $ip[$type];
}

/**
 * 生成推广二维码
 * @param int $id 订单id
 * @param $level 容错等级
 * @param $size 图片大小
 * @return
 */

use codes\QRcode;

function qrcode($id, $level = 3, $size = 4)
{
    $name = 'qrcode' . $id;
    $path = "./Uploads/qrcode/$name.png";
    $errorCorrectionLevel = intval($level);//容错级别
    $matrixPointSize = intval($size);//生成图片大小
    //生成二维码图片
    $object = new QRcode();
    $url = 'http://' . $_SERVER['HTTP_HOST'] . '/Home/Wechat/code.html?pid=' . $id;
    $img = $object->png($url, $path, $errorCorrectionLevel, $matrixPointSize, 2, false);
}

/**
 * 随机生成订单号
 */
function orderNum()
{
    do {
        $num = date('Y') . date('m') . time() . rand(100, 999);
        $res = db('order')->where(['order_sn' => $num])->find();
        $res1 = db('recharge')->where(['order_sn' => $num])->find();
    } while ($res || $res1);
    return $num;
}

/**
 * 随机生成订单号
 */
function orderNumBof()
{
    do {
        $num = date('Y') . date('m') . time() . rand(100, 999);
        $res = db('bof_deal')->where(['order_sn' => $num])->find();
    } while ($res);
    return $num;
}

/*function dd($obj){
    dump($obj);die;
}
*/
// 判断是否是微信内部浏览器
function is_weixin()
{
    return strpos($_SERVER['HTTP_USER_AGENT'], 'MicroMessenger') == false ? false : true;
}

/**
 * 对象转换成数组
 * @param $obj
 */
function objToArray($obj)
{
    return json_decode(json_encode($obj), true);
}

/**
 * 模拟提交参数，支持https提交 可用于各类api请求
 * @param string $url ： 提交的地址
 * @param array $data :POST数组
 * @param string $method : POST/GET，默认GET方式
 * @return mixed
 */
function http($url, $data = '', $method = 'GET')
{
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; MSIE 5.01; Windows NT 5.0)');
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_AUTOREFERER, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        exit(curl_error($ch));
    }
    curl_close($ch);
    // 返回结果集
    return $result;
}

/**
 * @param $Mobile
 * @return array
 * @auther mapengcheng
 */
function NewSms($Mobile)
{
    $str = "123456789012345678901234567890";
    $str = str_shuffle($str);
    $code = substr($str, 3, 6);
    $data = "username=%s&password=%s&mobile=%s&content=%s";
    $url = "http://120.55.248.18/smsSend.do?";
    $name = "BOF";
    $pwd = md5("yY6qY1uL");
    $pass = md5($name . $pwd);
    $to = $Mobile;
    $content = "您的验证码是" . $code . "，请在十分钟内填写，为确保您的账户安全，切勿将验证码发送于他人。【蓝海国际】";
    $content = urlencode($content);
    $rdata = sprintf($data, $name, $pass, $to, $content);
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $rdata);
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = curl_exec($ch);
    curl_close($ch);
    return ['code' => $code, 'data' => $result, 'msg' => ''];
}

/**
 *查询要查询用户指定级别内的所有下级id
 *$uid:要查询用户集合
 *$class:要查询的级别
 *$userall:静态变量占位
 *$users:用户集合----二维数组（）
 *return----查询指定用户的指定级别内的所有下级id集合(包括自己)
 */
function getChildenAll($uid, $users, $userall = '', $class = '')
{
    if (empty($userall)) {
        static $userall = [];
    } else {
        static $userall = [];
        $userall = [];
    }
    if (!in_array($uid, $userall)) {
        if (is_array($uid)) {
            foreach ($uid as $v) {
                $userall[] = $v;
            }
        } else {
            array_push($userall, $uid);
        }
    }
    $userChildren = [];
    foreach ($users as $k => $v) {
        if (is_array($uid)) {
            if (in_array($v['pid'], $uid)) {
                array_push($userChildren, $v['id']);
            }
        } else {
            if ($v['pid'] == $uid) {
                array_push($userChildren, $v['id']);
            }
        }
    }
    $userall = array_unique(array_merge($userall, $userChildren));
    if (!empty($userChildren)) {
        if ($class) {
            $class--;
            if ($class > 0) {
                getChildenAll($userChildren, $users, '', $class);
            }
        } else {
            getChildenAll($userChildren, $users);
        }
    }
    sort($userall);

    return $userall;
}

function getChildenAllNum($id)
{
    $user = db('users')->field('id,pid')->select();
    return count(getChildenAll($id, $user, 'xiaopengcheng'));
}

/**
 * 获取指定级别下级
 * @param $uid char 要查询下级的用户集合id；如'1,2,3'
 * @param $num int   要查询的级别
 * @return 查询级别的用户下级
 */
function getChilden($uid, $num = 0)
{
    $where['pid'] = ['IN', "$uid"];
    $user1 = db('users')->where($where)->field('id,pid')->select();
    $users_id = '';
    foreach ($user1 as $k => $v) {
        $users_id .= $v['id'] . ',';
    }
    $users_id = trim($users_id, ',');    //一级下级
    if ($num == 0) {
        $users_id = getChilden($users_id);
    } else {
        for ($i = 1; $i < $num; $i++) {
            if (!$users_id) {
                return $users_id;
            }
            $users_id = getChilden($users_id, $num - 1);
            return $users_id;
        }
    }
    return $users_id;
}

/**
 * 获取指定级别内数组下级
 * @param $uid char 要查询下级的用户集合id；如'1,2,3'
 * @param $num char   占位符
 * @param $key char   占位符
 * @param $num char   要查询下级位数
 * @return 查询级别的用户下级
 */
function getChildenArr($uid, $arr = '', $key = '', $num = 1)
{
    if (empty($arr)) {
        static $arr = [];
        static $key;
    } else {
        static $key;
        static $arr = [];
        $arr = [];
        $key = 1;
    }
    $where['pid'] = ['IN', "$uid"];
    $user1 = db('users')->where($where)->select();
    $users_id = '';
    foreach ($user1 as $k => $v) {
        $arr[$key][$k] = $v;
        $users_id .= $v['id'] . ',';
    }
    $key++;
    $users_id = trim($users_id, ',');    //一级下级
    for ($i = 1; $i < $num; $i++) {
        if (!$users_id) {
            break;
        }
        $users_id = getChildenArr($users_id, '', '', $num - 1);
        return $users_id;
    }
    return $arr;
}

/**
 * 获取本月的开始和结束时间戳
 * @return 本月的开始和结束时间戳  ----array
 */
function getMonth()
{
    $return[0] = strtotime(date('Y-m', time()) . '-1 00:00:00');
    $return[1] = strtotime(date('Y-m') . '-' . date('t') . ' 23:59:59');
    return $return;
}

/**
 * 获取今天的开始和结束时间戳
 * @return 本月的开始和结束时间戳  ----array
 */
function getDay()
{
    $return[0] = strtotime(date('Y-m-d') . ' 00:00:00');
    $return[1] = strtotime(date('Y-m-d') . ' 23:59:59');
    return $return;
}

function add_account($uid, $score, $cur_score, $remark, $is_add, $type, $from_uid,$source = '0',$star = '')
{
    $true_type = '1';
    $score_type = 'reg_score';
    switch ($type) {
//    1.注册积分,2游戏积分,3奖励积分，4消费积分，5电子积分，6获赠积分,7购物积分
        case '注册积分':
            $true_type = '1';
            break;
        case '游戏积分':
            $true_type = '2';
            $score_type = 'game_score';
            break;
        case '奖励积分':
            $true_type = '3';
            $score_type = 'prize_score';
            break;
        case '消费积分':
            $true_type = '4';
            $score_type = 'con_score';
            break;
        case '电子积分':
            $true_type = '5';
            $score_type = 'ele_score';
            break;
        case '获赠积分':
            $true_type = '6';
            $score_type = 'receive_score';
            break;
        case '购物积分':
            $true_type = '7';
            $score_type = 'pay_score';
            break;
    }
    $true_is_add = $is_add == '收入' ? '1' : '2';
    $insert = [
        'uid' => $uid,
        'score' => $score,
        'cur_score' => $cur_score,
        'remark' => $remark,
        'class' => 1,
        'is_add' => $true_is_add,
        'type' => $true_type,
        'source' => $source,
        'from_uid' => $from_uid,
        'created_at' => time()
    ];
    \think\Db::startTrans();
    $res = db('account')->insert($insert);
    $user_update[$score_type] = $cur_score;
    $user_update['updated_at'] = time();
    if ($star) {
        $user_update['star'] = $star;
    }
    $res1 = db('users')->where(['id' => $uid])->update($user_update);
    if ($res && $res1) {
        \think\Db::commit();
        return 1;
    } else {
        \think\Db::rollback();
    }
}

//获取用户所有上级,包含自己
//$id    要查询的id
//$arr   占位符
//$class 查询几级上级
function get_parent($id, $arr = '', $class = '')
{
    if (empty($arr)) {
        static $arr = [];
    } else {
        static $arr = [];
        $arr = [];
    }
    if (!in_array($id, $arr)) {
        array_push($arr, $id);
    }
    $pid = db('users')->where(['id' => $id])->value('pid');
    if (!empty($pid) && $pid > 0) {
        if ($class) {
            $class--;
            if ($class > 0) {
                get_parent($pid, '', $class);
            }
        } else {
            get_parent($pid);
        }

    }
    return $arr;
}



/**
 * 过滤数组获取需要的数据
 * @param array       $data
 * @param array|str   $where
 * @return array
 */
function getNeedData($data,$where){
    if(empty($data) || empty($where) || !is_array($data)) return false;
    $array=[];
    if(!is_array($where)) $where[]= $where;

    //过滤数据
    foreach ($data as $key => $value) {
      if(in_array($key,$where)){
         if(!empty($value) || $value===0) $array[$key] = $value;
      }
    }
    return $array;
}


/**
 * 过滤数组获取需要的单组数据
 * @param array       $data
 * @param array|str   $where
 * @return array
 */
function getNeedSingleData($data,$str){
    if(empty($data) || empty($str) || !is_array($data)) return false;
    $array=[];
    //过滤数据
    foreach ($data as $key => $value) {
        foreach($value  as $k=>$v){
             if($k==$str) $array[] = $v;
        }  
    }
    return $array;
}

#达到150w 不再享受动态收益 基金币 奖励 消费
function restriction($uid)
{
    $where = [];
    $where["uid"] = $uid;
    $res1 = db("reward_detailed")->where($where)->sum("con_score");
    $res2 = db("reward_detailed")->where($where)->sum("prize_score");
    $res3 = db("reward_detailed")->where($where)->sum("fund_gold");

    $sell = $res1+$res2+$res3;
    if($sell > 1500000){
        return true;
    }
    return false;
}


