<?php

namespace app\home\controller;

use think\Controller;
use Service\Rerformance;
use Service\Useractivate;
use app\backend\model\UserModel;
use Service\Stock;
use Service\Nineservice;

// 注册  发送验证码
class Register extends Controller
{
    const Amount = ['0' => 0, '1' => 100, "2" => 300, "3" => 500, "4" => 1000, "5" => 3000, "6" => 5000, "7" => 10000];
    const CLASSS = ['0' => '免费会员', '1' => "普卡", "2" => "银卡", "3" => "金卡", "4" => "白金卡", "5" => "黑金卡", "6" => "钻卡", "7" => "蓝钻"];
    /* 发送验证码
     *  @param type phone
     *  @return true or false
     */
    public $code;
    public $region;

    public function send()
    {
        #获取传过来的type  0=>注册 1=>忘记密码 2=>修改交易密码 3=>激活用户
        $type = input('type') ?? 0;
        #获取手机号
        $phone = request()->param('phone');
        // 判断手机号是否为空
        if ($phone == '') {
            return ajax('-2', '请输入手机号');
        }
        // 正则匹配手机号
        $preg = '/^[1][3,4,5,6,7,8,9][0-9]{9}$/';
        if (!preg_match($preg, $phone)) {
            return ajax('-5', '不是正确的手机号');
        }
        #根据手机号在库里查询
        $res = db('users')->where('phone', $phone)->find();
        // if(($type==0) && $res){
        //     if($res['step']>=4 && $res['code']){
        //         return ajax('-6','此手机号已被注册');
        //     }
        // }else
        if (($type == 1) && !$res) {
            return ajax('-7', '此手机号暂未注册');
        }
        // 今天的开始时间
        $start = strtotime('today');
        // 结束时间
        $end = $start + 60 * 60 * 24;
        // 条件 一天发一回
        $where = " phone = $phone and time <= $end and time >= $start and type = $type ";
        // 统计数量
        $num = db('sms')->where($where)->count();
        // 进行判断
        if ($num >= 1000) {
            return ajax('-1', '超过发送次数');
        }
        // 发送短信
        $send = NewSms($phone);

        // 是否发送成功
        if ($send['code'] > 0) {
            // 发送成功 存库里
            $arr = [
                'phone' => $phone,                 #电话号
                'time' => time(),                 #发送时间
                'code' => $send['code'],          #验证码
                'type' => $type,                  #类型  0=>注册  1=>忘记密码
                'status' => 0,                      #是否已验证  0=>未验证 1=>已验证
            ];
            #如过类型为1  说明是忘记密码 需要此用户id
            if ($type == 1) {
                $uid = db('users')->where('phone', $phone)->value('id');
                $arr['uid'] = $uid;
            } elseif ($type == 2) {
                $arr['uid'] = session('home.user')['id'];
            }

            db('sms')->insert($arr);
            return ajax('1', '发送成功');
        } else {
            return ajax('-3', '发送失败');
        }
    }

    /* 注册 第一步
     * @param (code phone auth_code password repassword two_password re_two_password) OR + leader_phone
     * @return
     */
    public function doreg()
    {
        # 获取传过来的数据
        $info = input('post.');
        # 对数据进行判断 不能为空
        $result = $this->validate($info, 'RegValidate');
        if (true !== $result) {
            return ajax('-1', $result);
        }
        #判断是否有领导人电话
        if (isset($info['leader_phone'])) {
            if (empty($info['leader_phone'])) {
                return ajax('-7', '请输入您的领导人电话');
            }
        }
        $arr = [];
        # 根据手机号查询
        $phone = $info['phone'];#TODO 原来以手机号判断 现在以code
        $code = $info['code'];
        # 正则匹配手机号
        $preg = '/^[1][3,4,5,6,7,8,9][0-9]{9}$/';
        if (!preg_match($preg, $phone)) {
            return ajax('-5', '请输入正确的手机号');
        }
        #可注册的等级  如果在平台注册 只能注册免费会员   商城购物过来的 根据购买的报单产品分等级
        $regist = [];
        #手机号是否已经注册
        // $tel = db('users')->where(['phone'=>$phone])->find();

        #手机号没有注册的时候 判断此编号是否注册
        $res = db('users')->where(['code' => $info['code']])->find();
        if ($res) {
            if ($res['step'] == '4') {
                return ajax('-6', '该账号已被注册');
            } else {
                $arr['id'] = $res['id'];
            }
        }
        # 今天的开始时间
        $start = strtotime('today');
        # 结束时间
        $end = $start + 60 * 60 * 24;
        # 查询条件
        $where = "phone = $phone and time <= $end and time >= $start and type = 0";
        # 查询出最近的一条数据
        $codes = db('sms')->where($where)->order('time', 'desc')->find();
        #判断是否发送的有短信
        if (!$codes) {
            return ajax('-4', '您输入的验证码错误');
        }
        if ($codes['status'] != 0) {
            return ajax('您输入的验证码错误');
        }
        #10分钟内有效
        $expiry = $codes['time'] + 60 * 10;
        if (time() > $expiry) {
            db('sms')->where('id', $codes['id'])->update(['status' => '2']);
            return ajax('-2', '您输入的验证码错误');
        }

        #对验证码判断
        if (($info['auth_code'] != $codes['code'])) {
            return ajax('-3', '您输入的验证码错误');
        }
        // if($info['auth_code'] != db('sms')->where('id',$codes['id'])->value("code")){
        //     return ajax('-1','您输入的验证码错误');
        // }
        #验证成功 改验证码状态
        db('sms')->where('id', $codes['id'])->update(['status' => '1']);
        $arr['password'] = md5($info['password']);                   //密码
        $arr['phone'] = $info['phone'];
        $arr['code'] = $info['code'];
        $arr['password'] = md5($info['password']);
        $arr['two_password'] = md5($info['two_password']);
        session('register', $arr);
        if (isset($arr['id'])) {
            return ajax('1', '填写成功,下一步', ['id' => $arr['id']]);
            exit;
        }
        return ajax('1', '填写成功,下一步', ['id' => 'first']);

    }

    #获取等级
    public function Accesslevel()
    {
        $id = input('id');
        $regist = [];
        if (!is_numeric($id) || $id == '') {
            $regist = ['免费会员'];
            return ajax('1', '获取成功', $regist);
            exit;
        }
        $user = db('users')->where('id', $id)->find();
        if (!$user) {
            $regist = ['免费会员'];
            return ajax('1', '获取成功', $regist);
            exit;
        }
        foreach ($this::CLASSS as $key => $val) {
            if ($key <= $user['shopping_number']) {
                $regist[] = $val;
            }
        }
        return ajax('1', '获取成功', $regist);
    }


    # 第二步
    public function dotwostep()
    {
        #获取传过来的数据
        $info = input('post.');
        $id = input('id');
        if ($id == '') {
            session('register', null);
            return ajax('-10', '请认真完成步骤');
        }
        if ($info['ncode'] == session('register')['code']) {
            return ajax('-2', '接点人不能为自己');
        }
        if ($info['pcode'] == session('register')['code']) {
            return ajax('-2', '推荐人不能为自己');
        }
        #判断是否为子账号 默认为主账号
        $data['cascade'] = input('cascade') != null ? input('cascade') : '1';
        $odd = '';
        $need = '';
        if (is_numeric($id)) {
            if ($id != session('register')['id']) {
                session('register', null);
                return ajax('-10', '请认真完成步骤');
            }
            #拥有的注册积分
            $odd = db('users')->where('id', $id)->value('reg_score');
            #需要的积分
            $need = $this::Amount[$info['class_reg']];
            if ($odd < $need) {
                return ajax('-1', '此手机号注册的用户积分不足,不足以开通此会员');
            }
        }
        #当前步数 如果为主账号的话为状态为2  为子账号的为4
        # return json($data);
        #如果为2 说明是子账号 需要填写主账号
        if ($data['cascade'] == '2') {
            if ($info['cascade_code'] == '') {
                return ajax('-3', '主账号不能为空');
            } else {
                $zzh = db('users')->where('code', $info['cascade_code'])->field('truename,identity,bank_name,bank_branch,bank_user,bank_account,weixin,zhifubao')->find();
                if (empty($zzh)) {
                    return ajax('-3', '主账号不存在');
                }
                $data['truename'] = $zzh['truename'];
                $data['identity'] = $zzh['identity'];
                $data['bank_name'] = $zzh['bank_name'];
                $data['bank_branch'] = $zzh['bank_branch'];
                $data['bank_user'] = $zzh['bank_user'];
                $data['bank_account'] = $zzh['bank_account'];
                $data['weixin'] = $zzh['weixin'] != '' ? $zzh['weixin'] : '';
                $data['zhifubao'] = $zzh['zhifubao'] != '' ? $zzh['zhifubao'] : '';
                $data['cascade_code'] = $info['cascade_code'];
                $data['step'] = '4';
                $data['nickname'] = 'BOF_' . str_rand(3) . str_rand(3) . rand(0, 9); #生成昵称
            }
        }
        # 判断值不能为空
        $result = $this->validate($info, 'TwostepValidate');
        if (true !== $result) {
            return ajax('-1', $result);
        }
        #判断是否有此报单中心
        if (!db('form_core')->where('ucode', input('bd_id'))->find()) {
            return ajax('-2', '没有此报单中心');
        }
        # 查出推荐人id 推荐人集合  接点人集合
        $pid = db('users')->where('code', $info['pcode'])->field(['id', 'all_pid'])->find();
        if (!$pid) {
            return ajax('-5', '此推荐人账号不存在');
        }


        #推荐人集合
        if ($pid['all_pid'] == '') {
            $all_pid = '0,' . $pid['id'] . ',';
        } else {
            $all_pid = substr_replace($pid['all_pid'], ',' . $pid['id'] . ',', -1);
        }
        $nid = db('users')->where('code', $info['ncode'])->field(['id', 'all_nid', 'region'])->find();
        if (!$nid) {
            return ajax('-1', '此接点人不存在');
        }

        $nid_str = session('register')["code"];
        if ($nid["id"] == $nid_str) {
            return ajax('-1', '接点人不能为自己');
        }
        $trueNid = $this->wz($nid['id'], $info["region"]);
        if (!$trueNid) {
            return ajax('-1', '这个位置已经有人了!');
        }
        $all_nid = (db("users")->where(["id" => $trueNid])->value("all_nid")) . $trueNid . ",";
        #要修改的数据
        $data['bd_id'] = $info['bd_id'];             #报单中心id
        $data['region'] = $info['region'];            #区间
        $data['class_reg'] = $info['class_reg'];         #注册时等级
        $data['class'] = $info['class_reg'];         #等级
        $data['pid'] = $pid['id'];                 #推荐人id
        $data['all_pid'] = $all_pid;                   #推荐人集合
        $data['all_nid'] = $all_nid;                   #接点人集合
        $data['nid'] = $trueNid;                   #推荐人集合
        $data['pattern'] = '0';                        #模式  手动 or 半托管
        // $data['pattern']	=	   $info['pattern'];           #模式  手动 or 半托管
        $data['nids'] = $nid['id'];
        $arr = session('register');
        $arr = array_merge($arr, $data);
        if ($data['cascade'] == '1') {
            session('register', $arr);
            return ajax('1', '填写成功,下一步', ['id' => 2]);
        } else {
            $arrss = [];
            if (isset($arr['id'])) {
                foreach ($arr as $key => $val) {
                    if ($key != 'id') {
                        $arrss[$key] = $val;
                    }
                }
                $arrss['updated_at'] = time();
                db('users')->where('id', $arr['id'])->update($arrss);
                $this->activation($need, $odd, $arrs['phone'], $id, $arrs['class']);
                return ajax('1', '注册成功');
            } else {
                $arr['created_at'] = time();
                db('users')->insert($arr);
                session('register', null);
                return ajax('1', '注册成功');
            }
        }
    }

    #通过接点人code 和 region 确定该用户的位置
    public function wz($nid, $region)
    {
        #查询该接点人的信息
        // $ob = db("users")->where(["id"=>$nid])->field(["id"])->find();
        $ob = db("users")->where(["id" => $nid])->value('id');
        #判断该用户左右区有没有人
        $obs = db("users")->where(["nid" => $ob, "region" => $region])->field(["id", "region"])->find();
        #如果有就执行递归
        if (!empty($obs)) {
            return false;
            // return $this->wz($obs["id"],$obs["region"]);
        }
        return $nid;
    }


    // 第三步
    public function dothreestep()
    {
        #数据
        $info = input('post.');
        #判断值不能为空
        $result = $this->validate($info, 'ThreestepValidate');
        if (true !== $result) {
            return ajax('-1', $result);
        }
        #银行卡号验证
        if (!BankID($info['bank_account'])) {
            return ajax('-2', '请输入正确的银行卡号');
        }
        #身份证号正则
        $preg = '/\d{17}[\d|x]|\d{15}/';
        if (!preg_match($preg, $info['identity'])) {
            return ajax('-3', '请输入正确的身份证号');
        }

        $config = db("config")->where("name", "identity")->value("value");
        $where["class"] = ["IN", [6, 7]];
        $where["identity"] = $info['identity'];
        $count = db("users")->where($where)->count();
        if ($count >= $config) {
            return ajax('-3', '同一身份证下最多注册' . $config . '个钻卡/蓝钻');
        }

        #删除数组中的id
        foreach ($info as $key => $val) {
            if ($key == 'id') {
                unset($info[$key]);
            }
        }
        #对数组进行整合
        #存库
        $arr = session('register');
        $arr = array_merge($arr, $info);
        session('register', $arr);
        return ajax('1', '填写成功,下一步', ['id' => 3]);

    }

    #第四步 上传视频
    public function dofourstep()
    {
        if (input('post.videourl') == '') {
            return ajax('-1', '请上传所拍摄的视频,如已上传请等待上传成功');
        }
        #接收传过来的视频路径

        // 删除视频地址前缀
        $videourl = input('post.videourl');
        $videourl = explode('/', trim($videourl, 'http://'));
        $videourl = '/' . implode('/', array_splice($videourl, 1));
        $info = [
            'videourl' => $videourl,
            'created_at' => time(),
            'step' => 4,
            'nickname' => 'BOF_' . str_rand(3) . str_rand(3) . rand(0, 9),  #生成昵称

        ];
        #重新检测接点人是否已存在 

        $model = new UserModel();
        if ($model->where(['code' => session('register')["code"]])->count() > 0) return ajax('1-', '该编号已存在');


        $trueNid = $model->where(['nid' => session('register')["nids"], 'region' => session('register')["region"]])->find();
        if (!empty($trueNid)) return ajax('-1', '该节点人已存在');


        $all_nid = (db("users")->where(["id" => session('register')["nids"]])->value("all_nid")) . session('register')["nids"] . ",";
        #获取nid all_nid        
        $info['nid'] = session('register')["nids"];                  #接点人id
        $info['all_nid'] = $all_nid;                                     #接点人集合
        $arr = session('register');
        #合并数组
        $id = '';
        $arr = array_merge($arr, $info);
        if (isset($arr['id'])) {
            $id = $arr['id'];
        }
        $arrs = [];
        #遍历数组 删除不需要的数据
        foreach ($arr as $k => $v) {
            if (($k != 'need_code') && ($k != 'id')) {
                $arrs[$k] = $v;
            }
        }

        $register = db("config")->where(["name" => "register"])->value("value");
        $arrs["sell_status"] = $register;
        
        #存库  
        if ($id != '') {
            #拥有的注册积分
            $odd = db('users')->where('id', $id)->value('reg_score');
            #需要的积分
            $need = $this::Amount[$arrs['class_reg']];
            if ($odd < $need) {
                return ajax('-1', '此手机号注册的用户积分不足,不足以开通当前会员');
            }
            if ($register == 1) {
                $arrs["status"] = 1;
            } else {
                $arrs["status"] = 3;
            }
            if (db('users')->where('id', $id)->update($arrs)) {
                if ($register == 1) {
                    $this->activation($need, $odd, $arrs['phone'], $id, $arrs['class']);
                    return ajax('1', '审核成功，请直接登录');
                } else {
                    return ajax('1', '注册成功,请等待管理员审核!');
                }
            } else {
                return ajax('-1', '注册失败');
            }
        } else {
            if (db('users')->insert($arrs)) {
                if ($register == 1) {
                    return ajax('1', '审核成功，请直接登录');
                }else{
                    return ajax('1', '注册成功,请等待管理员审核!');
                }
            } else {
                return ajax('-1', '注册失败');
            }
        }
    }

    #会员激活
    # need  需要的积分  odd  现在的积分 phone  用户手机号 id 用户id
    public function activation($need, $odd, $phone, $id, $class)
    {
        #扣除后的积分
        $surplus = $odd - $need;
        db('users')->where('id', $id)->update(['reg_score' => $surplus, 'updated_at' => time()]);
        #存到account表
        $account = [
            'score' => -$need,                          //积分
            'cur_score' => $surplus,                        //当前的积分
            'remark' => '激活用户',                      //备注
            'class' => '1',                             //积分
            'is_add' => '2',                             //支出
            'type' => '1',                             //注册积分
            'uid' => $id,                           //来自用户的id
            'source' => '11',
            'created_at' => time()
        ];
        db('account')->insert($account);
        if (!db("disc")->count()) {
            $market_price = db("config")->where(["name" => "current_price"])->value("value");
        } else {
            $market_price = db("disc")->order("id", "desc")->find()["market_price"];
        }
        $Useractivate = new Useractivate($id, $market_price, 0, '');
        $Useractivate->loadUser();
        $Rerformance = new Rerformance($id, $class);
        $Rerformance->loadUser();
        $Nineservice = new Nineservice($id);
        $Nineservice->loadUser();
        // 发送短信
        // $send = NewSms($phone);
        // // 发送成功 存库里
        // $arr = [
        //     'phone' =>  $phone,                 #电话号
        //     'time'  =>  time(),                 #发送时间
        //     'code'  =>  $send['code'],          #验证码
        //     'type'  =>  '3',                    #类型 激活用户
        //     'uid'   =>  input('id'),        #用户id
        //     'status'=>  '1',
        // ];
        // db('sms')->insert($arr);
        // return ajax('1','激活成功');
    }

    #忘记密码
    public function get_password()
    {
        # 获取手机号 验证码 密码 重复密码
        $phone = input('phone');
        $ver_code = input('ver_code');
        $password = input('password');
        $repassword = input('repassword');
        #手机号不能为空
        if ($phone == '') {
            return ajax('-1', '请输入手机号');
        }
        #密码需要一致
        if ($password != $repassword) {
            return ajax('-2', '密码不一致');
        }
        // 今天的开始时间
        $start = strtotime('today');
        // 结束时间
        $end = $start + 60 * 60 * 24;
        #查询条件
        $where = " time >= $start and time <= $end and type = '1' and phone = $phone and status = 0 ";
        #查出发送的验证码
        $code = db('sms')->where($where)->order('time', 'desc')->find();
        #是否发送过验证码
        if (!$code) {
            return ajax('-3', '此手机号暂未发送短信');
        }
        //10分钟内有效
        $expiry = $code['time'] + 60 * 10;
        if (time() > $expiry) {
            db('sms')->where('id', $code['id'])->update(['status' => '2']);
            return ajax('-2', '验证码已过期');
        }
        #验证码是否正确
        if ($ver_code != $code['code']) {
            return ajax('-4', '短信验证码错误');
        }
        #验证码正确  改变验证码状态
        db('sms')->where('id', $code['id'])->update(['status' => 1]);
        #要修改的数据
        $data = [
            'password' => md5($password),
            'updated_at' => time(),
        ];
        #判断
        if (db('users')->where('phone', $phone)->update($data)) {
            return ajax('1', '密码修改成功');
        } else {
            return ajax('-5', '密码修改失败');
        }
    }
}
















