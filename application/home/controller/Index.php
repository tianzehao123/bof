<?php

namespace app\home\controller;

use app\backend\model\ArticleModel;       //文章
use app\backend\model\CartModel;
use app\backend\model\OrderModel;
use app\backend\model\UserModel;
use think\Controller;
use think\Db;
use codes\Qrcode;
use think\Hook;
use wechats\Wechat;
use app\backend\model\LunboModel as Lunbo;    //轮播
use app\backend\model\GoodsModel;    //商品
class Index extends Controller
{
    const LUNBO = 'lunbo';  //轮播图
    const GOODS = 'goods';  //商品
    const CART = 'cart';    //购物车
    const ADDRESS = 'address';  //收货地址
    const USER = 'users';   //用户
    const DISC = 'disc';   //大盘表
    const CONFIG = 'config';   //配置表
    const ORDER = 'order';  //订单
    const DETAIL = 'order_detail';  //s订单详情
    const ACCOUNT = 'account';  //账户详情表
    const ARTICLE = 'article'; //文章表
    const USERS = 'home.user';

    #首页
    public function index()
    {
        $uid = session('home.user')["id"];
        $ob = db("users")->where(["id"=>$uid])->find();
        if (!$ob) {
            json(['status' => '10000', 'message' => '您还没有登录'])->send();
            exit;
        }
        #用户信息 TODO
        $userOne = db(self::USER)->where(["id" => $uid])->field(["code", "class","class_s", "star", "receive_score", "prize_score",
            "ele_score", "pay_score", "con_score", "reg_score", "game_score", "balance", "balance_return"])->find();
        $userOne["class"] = config("name")[$userOne["class"]];
        $userOne["class_s"] = config("name")[$userOne["class_s"]];
        #bof总量
        $userOne["bof_all"] = round($userOne["balance"] + $userOne["balance_return"]);
        $userOne["balance"] = round($userOne["balance"]);
        $userOne["balance_return"] = round($userOne["balance_return"]);


        $userOne["game_score"] = round($userOne["game_score"],2);
        $userOne["pay_score"] = round($userOne["pay_score"],2);
        $userOne["con_score"] = round($userOne["con_score"],2);
        $userOne["receive_score"] = round($userOne["receive_score"],2);
        $userOne["prize_score"] = round($userOne["prize_score"],2);
        $userOne["reg_score"] = round($userOne["reg_score"],2);
        $userOne["ele_score"] = round($userOne["ele_score"],2);
        #走势图
        $ten = new Bofsell();
        $userOne["ten"] = $ten->showDisc();
        #bof交易量
        $userOne["jiaoyi"] = db(self::ACCOUNT)->where(["type" => 8])->sum('cur_score');
        #上次市价
        $disc = db(self::DISC)->order("created_at", "desc")->select()[1]["market_price"];
        if (!$disc) {
            $disc = db(self::CONFIG)->where(["name" => "current_price"])->value("value");
        }
        $userOne["start"] = $disc;
        #当前市价
        $cur_price = db(self::DISC)->order("created_at", "desc")->select()[0]["market_price"];
        if (!$cur_price) {
            $cur_price = db(self::CONFIG)->where(["name" => "current_price"])->value("value");
        }
        $userOne["end"] = $cur_price;

        return ajax(0, "获取成功", $userOne);

    }

    public function edge_out()
    {
        session(null);
        return json(['code' => 1]);
    }

    public function is_login()
    {
        if (!empty(session('home.user'))) {
            return json(['status' => 100, 'message' => '已登录', 'data' => session('home.user')['id']]);
        } else {
            return json(['status' => 101, 'message' => '未登录']);
        }
    }

    #轮播
    public function lunbo()
    {
        $return = db(self::LUNBO)->where('gid is null')->order('id desc')->limit(0, 5)->select();
        return json(['status' => 1, 'message' => '完成', 'data' => $return]);
    }

    #未读公告数
    public function unread_article()
    {
        $uid = session(self::USERS)['id'];
        $return = 0;
        if (!empty($uid)) {
            $user_yidu = db('users')->where(['id' => $uid])->value('gid');
            if (!empty($user_yidu)) {
                $yidu = explode(',', $user_yidu);
                $return = db('article')->where(['type' => '最新公告'])->count() - count($yidu);
            }
        }
        return json(['status' => 1, 'message' => '完成', 'data' => $return]);
    }

    #首页商品
    public function index_goods()
    {

        $return = [
            '粉丝商品' => db('goods')->where(['cid' => 1, 'is_delete' => 1])->limit(3)->select(),
            '购物专区' => db('class')->where(['id' => ['neq', 1]])->limit(6)->select()
        ];

        return json(['status' => 1, 'message' => '完成', 'data' => $return]);
    }

    #商品列表
    public function goods_list()
    {
        $goods = new GoodsModel();
//        需要cid         商品类别id
        $return['list'] = $goods->getGoodsByWhere(['cid' => input('cid'), 'is_delete' => 1], 0, 100);
        return json(['status' => 1, 'message' => '完成', 'data' => $return]);
    }

    #商品分类列表
    public function class_list()
    {
        $return['class'] = db('class')->where(['id' => ['neq', 1]])->order('id desc')->select();
        // $class = input('cid') != 0?input('cid'):db('class')->where(['id'=>['>',1]])->order('id desc')->value('id');
        /**{'img':'images/list1.png','con':'商场同款诗凡黎2016冬季新款连衣裙','price':'298.00'},*/
        $where = [];
        if (!empty(input('cid'))) {
            $where['cid'] = input('cid');
        }
        $data = db('goods')->where(['is_delete' => 1, 'cid' => ['neq', 1]])->where($where)->select();
        $datas = [];
        foreach ($data as $k => $v) {
            $datas[] = [
                'id' => $v['id'],
                'img' => $v['img'],
                'con' => $v['name'],
                'cid' => $v['cid'],
                'price' => $v['price'],
                'market_price' => $v['market_price'],
            ];
        }
        $return['goods'] = $datas;
        return json(['status' => 1, 'message' => '完成', 'data' => $return]);
    }

    #新闻列表
    public function about()
    {
        $type = input('type');
        $search = '公司简介';
        if ($type == 'novice') {
            $search = '新手指南';
        }
        if ($type == 'news') {
            $search = '最新公告';
        }
        $return = ArticleModel::all(function ($query) use ($search) {
            $query->where(['type' => $search])->order('id desc');
        });
        return json(['status' => 1, 'message' => '完成', 'data' => $return]);
    }

    #文章详情
    public function article()
    {
        #需要文章id
        $return['article'] = ArticleModel::get(['id' => input('id')]);
        if ($return['article']->type == '最新公告') {
            if (!empty(session(self::USERS)['id'])) {
                $uid = session(self::USERS)['id'];
                $user = db('users')->where(['id' => $uid])->find();
                $yidu = explode(',', $user['gid']);
                if (!in_array(input('id'), $yidu)) {
                    $up['gid'] = trim($user['gid'] . ',' . input('id'), ',');
                    db('users')->where(['id' => $uid])->update($up);
                }
            }
        }
        return json(['status' => 1, 'message' => '完成', 'data' => $return]);
    }

    #客服中心
    public function kefu()
    {
        $config = unserialize(file_get_contents('./config'));
        $return['kefu_ma'] = $config['kefu_ma'];
        $return['kefu_phone'] = $config['kefu_phone'];
        $return['gongzuo_time'] = $config['gongzuo_time'];
        return json(['status' => 1, 'message' => '完成', 'data' => $return]);
    }

    #商品详情
    public function goods_detail()
    {
        #需要商品id,id
        $return['goods'] = GoodsModel::get(['id' => input('id')]);
//        dump($return);exit;
        $return['goods']->lunbo;

        $return['goods']->xiaoliang = $return['goods']->orderDetail()->sum('g_num');


        // dump(objToArray($return));
        return json(['status' => 1, 'message' => '完成', 'data' => $return]);
    }

    #推广二维码
    public function promotion()
    {

        $id = session('home.user')['id'];

        if (!$id) {
            return json(['status' => 3, 'message' => '您还没有登录']);
        }
        $user = db(self::USER)->where(['id' => $id])->find();
        // $is_xf = db('order')->where(['uid'=>$id,'fenhong'=>1])->find();
        if ($user['class'] == 1) return json(['status' => 2, 'message' => '消费后才有推广二维码']);
        // $img = './tg_bj.png';

        $return['nickname'] = !empty($user['nickname']) ? $user['nickname'] : $user['phone'];
        $qrcode = '';
        if (!is_file('./userimg/qrcode' . $user['id'] . '.png')) {
            $path = ROOT_PATH . 'public/userimg/qrcode' . $id . '.png';
            Qrcode::png(DOMIAN . '/home/login/toweb?pid=' . $id, $path);
        }
        $qrcode = 'http://' . $_SERVER['HTTP_HOST'] . '/userimg/qrcode' . $user['id'] . '.png';
        $return['qrcode'] = $qrcode;
        $return['headimgurl'] = $user['headimgurl'];
        // dump(DOMIAN.'/home/login/toweb');exit;
//        if(is_weixin()){
//            $jsapi_config = Wechat::get_jsapi_config(['onMenuShareTimeline','onMenuShareAppMessage'],false,false);
//            $this->assign('jsapi',$jsapi_config);
//        }else{
//            $this->assign('jsapi','123');
//        }
        return json(['status' => 1, 'message' => '获取成功', 'data' => $return]);

    }


}