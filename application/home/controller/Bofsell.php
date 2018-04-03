<?php

namespace app\home\controller;

use Service\Nineservice;
use Service\Stock;
use Service\Useractivate;
use Service\Rerformance;
use think\Controller;
use think\Request;

class Bofsell extends Controller
{//TODO 后期继承base
    const USER = "users";
    const CONFIG = "config";
    const DISC = "disc";
    const DEAL = "bof_deal";
    const ACCOUNT = "account";
    const RANK = "bof_rank";
    const ORIGINAL = "bof_original";
    const ISSUE = "bof_issue";
    #用户id
    private $uid;

    public function __construct(Request $request = null)
    {
        parent::__construct($request);
        $this->uid = session('home.user')["id"];
    }

    public function index()
    {
        $day = $this->showDisc();
        return ajax(0, "获取成功", $day);
    }

    #获取大盘的方法
    public function showDisc()
    {
        #获取今天0点的时间戳
        $start = strtotime(date('Ymd'));
        #获取今天24点的时间戳
        $end = strtotime(date('Ymd')) + 86400;

        #今天的大盘数据
        $day["ten"] = $this->timeDay($start, $end);
        #昨天的大盘数据1
        $day["nine"] = $this->timeDay($start - 86400, $start);
        #昨天的大盘数据2
        $day["eight"] = $this->timeDay($start - 86400 * 2, $start - 86400);
        #昨天的大盘数据3
        $day["seven"] = $this->timeDay($start - 86400 * 3, $start - 86400 * 2);
        #昨天的大盘数据4
        $day["six"] = $this->timeDay($start - 86400 * 4, $start - 86400 * 3);
        #昨天的大盘数据5
        $day["five"] = $this->timeDay($start - 86400 * 5, $start - 86400 * 4);
        #昨天的大盘数据6
        $day["four"] = $this->timeDay($start - 86400 * 6, $start - 86400 * 5);
        #昨天的大盘数据7
        $day["three"] = $this->timeDay($start - 86400 * 7, $start - 86400 * 6);
        #昨天的大盘数据8
        $day["two"] = $this->timeDay($start - 86400 * 8, $start - 86400 * 6);
        #昨天的大盘数据9
        $day["one"] = $this->timeDay($start - 86400 * 9, $start - 86400 * 8);
        
        return $day;
    }

    #获取某天的大盘数据
    public function timeDay($start, $end)
    {
        $day = [];

        $ob = db(self::DISC)->where("created_at", "between", [$start, $end])->order("created_at", "desc")->field("market_price")->select();
        if (count($ob) >= 1) {
            $day["price"] = $ob[0]["market_price"];
        } else if (empty($ob)) {
            $day["price"] = $this->showTime($start-86400,$end-86400);
        }
        $day["time"] = date("Y-m-d", $start);
        $day["price"] = !$day["price"] ? 0.2 : $day["price"];
        return $day;
    }

    public function showTime($start,$end,$num=0){
        if ($num == 11) {
            return '0.200';
        }
        $ob = db(self::DISC)->where("created_at","between",[$start,$end])->order("created_at","desc")->value("market_price");
        if(empty($ob)){
            $num++;
            return $this->showTime($start-86400,$end-86400,$num);
        }
        return $ob;
    }

    #获取bof 卖出前信息
    public function showbalance()
    {
        $userBalance = db(self::USER)->where(["id" => $this->uid])->field(["balance", "balance_return"])->find();
        #如果发行记录里面为空  则使用初始的0.2
        if (!db("disc")->count()) {
            $configPrice = db(self::CONFIG)->where(["name" => "current_price"])->value("value");
        } else {
            $configPrice = db(self::DISC)->order("id", "desc")->find()["market_price"];//TODO sql_disc大盘表
        }


        $data["bof_all"] = round($userBalance["balance"] + $userBalance["balance_return"],0);#总量
        $data["bof_more"] = round($userBalance["balance"],0);#余量
        $data["bof_return"] = round($userBalance["balance_return"],0);#回购量

        $data["current_price"] = $configPrice;#当前系数

        return ajax(0, "获取成功!", $data);
    }

    #BOF卖出->预计金额 失去焦点事件 bof
    public function Estimate()
    {
        $configPrice = db(self::CONFIG)->where(["name" => "current_price"])->value("value");
        return ajax(0, "获取成功!", input("money") * $configPrice);
    }

    #BOF卖出
    public function sellbof(Request $request)
    {
        if (!$request->post()) {
            return json(["status" => 100, "message" => "请稍后重试!"]);
        }
        if (!db(self::DISC)->count()) {
            $bofPrice = db(self::CONFIG)->where(["name" => "current_price"])->value("value");
        } else {
            $bofPrice = db(self::DISC)->order("id", "desc")->find()["market_price"];//TODO sql_disc大盘表
        }

        if ($bofPrice >= 0.34) {
            return json(["status" => 100, "message" => "当前不可销售!"]);
        }
        $bofOne = db(self::DEAL)->where(["uid" => $this->uid,"type"=>2])->order("created_at", "desc")
            ->value("sell_price");

        $userOne = db(self::USER)->where(["id" => $this->uid])->field(["bof_num","pay_score", "class", "pattern", "balance", "balance_return", "game_score", "prize_score", "con_score", "bof_all","fund_gold"])->find();

        if ($userOne["bof_num"] >= 8) {
            return json(["status" => 100, "message" => "本轮销售次数已经用完!"]);
        }
        if ($userOne["pattern"] == 2) {
            return json(["status" => 100, "message" => "半托管销售中!"]);
        }
        if($bofOne){
            if($userOne["bof_num"] != 0){
                if (round($bofPrice - $bofOne, 4) < 0.02) {
                    return json(["status" => 100, "message" => "必须高于原系数0.02以上才可以挂售!"]);
                }   
            }
        }
        if (input("num") <= 0) {
            return json(["status" => 100, "message" => "输入金额有误!"]);
        }
        if (input("num") > $userOne["balance"]) {
            return json(["status" => 100, "message" => "账户bof余额不足!"]);
        }
        if ((int)input("num") != input("num")) {
            return json(["status" => 100, "message" => "必须为整数!"]);
        }
        #余量10%
        if ($userOne["balance"] * 0.1 <= input("num")) {
            return json(["status" => 100, "message" => "手动销售最多挂售当前BOF的10%!"]);
        }
        if($userOne["class"] == 0){
            return ajax(100,"免费会员不能出售bof！");
        }

        bcscale(6);
        #存储卖出记录
        $sell_num = bcmul(input("num"),"0.9");#卖出量已经去除平台手续费
        #慈善基金
        $reward_score = bcmul(bcmul($sell_num,"0.05"),$bofPrice);
        #游戏积分
        $game_score = $reward_score;
        #原点回购
        $bof_break = bcmul(bcmul($sell_num, "0.3"),(config("Level")[$userOne["class"]]/100));
        #消费积分
        $nineSix = bcmul(bcmul($sell_num,"0.6"),$bofPrice);
        $con_score = bcmul($nineSix,"0.3");
        #复投积分
        $nineSixSeven = bcmul($nineSix,"0.7");
        #购物积分
        $pay_score = bcmul($nineSixSeven,"0.2");
        #奖励积分
        $prize_score = bcmul($nineSixSeven,"0.7");

        $data["uid"] = $this->uid;
        $data["order_sn"] = orderNumBof();#交易编号
        $data["sell_price"] = $bofPrice;#卖出时的股价 TODO
        $data["sell_num"] = input("num");
        $data["service_price"] = bcmul(bcmul(input("num"),"0.1"),$bofPrice);#手续费
        $account["game_score"] = $data["game_score"] = $game_score;
        $account["reward_score"] = $data["reward_score"] = $reward_score;
        $account["con_score"] = $data["con_score"] = $con_score;
        $account["prize_score"] = $data["prize_score"] = $prize_score;
        $account["pay_score"] = $data["pay_score"] = $pay_score;
        $data["back_num"] = $bof_break;#回购数量
        $data["type"] = 2;
        $data["status"] = 2;
        $data["created_at"] = time();   
        $data["updated_at"] = time();
        $data["coe_num"] = $bofPrice;
        $data["pay_status"] = input("pay_status") ? input("pay_status") : 1;

        $res = db(self::DEAL)->where("uid", $this->uid)->insert($data);

        if ($res) {

            if(input("pay_status") == 1){
                $data1["game_score"] = $userOne["game_score"] += $game_score;
                $data1["fund_gold"] = $userOne["fund_gold"] += $reward_score;//TODO
                $data1["con_score"] = $userOne["con_score"] += $con_score;
                $data1["prize_score"] = $userOne["prize_score"] += $prize_score;
                $data1["pay_score"] = $userOne["pay_score"] += $pay_score;
                $data1["balance"] = $userOne["balance"] -= input("num");
                $data1["bof_num"] = $userOne["bof_num"] += 1;#卖出一次就+1  默认0
                $data1["bof_all"] = $userOne["bof_all"] += $sell_num;#卖出一次就+1  默认0
                $data1["balance_return"] = $userOne["balance_return"] + $bof_break;#balance_return  回购数量
                $data1["pattern"] = 1;
            }else{
                $data1["bof_num"] = $userOne["bof_num"] += 1;#卖出一次就+1  默认0
                $data1["pattern"] = 2;
            }

            $res1 = db(self::USER)->where("id", $this->uid)->update($data1);

            if(input("pay_status") == 1){
                if ($res1) {
                    if(input("pay_status") == 1){
                        if (!$this->accountrecord($account)) {
                            return ajax(100, "请稍后重试!");
                        }
                    }

                    if ($this->bofBreak(input("num"))) {
                        return ajax(0, "手动销售卖出成功");
                    }
                } else {
                    return ajax(100, "请稍后重试!");
                }
            }else{
                    return ajax(0, "半托管销售卖出成功,请等待系统执行!");
            }
        }
    }

    #会员挂出的BOF全部公司收回
    public function bofBreak($num)
    {
        #获取当前股价
        if (!db(self::DISC)->count()) {
            $bofPrice = db(self::CONFIG)->where(["name" => "current_price"])->value("value");
        } else {
            $bofPrice = db(self::DISC)->order("id", "desc")->find()["market_price"];//TODO sql_disc大盘表
        }
        #增加总量

        $addBof = db(self::CONFIG)->where(["name" => "bof_all"])->setInc("value", $num);
        #添加记录
        $ob = db(self::ISSUE)->insert([
            "bof_num" => $num,
            "bof_price" => $bofPrice,
            "bof_residue" => db(self::CONFIG)->where(["name" => "bof_all"])->value("value"),
            "status" => 2,
            "created_at" => time(),
        ]);

        if ($addBof && $ob) {
            return true;
        } else {
            return false;
        }
    }

    public function bofList()
    {
        $num = 10;     //一页数量
        $page = input('page') ?? '1';
        $where = [];
        $and_where = [];

        $start_time = input('start_time');
        $stop_time = input('stop_time');
        $code = input('code');
        $order_sn = input('order_sn');
        $type = input('type');
        $status = input('status');
        $where["uid"] = $this->uid;
        $where["type"] = 1;
        $where["status"] = 2;
        if (!empty($start_time)) {
            $where['created_at'] = ['>=', strtotime($start_time . ' 00:00:00')];
        }
        if (!empty($stop_time)) {
            $and_where['created_at'] = ['<=', strtotime($stop_time . ' 23:59:59')];
        }
        if (!empty($code)) {
            $uid = db(self::USER)->where("code", $code)->value("id");
            if ($uid) {
                $where['uid'] = $uid;
            }
        }
        if (!empty($order_sn)) {
            $where['order_sn'] = $order_sn;
        }
        if (!empty($type)) {
            $where['type'] = $type == 1 ? 2 : 1;
        }
        if (!empty($status)) {
            $where['status'] = $status;
        }

        $return['all_num'] = db(self::DEAL)->where($where)->where($and_where)->count();

        $return['all_page'] = ceil($return['all_num'] / $num);

        if ($page > $return['all_page']) {
            $page = $return['all_page'];
        }
        if ($page < '1') {
            $page = '1';
        }
        $return['page'] = $page;
        //需要的参数
        $need = ['uid', 'order_sn', 'coe_num', 'sell_num', 'service_price', 'back_num', 'prize_score', 'pay_score',
            'reward_score', 'con_score', 'game_score', 'sell_price','created_at', 'updated_at', 'status', 'type'];

        $list = db(self::DEAL)->where($where)->where($and_where)->limit($num * ($page - 1), $num)->field($need)->order('id desc')->select();
        $status = config("bof_deal_status");
        $num = 0;
        foreach ($list as $k => $v) {
            $list[$k]['uid'] = db(self::USER)->where("id", $v["uid"])->value("code");
            $list[$k]['created_at'] = date('Y-m-d H:i:s', $v['created_at']);
            $list[$k]['updated_at'] = empty($v['update_at'])?'暂无':date('Y-m-d H:i:s', $v['updated_at']);
            $list[$k]['status'] = $status[$v['status']];
            $list[$k]['coe_num'] = round($v["sell_price"],3);
            $list[$k]['game_score'] = round($v["game_score"],2);
            $list[$k]['pay_score'] = round($v["pay_score"],2);
            $list[$k]['prize_score'] = round($v["prize_score"],2);
            $list[$k]['reward_score'] = round($v["reward_score"],2);
            $list[$k]['service_price'] = round($v["service_price"],2);
            $list[$k]['con_score'] = round($v["con_score"],2);
            $list[$k]['back_num'] = round($v["back_num"],2);
            $num += $v["service_price"];
        }
        $userOne = db(self::USER)->where(["id"=>$this->uid])->field(["balance_return","prize_score","pay_score","fund_gold","game_score"])->find();
        $list["service_price"] = $num;
        $list["back_num"] = round($userOne["balance_return"],2);
        $list["prize_score"] = round($userOne["prize_score"],2);
        $list["pay_score"] = round($userOne["pay_score"],2);
        $list["reward_score"] = round($userOne["fund_gold"],2);
        $list["game_score"] = round($userOne["game_score"],2);
        $return['list'] = $list;
        return ajax(0, "获取成功", $return);
    }

    public function accountrecord($data)
    {
        $newdata = [];
        $userOne = db(self::USER)->where(["id" => $this->uid])->field(["game_score", "pay_score","balance", "fund_gold", "con_score", "prize_score"])->find();
        $newdata["uid"] = $this->uid;
        $newdata["class"] = 1;
        $newdata["is_add"] = 1;
        $newdata["source"] = 16;

        if (isset($data["game_score"])) {
            $newdata["score"] = $data["game_score"];
            $newdata["cur_score"] = $userOne["game_score"];
            $newdata["remark"] = "游戏积分-BOF手动卖出收益";
            $newdata["created_at"] = time();
            $newdata["is_add"] = 1;
            $newdata["type"] = 2;
            db(self::ACCOUNT)->insert($newdata);
        }
        if (isset($data["reward_score"])) {
            $newdata["score"] = $data["reward_score"];
            $newdata["cur_score"] = $userOne["fund_gold"];
            $newdata["remark"] = "基金币-BOF手动卖出收益";
            $newdata["created_at"] = time();
            $newdata["is_add"] = 1;
            $newdata["type"] = 9;
            db(self::ACCOUNT)->insert($newdata);
        }
        if (isset($data["con_score"])) {
            $newdata["score"] = $data["con_score"];
            $newdata["cur_score"] = $userOne["con_score"];
            $newdata["remark"] = "消费积分-BOF手动卖出收益";
            $newdata["created_at"] = time();
            $newdata["is_add"] = 1;
            $newdata["type"] = 4;
            db(self::ACCOUNT)->insert($newdata);
        }
        if (isset($data["prize_score"])) {
            $newdata["score"] = $data["prize_score"];
            $newdata["cur_score"] = $userOne["prize_score"];
            $newdata["remark"] = "奖励积分-BOF手动卖出收益";
            $newdata["created_at"] = time();
            $newdata["is_add"] = 1;
            $newdata["type"] = 3;
            db(self::ACCOUNT)->insert($newdata);
        }
        if (isset($data["pay_score"])) {
            $newdata["score"] = $data["pay_score"];
            $newdata["cur_score"] = $userOne["pay_score"];
            $newdata["remark"] = "购物积分-BOF手动卖出收益";
            $newdata["created_at"] = time();
            $newdata["is_add"] = 1;
            $newdata["type"] = 7;
            db(self::ACCOUNT)->insert($newdata);
        }
        return true;
    }

    #我的列队
    public function rankList()
    {
        $rank = db(self::RANK)->where(["uid" => $this->uid, "status" => 1])->find();
        if ($rank) {
            $rank["uid"] = db("users")->where("id", $this->uid)->value("code");
            $rank["created_at"] = date("Y-m-d H:i:s", $rank["created_at"]);
            #当前排位
            $where = [];
            $where["id"] = ["<=",$rank["id"]];
            $where["status"] = 1;
            $rank["current"] = db(self::RANK)->where($where)->count();
            #列队总数
            $rank["all_num"] = db(self::RANK)->where(["status" => 1])->count();
            return ajax(0, "获取成功!", $rank);
        } else {
            return ajax(100, "不在列队中!");
        }
    }

    public function myUser()
    {
        if (session("home.user")) {
            return ajax(0, "获取成功", session("home.user"));
        } else {
            return ajax(100, "未登录");
        }
    }

    public function testnew()
    {
       $stock = new Stock();
       $ceshi = $stock->loadAccount();
       halt($ceshi);
        #分配bof测试
//        $user = new Useractivate(114,0.20400,0,"");
//        $oo = $user->loadUser();
//        halt($oo);


    }
    #通过接点人code 和 region 确定该用户的位置
    public function wz($nid,$region)
    {
        #查询该接点人的信息
        $where["id"] = $nid;
        $ob = db(self::USER)->where($where)->field(["id"])->find();
        #判断该用户左右区有没有人
        $obs = db(self::USER)->where(["nid"=>$ob["id"],"region"=>$region])->field(["id","region"])->find();
        #如果有就执行递归
        if($obs){
            return $this->wz($obs["id"],$obs["region"]);
        }
        
        return $ob["id"];
    }

    public function test()
    {
        #查询当前销售总量
        $bofAll = db(self::USER)->where(["id" => $this->uid])->field(["bof_all", "class", "balance", "reg_score"])->find();
        #查询当前直推
        $userAll = db(self::USER)->where(["pid" => $this->uid])->field(["class"])->select();
        $zhi = $this->test1($userAll);

        if ((config("bofAll")[$bofAll["class"]]) * 0.8 <= $bofAll["bof_all"] && $zhi && $this->reg_score($bofAll)) {
            return "ok";
        } else {
            return "no";
        }
    }

    #接口 原点位激活提交
    public function original()
    {
        $userOne = db(self::USER)->where(["id" => $this->uid])
            ->field(["code", "class", "reg_score", "created_at", "updated_at"])->find();
        if (!$this->reg_score($userOne)) {
            return ajax(100, "注册币不足,不能申请原点复位!");
        }
        $oo = db(self::ORIGINAL)->where(["code" => $userOne["code"]])->find();
        if ($oo) {
            return ajax(100, "不能重复申请!");
        }

        $data["code"] = $userOne["code"];
        $data["class"] = $userOne["class"];
        $data["created_at"] = $userOne["created_at"];
        $data["updated_at"] = $userOne["updated_at"];
        $ob = db(self::ORIGINAL)->insert($data);
        if ($ob) {
            return ajax(0, "已成功申请");
        }

    }

    #判断注册积分是否充足
    public function reg_score($user)
    {
        $standard = config("bof_reg_score")[$user["class"]];
        if ($standard <= $user["reg_score"]) {
            return true;
        } else {
            return false;
        }
    }

    #判断是否达到原点位激活直推条件
    public function test1($user)
    {
        $arr = ["pk" => 0, "yk" => 0, "jk" => 0, "bj" => 0, "hj" => 0, "zk" => 0, "lz" => 0];
        foreach ($user as $v) {
            switch ($v["class"]) {
                case 1:
                    $arr["pk"]++;
                    $arr["class"] = $v["class"];
                    break;
                case 2:
                    $arr["yk"]++;
                    $arr["class"] = $v["class"];
                    break;
                case 3:
                    $arr["jk"]++;
                    $arr["class"] = $v["class"];
                    break;
                case 4:
                    $arr["bj"]++;
                    $arr["class"] = $v["class"];
                    break;
                case 5:
                    $arr["hj"]++;
                    $arr["class"] = $v["class"];
                    break;
                case 6:
                    $arr["zk"]++;
                    $arr["class"] = $v["class"];
                    break;
                case 7:
                    $arr["lz"]++;
                    $arr["class"] = $v["class"];
                    break;
            }
        }

        #如果不满足的话 直接返回false
        if (!isset($arr["class"])) {
            return false;
        }

        #真实直推人数与标准作比较
        if ($arr["pk"] >= config("bofLevel")[$arr["class"]] ||
            $arr["yk"] >= config("bofLevel")[$arr["class"]] ||
            $arr["jk"] >= config("bofLevel")[$arr["class"]] ||
            $arr["bj"] >= config("bofLevel")[$arr["class"]] ||
            $arr["hj"] >= config("bofLevel")[$arr["class"]] ||
            $arr["zk"] >= config("bofLevel")[$arr["class"]] ||
            $arr["lz"] >= config("bofLevel")[$arr["class"]]) {
            return true;
        } else {
            return false;
        }
    }

    #获取当前用户编号和手机号
    public function getUser()
    {
        $userOne = db(self::USER)->where(["id" => $this->uid])->field(["code", "phone"])->find();
        return ajax(0, "成功获取!", $userOne);
    }

    #留言
    public function message()
    {
        if (!\request()->post()) {
            return ajax(100, "请稍后重试!");
        }
        $content = htmlspecialchars(input("content"));

        if (!$content) {
            return ajax(100, "输入内容有误");
        }

        $data = [];
        $userOne = db(self::USER)->where(["id" => $this->uid])->field(["code", "phone"])->find();
        $data["code"] = $userOne["code"];
        $data["phone"] = $userOne["phone"];
        $data["content"] = trim($content);
        $data["created_at"] = time();

        $ob = db("message")->insert($data);
        if ($ob) {
            return ajax(0, "已成功留言");
        }
    }

    #当前用户是否是管理员直接登录的
    public function isAdmin(){
        $flag = session("home.user");
        if(isset($flag["flag"]) && $flag["flag"] == "admin"){
            return ajax(0,"yes");
        }else{
            return ajax(1,"no");
        }
    }
    #股值增长测试
    public function stocktest()
    {
        $data["uid"] = 164;
        $data["score"] = -500000;
        $data["remark"] = "手动增长";
        $data["status"] = 1;
        $data["is_add"] = 2;
        $data["source"] = 11;
        $data["created_at"] = time();

        $ob =  db("account")->insert($data);
        if($ob){
            echo "ok";
        }
    }
    #股值增长测试
    public function testtest()
    {
        // $oo = new Stock();
        // dd($oo->usersMarket());
        // $Useractivate = new Useractivate(192,0.35,0,'');
        // $arr["class"] = 7;
        // dd($Useractivate->levelUser($arr));
         $Useractivate = new Nineservice(394,200);
         dd($Useractivate->loadUser());

        // $oo = new Rerformance(321,1);
        // dd($oo->loadUser());

    }



}