<?php
namespace app\home\controller;

use think\Controller;
use wechats\Wechat as Wechats;
use filesh\File;
use codes\Qrcode;
use org\Verify;

class Login extends Controller{
    #登录
    public function login(){
        //需要手机号phone，密码password
        $code = input('code');
        $password = input('password');
        $ver_code = input('ver_code');
        #登陆信息进行判断
        if(!$code || !$password || !$ver_code){
            return ajax('-1','登陆信息不完善!');
        }

        #验证码判断
        $verify = new Verify();
        if (!$verify->check($ver_code)) {
           return ajax('-4','验证码错误','');
        }
        $user = db('users')->where(['code'=>$code])->find();
        #账户和密码判断
        if(!$user || (md5($password) != $user['password'])){
            return ajax('-1','账号或密码错误');
        }
        #对用户状态进行判断
        if($user['status'] == 2){
            return ajax('-1','您已被系统拉黑，请联系客服！');
        }elseif($user['status']==3){
            if($user['class']!=0){
                return ajax('-1','您的账号暂未激活,请到商城购物');
            }
        }
        if($user['islock'] == 2){
            return ajax('-1','您的账号已被锁定');
        }
        session('home.user',$user);
        // if(is_weixin()){
        //     $this->wechat_login($user['id']);
        // }
        return ajax('1','登录成功',$user['id']);
    }


    // 验证码
    public function checkVerify(){
        $verify = new Verify();
        $verify->imageH = 45;
        $verify->imageW = 120;
        $verify->useZh = false;
        $verify->length = 4;
        $verify->useNoise = false;
        $verify->fontSize = 17;
        return $verify->entry();
    }

    public function mytest(){
        return '<img src="'.FRDOMAIN.'/home/login/checkVerify"';
    }

    #我的二维码
    public function code(){
        # 获取用户信息
        $uid = session('home.user')['id'];
        $userinfo = db('users')->where(['id'=>$uid])->find();
        # 获取jsapi
        $jsapi_config = Wechats::get_jsapi_config(['onMenuShareTimeline','onMenuShareAppMessage'],false,false);
        #渲染
        return view('html/code',['users'=>$userinfo,'jsapi_config'=>$jsapi_config]);

    }
    #获取系统二维码
    public function get_code(){
        $path = ROOT_PATH.'public/userimg/qrcode'.$_GET['id'].'.png';
        Qrcode::png('http://'.$_SERVER['HTTP_HOST'].'/home/login/toweb?pid='.$_GET['id'],$path);
    }
    # web端和微信端的二维码兼容 关注公众号的二维码
    public function toweb(){
        // dump(10);exit;
        if(is_weixin()){
            # 显示微信二维码
            # 判断是否已经生成过二维码
            if($user = db('users') -> where(['id'=>$_GET['pid']]) ->where(['qrcode'=>['neq','']]) -> find()){
                $qrcode = $user['qrcode'];
            }else{
                # 获取带参数二维码
                $qrcode = Wechats::get_Qrcode($_GET['pid']);
                # 获取Ticked
                $qrcode = substr($qrcode,51);
                # 下载二维码
                File::_download('https://mp.weixin.qq.com/cgi-bin/showqrcode?ticket='.$qrcode,ROOT_PATH.'public/qrcode/',$qrcode.'.jpg');
                # 修改用户Ticked
                db('users') -> where(['id'=>$_GET['pid']]) -> update(['qrcode'=>$qrcode]);
            }

            # 分配ticked到模板引擎
            $this -> assign('ticket',$qrcode);
            # 渲染微信二维码页面
            return $this->fetch();
        }else{
            # 跳转到用户中心 用Base 记录上级id
            header('location:'.FRDOMAIN.'/register.html?pid='.$_GET['pid']);

        }

    }
    #微信登录
     #微信登录
    public function wechat_login($uid = ''){
        #判断是否是绑定微信
        if(input('uid')){ #手机用户绑定微信的
            $this->wechat_bind(input('uid'));
        }else{ #微信登录商城的
            $this->wechat_shop();
        }

    }
    public function wechat_shop(){
        # 获取用户信息
        // dump(config('Wechat')['callback']);exit;
        $userinfo = Wechats::get_user_info(config('Wechat')['callback']);
        // dump($userinfo);exit;
        # 判断用户是否已经注册过了
        if($user = db('users') -> where(['openid'=>$userinfo['openid']]) -> find()){
            if($user['status'] == 2){
                exit("<script>alert('您已被系统拉黑，请联系客服');window.location.href='http://szcxdzsw.com/login.html'</script>");
            }
            # 存储用户信息
            session('home.user',$user);
            if(!empty($user['phone'])){
                # 跳转到首页
                header('location:'.FRDOMAIN.'/index.html?id='.$user['id']);exit;
            }else{
                 header('location:'.FRDOMAIN.'/register.html?id='.$user['id']);exit;
            }
        }else{
            # 用户数组
            $data = [];
            # 上级会员
            $data['pid'] = 0;
            # 用户唯一标识
            $data['openid'] = $userinfo['openid'];
            # 性别 1=男 2=女性 0=未设置
            $data['sex'] = $userinfo['sex'];
            # 用户昵称
            $data['nickname'] = $userinfo['nickname'];
            # 头像
            $data['headimgurl'] = $userinfo['headimgurl'];
             # 创建时间
            $data['created_at'] = time();
            # 最后更新时间
            $data['updated_at'] = $data['created_at'];
            # 插入用户数据
            $id = db('users') -> insertGetId($data);
            # 更新用户信息
            if($user = db('users') -> where(['id'=>$id]) -> find()){
                $_SESSION['home']['user']   =$user;
                $_SESSION['home_user_id']   =$id;
            }
            # 跳转到首页
            header('location:'.FRDOMAIN.'/index.html?id='.$id);exit;
        }
    }
    public function wechat_bind($uid){
        # 获取用户信息
        $userinfo = Wechats::get_user_info('http://'.$_SERVER['HTTP_HOST'].'/home/login/wechat_login.html?uid='.$uid);
        $user2 = db('users') -> where(['id'=>input('uid')]) -> find();
        # 判断用户是否已经注册过了
        if($user = db('users') -> where(['openid'=>$userinfo['openid']]) -> find()){
            #nickname openid sex headimgurl
            if(!empty($user['phone']) && $user['phone'] != $user2['phone']){
                exit("<script>alert('此微信号已绑定过手机号码');window.location.href='".FRDOMAIN."/index.html'</script>");
            }
            if(!empty($user2['openid'])){
                exit("<script>alert('微信已绑定成功');window.location.href='".FRDOMAIN."/index.html'</script>");
            }
            $update['nickname']     = $user['nickname'];
            $update['openid']       = $user['openid'];
            $update['sex']          = $user['sex'];
            $update['headimgurl']   = $user['headimgurl'];
            $update['updated_at']   = date('YmdHis');
            $res1 = db('users') -> where(['id'=>$user2['id']]) -> update($update);
            $res2 = db('users') -> where(['id'=>$user['id']]) ->delete();
            $user = db('users') -> where(['id'=>$user2['id']]) -> find();
            # 存储用户信息
            session('home.user',$user);
            $_SESSION['home_user_id']   =$user['id'];
            # 跳转到首页
            header('location:'.FRDOMAIN.'/index.html');exit;
        }else{
            if(empty($user2['openid'])){
                # 用户数组
                $data = [];
                # 用户唯一标识
                $data['openid'] = $userinfo['openid'];
                # 性别 1=男 2=女性 0=未设置
                $data['sex'] = $userinfo['sex'];
                # 用户昵称
                $data['nickname'] = $userinfo['nickname'];
                # 头像
                $data['headimgurl'] = $userinfo['headimgurl'];
                # 最后更新时间
                $data['updated_at'] = time();
                # 绑定用户数据
                $id = db('users') -> where(['id'=>input('uid')]) -> update($data);
              }
            # 更新用户信息
            if($user = db('users') -> where(['id'=>$user2['id']]) -> find()){
                session('home.user',$user);
                $_SESSION['home_user_id']   =$id;
            }
            # 跳转到首页
            header('location:'.FRDOMAIN.'/index.html');exit;
        }
    }

    #绑定微信二维码
    public function bindwx(){
        $id = session('home.user')['id'];
        if(!$id){
           return json(['status'=>0,'message'=>'未登录']);
        }
        $path = ROOT_PATH.'public/userimg/qrcode/weixin'.$id.'.png';
        Qrcode::png('http://'.$_SERVER['HTTP_HOST'].'/home/login/wechat_login?uid='.$id,$path);
        return json(['status'=>1,'message'=>'查询成功','data'=>DOMIAN.'/userimg/qrcode/weixin'.$id.'.png']);
    }
    #退出登录
    public function logout()
    {
        session(null);
        return ajax(1,'成功');
    }


    // public function adddddd()
    // {
    //     $orderId = db('static_order')->column('order_id');
    //     if (empty($orderId)) {
    //         $asd = [];
    //         $data = db('order')->where('status','>=',2)->field('id,uid,price')->select();
    //         foreach ($data as $k => $v) {
    //             if ($v['price'] == 398) {
    //                 $real_money = 398 * 2;
    //             }else if ($v['price'] == 698) {
    //                 $real_money = 698 * 3;

    //             }else if ($v['price'] == 998) {
    //                 $real_money = 998 * 5;
    //             }
    //             $asd[] = $this->dataAd($v['uid'],$v['id'],$v['price'],$real_money);
    //         }
    //     }else{
    //         $asd = [];
    //         $data = db('order')->where('status','>=',2)->whereNotIn('id',$orderId)->field('id,uid,price')->select();
    //         foreach ($data as $k => $v) {
    //             if ($v['price'] == 398) {
    //                 $real_money = 398 * 2;
    //             }else if ($v['price'] == 698) {
    //                 $real_money = 698 * 3;

    //             }else if ($v['price'] == 998) {
    //                 $real_money = 998 * 5;
    //             }
    //             $asd[] = $this->dataAd($v['uid'],$v['id'],$v['price'],$real_money);
    //         }
    //     }

    //     db('static_order')->insertAll($asd);
    // }


    // public function dataAd($uid,$orderId,$money,$real_money)
    // {
    //     return [
    //         'uid' =>$uid,
    //         'order_id' =>$orderId,
    //         'money'  =>$money,
    //         'real_money' =>$real_money,
    //         'created_at' =>date('Y-m-d H:i:s'),
    //         'updated_at' =>date('Y-m-d H:i:s')
    //     ];
    // }

}