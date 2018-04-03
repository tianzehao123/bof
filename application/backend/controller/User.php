<?php
/**
 * Created by PhpStorm.
 * User: ovo
 * Date: 2017/7/10
 * Time: 下午6:08
 */

namespace app\backend\controller;

use app\backend\model\UserModel;
use app\backend\model\ApplyModel;
use think\Request;
use think\Db;
use Service\Useractivate;
use Service\Stock;
use Service\Rerformance;
use Service\Nineservice;
use app\backend\validate\DoLeveldateValidate;


class User extends Base
{
    const USER = 'users';//用户表
    const ACCOUNT = 'account';//账户明细表
    const Amount = ['0' => 0, '1' => 100, "2" => 300, "3" => 500, "4" => 1000, "5" => 3000, "6" => 5000, "7" => 10000];
    const CLASSS = ['0' => '免费会员', '1' => "普卡", "2" => "银卡", "3" => "金卡", "4" => "白金卡", "5" => "黑金卡", "6" => "钻卡", "7" => "蓝钻"];
    const FORMSTATUS = [1 => '未审核', 2 => '已审核', 3 => '已驳回', 4 => '禁用'];
    const FROMCLASS = [1 => '无办公地点', 2 => '工作室', 3 => '子公司'];
    const SELLSTATUS = [0=>"自动",1 => '自动', 2 => '手动'];
    const TYPESTATUS = [1 => '未审核', 2 => '已同意', 3 => '已拒绝'];

    //用户列表
    public function index()
    {
        if (Request()->isAjax()) {
            $where = [];
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;
            if (!empty($param['code'])) {
                $where["code"] = ["like", "%" . $param['code'] . "%"];
            }
            if (!empty($param['phone'])) {
                $where['phone'] = ['like', "%" . $param['phone'] . "%"];
            }
            if (!empty($param['status'])) {
                $where['status'] = ['like', "%" . $param['status'] . "%"];
            }
            if (!empty($param['type_status'])) {
                $where['type_status'] = $param['type_status'];
            }
            if (!empty($param['sell_status'])) {
                $where['sell_status'] = $param['sell_status'];
            }
            if (!empty($param['class']) || $param['class'] == 0) {
                if ($param['class'] == '31') {
                    $where['class'] = ['in', [0, 1, 2, 3, 4, 5, 6, 7]];
                } else {
                    $where['class'] = $param['class'];
                }
            }
            if (!empty($param['start']) and empty($param['end'])) {
                $where['created_at'] = ['>=', strtotime($param['start'])];
            }
            if (empty($param['start']) and !empty($param['end'])) {
                $where['created_at'] = ['<=', strtotime($param['end'] . "23:59:59")];
            }
            if (!empty($param['start']) and !empty($param['end'])) {
                $where['created_at'] = ['between', [strtotime($param['start']), strtotime($param['end'] . "23:59:59")]];
            }

            //开始查询数据
            $field = ["id", "nickname", 'code', "phone", "sell_status", "type_status", "created_at", "balance", 'pay_score', "reg_score", "class", 'nid', "pid", "status", "left_all_ach", "right_all_ach", "islock", "videourl", 'truename', 'bd_id', 'is_class_date'];
            $list = Db::name("users")->field($field)->where($where)->order('id desc')->limit($pageNumber, $pageSize)->select();
            $count = Db::name("users")->where($where)->count();
            $model = new UserModel();
            $status = ["0" => "未知", "1" => "正常", "2" => "禁用", "3" => "待激活"];
            //补全信息

            // code        会员编号
            // truename    真实姓名
            // class       用户等级
            // AmountMoney 投资金额
            // xiaoqu      小区业绩
            // p_name      推荐人
            // n_name      接点人
            // bd_name     报单中心
            // created_at  会员注册时间
            // status      状态
            // operate     操作成功
            //获取 所有的报单中心id  推荐人id  接点人id
            $pidAll = null;
            $nidAll = null;
            $bd_idAll = null;
            foreach ($list as $key => $value) {
                $pidAll[] = $value['pid'];
                $nidAll[] = $value['nid'];
            }
            //查询报单中心名称 推荐人名称 节点名称
            $p_nameAll = $model->field(['id', 'code'])->where(['id' => ['in', $pidAll]])->select();
            $n_nameAll = $model->field(['id', 'code'])->where(['id' => ['in', $nidAll]])->select();

            foreach ($list as $key => $value) {
                $list[$key]['status'] = $status[$value['status']];                                  //会员状态
                $list[$key]['created_at'] = is_numeric($value['created_at']) ? date("Y-m-d H:i:s", $value['created_at']) : $value['created_at'];
                $list[$key]['class'] = self::CLASSS[$value['class']];                               //会员等级
                $list[$key]['sell_status'] = self::SELLSTATUS[$value['sell_status']];                               //审核方式
                $list[$key]['type_status'] = self::TYPESTATUS[$value['type_status']];                               //审核状态

                //获取用户推荐人名称
                foreach ($p_nameAll as $k => $v) {
                    if ($value['pid'] == $v['id']) $list[$key]['p_name'] = $v['code'];
                }

                //获取用户接点人名称
                foreach ($n_nameAll as $k => $v) {
                    if ($value['nid'] == $v['id']) $list[$key]['n_name'] = $v['code'];
                }


                //小区业绩
                if ($value['left_all_ach'] > $value['right_all_ach']) {
                    $list[$key]['xiaoqu'] = $value['right_all_ach'];
                } else {
                    $list[$key]['xiaoqu'] = $value['left_all_ach'];
                }

                //投资金额
                $list[$key]['AmountMoney'] = isset(self::Amount[$value['class']]) ? self::Amount[$value['class']] : '0';

                //组合操作按钮
                if ($value["status"] == 3) {
                    $operate = [
                        '编辑用户' => url('user/userupdate', ['id' => $value['id']]),
                        '登录该会员' => "javascript:userLogin('" . $value['id'] . "')",
                        '手动购物积分充值' => "javascript:userEdit('" . $value['id'] . "','" . $value['pay_score'] . "','" . $value['nickname'] . "')",
                    ];
                } else {
                    $operate = [
                        '编辑用户' => url('user/userupdate', ['id' => $value['id']]),
                        '登录该会员' => "javascript:userLogin('" . $value['id'] . "')",
                        '手动购物积分充值' => "javascript:userEdit('" . $value['id'] . "','" . $value['pay_score'] . "','" . $value['nickname'] . "')",
                    ];

                }

                // if ($value['class'] > 0) {
                $operate['资金明细'] = "javascript:user_account(" . $value['id'] . ")";
                if ($value['status'] === 3 && $value['class'] != 0) {
                    if (db('config')->where(['name' => 'register'])->value('value') == 2) {
                        $operate['激活账号'] = "javascript:activation('" . $value['id'] . "')";
                    }
                } else {
                    if ($value['islock'] === 1) {
                        $operate['账号锁定'] = "javascript:lock('" . $value['id'] . "')";
                    }
                    if ($value['islock'] === 2) {
                        $operate['账号解锁'] = "javascript:lock('" . $value['id'] . "')";
                    }
                }
                $operate['审核视频'] = "javascript:videourl('" . $value['videourl'] . "')";
                $operate['审核/复审'] = "javascript:update_status('" . $value['id'] . "')";
                // }


                if ($value['is_class_date'] != 1) {
                    $operate['升级收益级别'] = "javascript:class_date('" . $value['id'] . "','" . $value['code'] . "','" . self::CLASSS[$value['class']] . "')";
                }

                //添加操作按钮
                $list[$key]['operate'] = showOperate($operate);
            }

            $return['rows'] = $list;
            $return['total'] = $count;
            return json($return);
        }


        $this->assign([
            "class" => self::CLASSS
        ]);
        return $this->fetch();
    }

    public function videomp($id)
    {
        $uservideourl = db("users")->where(["id" => $id])->value("videourl");
        if (!empty($uservideourl)) {
            $uservideourl = 'http://webbof.ewtouch.com' . $uservideourl;
            header("location:" . $uservideourl);
            return;
        } else {
            return $this->error('该用户没有上传视频');
        }
    }

    public function showStatus()
    {
        $userStatus = db("users")->where(["id" => input("id")])->value("type_status");
        return ajax(0, "ok", $userStatus);
    }

    public function updateStatus()
    {
        $ob = db("users")->where(["id" => input("id")])->update(["type_status" => input("type_status")]);
        if ($ob) {
            return ajax(0, "ok");
        }
    }

    //购物积分充值
    public function userLogin()
    {
        $id = input("id");
        $user = db("users")->where(["id" => $id])->find();
        if ($user) {
            #对用户状态进行判断
            if ($user['status'] == 2) {
                return ajax('-1', '您已被系统拉黑，请联系客服！');
            } elseif ($user['status'] == 3) {
                if ($user['class'] != 0) {
                    return ajax('-1', '您的账号暂未激活,请到商城购物');
                }
            }
            if ($user['islock'] == 2) {
                return ajax('-1', '您的账号已被锁定');
            }
            $user["flag"] = "admin";
            session('home.user', $user);

            return ajax("1");
        }
    }

    //购物积分充值
    public function userEdit()
    {
        if (empty(input("id")) or !is_numeric(input("id"))) {
            return "请选择用户";
        }
        if (empty(input("Amount")) or !is_numeric(input("Amount"))) {
            return "请输入金额";
        }
        $pay_score = Db::name("users")->where(["id" => input("id")])->value("pay_score");
        if (input("Amount") > 0) {
            $remark = "系统充值";
            $is_add = 1;
        } else {
            $remark = "系统扣币";
            $is_add = 2;
        }
        $Amount = $pay_score + input("Amount");
        $account = [
            "uid" => input("id"),
            "score" => input("Amount"),
            "cur_score" => $Amount,
            "from_uid" => 0,
            "remark" => $remark,
            "is_add" => $is_add,
            "class" => 1,
            'type' => 7,
            "created_at" => time()
        ];

        $return = ["1" => "充值", "2" => "扣币"];
        //执行充值
        Db::startTrans();
        try {
            $users = Db::name("users")->where(['id' => input("id")])->update(['pay_score' => $Amount]);
            $account = Db::name("account")->insert($account);
            // 提交事务
            if ($users !== false and $account !== false) {
                Db::commit();
                return ($return[$is_add] . "成功");
            } else {
                Db::rollback();
                return ($return[$is_add] . "失败");
            }
        } catch (\Exception $e) {
            // 回滚事务
            Db::rollback();
            return ($return[$is_add] . "失败");
        }
    }


    //激活用户
    public function activation()
    {
        if (empty(input("id")) or !is_numeric(input("id"))) {
            return "请选择用户";
        }

        $disc = Db::name("disc")->order("id desc")->value(['market_price']);
        if (!empty($disc) and count($disc) > 0) {
            $market_price = $disc;
        } else {
            $market_price = Db::name("config")->where(['name' => 'current_price'])->value('value');
        }
        //判断用户是否已被激活
        $user = Db::name('users')->field(['status', 'class', 'reg_score'])->where(['id' => input('id')])->find();
        if ($user === false || count($user) < 1) {
            return '该用户不存在';
        }

        if ($user['status'] != 3) {
            return '该用户已激活过了';
        }
        if ($user['reg_score'] < self::Amount[$user['class']]) {
            return '该用户注册币不足';
        }
        if (empty($market_price)) return "系统错误";
        //分配BOF
        $Useractivate = new Useractivate(input('id'), $market_price, 0, '');
        $str = $Useractivate->loadUser();
        //分配bof
        if ($str != '已加入队列' && $str != '已激活') {
            return $str;
        }
        // //涨幅
        // $Stock = new Stock();
        // $str1 = $Stock->loadAccount();

        // 增加左右区业绩
        $Rerformance = new Rerformance(input('id'), $user['class']);
        $Rerformance->loadUser();
        //九级分销
        $Nineservice = new Nineservice(input('id'));
        $Nineservice->loadUser();
        $data['status'] = 1;
        $data['reg_score'] = ($user['reg_score'] - self::Amount[$user['class']]);
        $row = Db::name('users')->where(['id' => input('id')])->update($data);

        //激活添加记录
        $data3['uid'] = input('id');
        $data3['from_uid'] = 0;
        $data3['score'] = '-' . self::Amount[$user['class']];
        $data3['cur_score'] = ($user['reg_score'] - self::Amount[$user['class']]);
        $data3['remark'] = '系统激活会员';
        $data3['is_add'] = 2;
        $data3['class'] = 1;
        $data3['type'] = 1;
        $data3['created_at'] = time();
        $data3['source'] = 11;

        Db::name('account')->insert($data3);
        if ($row !== false) {
            return '激活成功';
        } else {
            return '激活失败';
        }
    }


    // 锁定用户
    public function lock()
    {
        if (empty(input("id")) or !is_numeric(input("id"))) {
            return "请选择用户";
        }
        $list = Db::name("users")->field(['status', 'islock', 'class'])->where(['id' => input("id")])->find();
        if ($list['status'] !== 3) {
            if ($list['islock'] == 2) {
                $data['islock'] = 1;
                $data['status'] = 1;
            } else {
                $data['islock'] = 2;
                $data['status'] = 2;
            }
            $row = Db::name("users")->where(['id' => input("id")])->update($data);
            if ($row !== false) {
                return "操作成功";
            } else {
                return "操作失败";
            }
        } else {
            return "此用户还没有激活";
        }

    }


    // 资金明细
    public function account()
    {
        $type = ["1" => "注册积分", "2" => "游戏积分", "3" => "奖励积分", "4" => "消费积分", "5" => "电子积分", "6" => "获赠积分", "7" => "购物积分", "8" => "bof", '9' => '基金币', '10' => '复投积分'];
        $class = ["0" => "未知", "1" => "积分", "2" => "余额"];
        $isAdd = ["0" => "未知", "1" => "收入", "2" => "支出"];

        if (empty(input("id"))) {
            return ajax(2, "请选择用户");
        }
        $list = Db::name("account")->field(['id', 'remark', 'class', 'is_add', 'type', 'score', 'cur_score', 'from_uid', 'created_at'])->order("id desc")->where(['uid' => input("id")])->select();
        if ($list !== false) {
            if (empty($list) or count($list) < 1) {
                return ajax(2, "没有数据", "");
            } else {
                foreach ($list as $key => $value) {
                    $list[$key]['type'] = $type[$value['type']];
                    $list[$key]['class'] = $class[$value['class']];
                    $list[$key]['is_add'] = $isAdd[$value['is_add']];
                    $list[$key]['created_at'] = (isset($value['created_at']) && is_numeric($value['created_at'])) ? date("Y-m-d H:i:s", $value['created_at']) : $value['created_at'];
                    if ($value['is_add'] < 0 or $value['is_add'] > count($isAdd)) {
                        $list[$key]['is_add'] = "未知";
                    } else {
                        $list[$key]['is_add'] = $isAdd[$value['is_add']];
                    }
                    if (!empty($value['from_uid'])) {
                        $uid[] = $value['from_uid'];
                    } else if ($value['from_uid'] === 0) {
                        $list[$key]['from_uid'] = "系统";
                    } else {
                        $list[$key]['from_uid'] = "未知";
                    }

                }

                //return $uid;
                //查询出所有用户  并给用户赋予用户编号
                if (isset($uid)) {
                    $row = Db::name('users')->field(['id', 'code'])->where(['id' => ['in', $uid]])->select();
                    foreach ($row as $key => $value) {
                        foreach ($list as $k => $v) {
                            if ($value['id'] === $v['from_uid']) {
                                $list[$k]['from_uid'] = $row[$key]['code'];
                            }
                        }
                    }
                }
                return ajax(1, "查询成功", $list);
            }
        } else {
            return ajax(2, "查询失败", "");
        }

    }


    //编辑用户
    public function userupdate()
    {
        if (Request()->isAjax() and !empty(Request()->post())) {
            if (empty(input('result'))) return ajax(2, '请添加信息');
            $data = $_POST['result'];
            $data = json_decode($data, true);
            //获取指定的并不为空的数据
            if (empty($data['id'])) return ajax(2, '请重新选择您要修改的用户');

            $field = ['truename', 'out_status', 'sell_trial', 'nickname', 'code', 'pid', 'nid', 'bd_id', 'class', 'identity', 'phone', 'bank_name', 'bank_branch', 'bank_account', 'weixin', 'zhifubao', 'region', 'two_password', 'password', 'reg_score', 'prize_score', 'game_score', 'ft_score', 'game_score', 'bank_branch', 'balance_return', 'balance'];
            //获取需要的数据
            $list = getNeedData($data, $field);
            //加密二级密码和密码
            $list['class'] = $data['class'];
            if (!empty($list['password'])) $list['password'] = md5($list['password']);
            if (!empty($list['two_password'])) $list['two_password'] = md5($list['two_password']);
            $row = Db::name("users")->where(['id' => $data['id']])->update($list);
            if ($row !== false) {
                $obSell = $this->setTrial($data['id'], $data['sell_trial']);
                if ($obSell) {
                    return ajax(1, "修改成功");
                }
            } else {
                return ajax(2, "修改失败");
            }

        } else {
            if (empty(input("id")) or !is_numeric(input("id"))) {
                $this->error("请选择需要编辑的用户");
            }
            //设置需要获取和修改的字段
            $field = ['id', 'truename', 'nickname', 'sell_trial', 'code', 'pid', 'nid', 'bd_id', 'out_status', 'class', 'identity', 'phone', 'bank_name', 'bank_branch', 'bank_account', 'weixin', 'zhifubao', 'region', 'reg_score', 'prize_score', 'game_score', 'ft_score', 'game_score', 'bank_branch', 'balance_return', 'balance', 'ele_score'];
            $user = Db::name("users")->field($field)->where(['id' => input("id")])->find();
            //获取该用户的推荐人名称和接点人名称
            $p_code = Db::name('users')->where(['id' => $user['pid']])->value('code');
            $n_code = Db::name('users')->where(['id' => $user['nid']])->value('code');
            $user['p_code'] = empty($p_code) ? '未知' : $p_code;
            $user['n_code'] = empty($n_code) ? '未知' : $n_code;


            $bof_where = [];
            $bof_where["type"] = 2;
            $bof_where["status"] = 2;
            $bof_where["uid"] = input("id");
            $user["bof_num"] = round(db("bof_deal")->where($bof_where)->sum("sell_num"), 2);

            $user['reg_score'] = round($user['reg_score'], 2);
            $user['prize_score'] = round($user['prize_score'], 2);
            $user['game_score'] = round($user['game_score'], 2);
            $user['ft_score'] = round($user['ft_score'], 2);
            $user['ele_score'] = round($user['ele_score'], 2);
            $user['balance'] = round($user['balance'], 2);
            $user['balance_return'] = round($user['balance_return'], 2);
            $user['bof_num'] = round($user['bof_num'], 2);

            if ($user !== false) {
                $this->assign([
                    'class' => self::CLASSS,
                    "user" => $user
                ]);
                return $this->fetch("user/useredit");
            } else {
                $this->error("获取用户信息失败");
            }

        }

    }

    #用户免审核之后  基础所有订单免审核状态
    public function setTrial($uid, $value)
    {
        if ($value == 1) {
            $num = 1;
            $num_v = 7;
        } else {
            $num = 7;
            $num_v = 1;
        }

        $nums = 0;
        $status = db("users")->where(["id" => $uid])->value("sell_trial");
        if ($status == $value) {
            $ob = db("score_deal")->where(["uid" => $uid, "status" => $num])->select();
            if (count($ob) >= 1) {
                foreach ($ob as $v) {
                    $oo = db("score_deal")->where(["id" => $v["id"]])->update(["status" => $num_v]);
                    if ($oo) {
                        $nums++;
                    }
                }
            }
        }

        if ($nums > 0) {
            return 1;
        }
        return 2;
    }

    //公排节点
    public function tree()
    {
        $where = [];
        $field = ["id", "nid", "code", "region", "class", 'class_s', "left_all_ach", "right_all_ach", "left_ach", "right_ach"];
        if (!empty(input("code"))) {
            $where['code'] = input('code');
        }
        if (!empty(input("nid"))) {
            $where['id'] = input('nid');
        }
        $model = new UserModel();
        if (count($where) < 1) {
            $one = $model->field($field)->find();
        } else {

            //获取第一层节点
            $one = $model->field($field)->where($where)->find();
            if ($one === false or empty($one)) {
                $this->error("您查询的用户不存在");
                return;
            }
        }
        $one['class'] = $this::CLASSS[$one['class']];
        $one['left_all_ach'] = ceil($one['left_all_ach'] / 100);
        $one['right_all_ach'] = ceil($one['right_all_ach'] / 100);
        $one['left_ach'] = ceil($one['left_ach'] / 100);
        $one['right_ach'] = ceil($one['right_ach'] / 100);
        $one['class_s'] = self::CLASSS[$one['class_s']];
        $two[1] = null;
        $two[2] = null;
        $three = [1 => [1 => "", 2 => ""], 2 => [1 => "", 2 => ""]];
        //第二代
        $row = $model->field($field)->where(['nid' => $one['id']])->select();
        foreach ($row as $key => $value) {
            $value['left_all_ach'] = ceil($value['left_all_ach'] / 100);
            $value['right_all_ach'] = ceil($value['right_all_ach'] / 100);
            $value['left_ach'] = ceil($value['left_ach'] / 100);
            $value['right_ach'] = ceil($value['right_ach'] / 100);
            $row[$key]["class"] = self::CLASSS[$value['class']];
            $row[$key]['class_s'] = self::CLASSS[$value['class_s']];
            $two[$value['region']] = $value;
            $nid[] = $value['id'];
            $region[$value['id']] = $value['region'];
        }
        // 第三代
        if (isset($nid)) {
            $data = $model->field($field)->where(['nid' => ['in', $nid]])->select();

            foreach ($data as $key => $value) {
                $value['left_all_ach'] = ceil($value['left_all_ach'] / 100);
                $value['right_all_ach'] = ceil($value['right_all_ach'] / 100);
                $value['left_ach'] = ceil($value['left_ach'] / 100);
                $value['right_ach'] = ceil($value['right_ach'] / 100);
                $data[$key]['class'] = self::CLASSS[$value['class']];
                $data[$key]['class_s'] = self::CLASSS[$value['class_s']];
                $three[$region[$value['nid']]][$value['region']] = $value;
            }
        }


        $this->assign([
            "one" => $one,
            "two" => $two,
            "three" => $three
        ]);
        return $this->fetch();

    }

    #用户注册
    public function register()
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $result = $this->validate($param, 'UserRegisterValidate');
            if (true !== $result) {
                return json(['code' => '-1', 'msg' => $result]);
            }
            #判断是否有此报单中心
            if (!db('form_core')->where('id', $param['bd_id'])->find()) {
                return json(['code' => '-1', 'msg' => '此报单中心不存在']);
            }
            // 查出推荐人id 推荐人集合  接点人集合
            $pid = db('users')->where('code', $param['pcode'])->field(['id', 'all_pid'])->find();
            if (!$pid) {
                return json(['code' => '-1', 'msg' => '此推荐人不存在']);
            }
            // 接点人ID 推荐人集合  接点人集合
            $nid = db('users')->where('code', $param['ncode'])->field(['id', 'all_nid'])->find();
            #推荐人集合
            if ($pid['all_pid'] == '') {
                $all_pid = '0,' . $pid['id'] . ',';
            } else {
                $all_pid = substr_replace($pid['all_pid'], ',' . $pid['id'] . ',', -1);
            }
            #接点人集合
            if ($nid['all_nid'] == '') {
                $all_nid = '0,' . $nid['id'] . ',';
            } else {
                $all_nid = substr_replace($nid['all_nid'], ',' . $nid['id'] . ',', -1);
            }
            $info = [];
            foreach ($param as $k => $v) {
                if ($k != 'repassword' && $k != 'pcode' && $k != 'ncode') {
                    $info[$k] = $v;
                }
            }

            $trueNid = $this->wz($nid['id'], $param["region"]);
            if ($trueNid) {
                return ajax('-1', '这个位置已经有人了!');
            }

            $info['pid'] = $pid['id'];
            $info['nid'] = $nid['id'];
            $info['all_pid'] = $all_pid;
            $info['all_nid'] = $all_nid;
            $info['password'] = md5($info['password']);
            $info['created_at'] = time();
            $info['class'] = $info['class_reg'];
            $info['nickname'] = 'BOF_' . $this->str_rand(3) . $this->str_rand(3) . rand(0, 9);     //随机生成昵称
            db('users')->insert($info);
            return json(['code' => 1, 'msg' => '注册成功']);
        }
        $data = [];
        foreach ($this::CLASSS as $k => $v) {
            if ($k != '0') {
                $data[$k] = $v;
            }
        }
        return $this->fetch('', ['region' => input('region'), 'classs' => $data, 'code' => input('code')]);
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
        if ($obs) {
            return true;
            // return $this->wz($obs["id"],$obs["region"]);
        }
        return $nid;
    }

    public function str_rand($length, $char = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ')
    {
        if (!is_int($length) || $length < 0) {
            return false;
        }

        $string = '';
        for ($i = $length; $i > 0; $i--) {
            $string .= $char[mt_rand(0, strlen($char) - 1)];
        }

        return $string;
    }

    //直属关系
    public function recommend()
    {

        $field = ['id', 'code'];
        $model = new UserModel();
        if (!empty(input("code"))) {
            $user = Db::name("users")->field($field)->where(['code' => input("code")])->find();
        } else {
            $user = $model->field($field)->find();
        }

        if ($user === false) {
            return ajax(2, "您搜索的用户不存在");
        }
        if (Request()->isAjax()) {
            if (empty($user) or count($user) < 1) {
                return ajax(2, "暂无数据", "");
            }
            $son = $model->field($field)->order("id")->where(['pid' => $user['id']])->select();
            if ($son === false or count($son) < 1) {
                return ajax(2, "查询失败", "");
            }
            foreach ($son as $key => $value) {
                $count = Db::name("users")->where(['pid' => $value['id']])->count();
                $son[$key]['text'] = $value['code'] . " : <span style='color:#1687ff'> " . $count . " </span> 个直推";
                $son[$key]['icon'] = "glyphicon glyphicon-user";
                $son[$key]['id'] = $value['code'];
            }
            return ajax(1, "查询成功", $son);
        } else {
            $this->assign([
                "code" => $user['code'],
            ]);
            return $this->fetch();
        }

    }


    //升级管理
    public function upgrade()
    {
        if (Request()->isAjax()) {
            $where = [];
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;
            if (!empty($param['code'])) {
                $where["code"] = ["like", "%" . $param['code'] . "%"];
            }
            if (!empty($param['class']) or $param['class'] === 0) {
                $where['class'] = $param['class'];
            }
            if (!empty($param['start']) and empty($param['end'])) {
                $where['created_at'] = ['>=', strtotime($param['start'])];
            }
            if (empty($param['start']) and !empty($param['end'])) {
                $where['created_at'] = ['<=', strtotime($param['end'] . "23:59:59")];
            }
            if (!empty($param['start']) and !empty($param['end'])) {
                $where['created_at'] = ['between', [strtotime($param['start']), strtotime($param['end'] . "23:59:59")]];
            }

            $list = Db::name("user_upgrade")->field(['id', 'code', 'truename', 'type', 'class', "class_at", "created_at", "state", "Amount"])->order("id desc")->where($where)->limit($pageNumber, $pageSize)->select();
            $count = Db::name("user_upgrade")->where($where)->count();

            $state = ["1" => "升级成功", "0" => "升级失败"];
            foreach ($list as $key => $value) {
                $list[$key]['created_at'] = date("Y-m-d h:i:s", $value['created_at']);
                $list[$key]['class'] = self::CLASSS[$value['class']];
                $list[$key]['class_at'] = self::CLASSS[$value['class_at']];
                $list[$key]['state'] = $state[$value['state']];
            }

            $return['rows'] = $list;
            $return['total'] = $count;
            return json($return);

        } else {
            $this->assign(["class" => $this::CLASSS]);
            return $this->fetch();
        }
    }


    // 报单中心列表
    public function form_center()
    {
        $where = [];
        if (Request()->isAjax()) {
            // 拼装搜索条件
            $param = input('param.');
            if (!empty($param['code'])) {
                $where['ucode'] = $param['code'];
            }
            if (!empty($param['from_class'])) {
                $where['from_class'] = $param['from_class'];
            }
            if (!empty($param['status'])) {
                $where['status'] = $param['status'];
            }
            $pageSize = empty(input('pageSize')) ? 1 : input('pageSize');
            $pageNumber = empty(input('pageNumber')) ? 12 : input('pageNumber');

            //获取列表数据
            return $this->listAggregate('form_core', $where, $pageSize, $pageNumber, "报单中心", $param);
        }

        $this->assign([
            'from_status' => self::FORMSTATUS,
            'from_class' => self::FROMCLASS
        ]);

        // Db::name("form_core")->field(['uid', 'ucode', 'class', 'address', 'status', 'creq'])->where($where)->limit($pageNumber, $pageSize)->select();

        return $this->fetch();
    }

    //审核报单中心
    public function updatefrom()
    {
        if (!empty(input('id')) && !empty(input('status'))) {
            $row = Db::name('form_core')->where(['id' => input('id')])->update(['status' => input('status'), 'updated_at' => time()]);
            if ($row !== false) {
                return json(['code' => 1, 'msg' => '修改成功']);
            } else {
                return json(['code' => 2, 'msg' => '修改失败']);
            }
        } else {
            return json(['code' => 2, 'msg' => '修改失败']);
        }
    }

    //签约商家已审核
    public function business()
    {

        if (Request()->isAjax()) {
            // 拼装搜索条件
            $where = [];
            $param = input('param.');
            if (!empty($param['code'])) {
                $where['ucode'] = $param['code'];
            }
            if (!empty($param['status'])) {
                $where['status'] = $param['status'];
            }
            //获取列表数据
            return $this->listAggregate('business', $where, input('pageSize'), input('pageNumber'), "商家", $param);

        }
        $this->assign([
            'from_status' => self::FORMSTATUS,
        ]);

        return $this->fetch();
    }

    //修改签约商家审核状态
    public function updatebuss()
    {
        if (!empty(input('id')) && !empty(input('status'))) {
            $row = Db::name('business')->where(['id' => input('id')])->update(['status' => input('status'), 'updated_at' => time()]);
            if ($row !== false) {
                return json(['code' => 1, 'msg' => '修改成功']);
            } else {
                return json(['code' => 2, 'msg' => '修改失败']);
            }
        } else {
            return json(['code' => 2, 'msg' => '修改失败']);
        }

    }

    //原点复位
    public function originreset()
    {
        if (Request()->isAjax()) {
            $where = [];
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;
            if (!empty($param['code'])) {
                $where["code"] = ["like", "%" . $param['code'] . "%"];
            }
            if (!empty($param['class']) or $param['class'] === 0) {
                $where['class'] = $param['class'];
            }
            if (!empty($param['start']) and empty($param['end'])) {
                $where['created_at'] = ['>=', strtotime($param['start'])];
            }
            if (empty($param['start']) and !empty($param['end'])) {
                $where['created_at'] = ['<=', strtotime($param['end'] . "23:59:59")];
            }
            if (!empty($param['start']) and !empty($param['end'])) {
                $where['created_at'] = ['between', [strtotime($param['start']), strtotime($param['end'] . "23:59:59")]];
            }

            $list = Db::name("bof_original")->order("id desc")->where($where)->limit($pageNumber, $pageSize)->select();
            $count = Db::name("bof_original")->where($where)->count();

            foreach ($list as $key => $value) {
                $list[$key]['created_at'] = date("Y-m-d h:i:s", $value['created_at']);
                $list[$key]['status'] = config("status_origin")[$value['status']];
                $list[$key]['class'] = config("name")[$value['class']];
                $operate = [
                    '通过' => in_array($value["status"], [2, 3]) ? "javascript:void(0)" : "javascript:operation(" . $value['id'] . ")",
                    '拒绝' => in_array($value["status"], [2, 3]) ? "javascript:void(0)" : "javascript:refuse(" . $value['id'] . ")",
                ];
                $list[$key]['operate'] = showOperate($operate);
            }

            $return['rows'] = $list;
            $return['total'] = $count;
            return json($return);

        } else {
            $this->assign(["class" => $this::CLASSS]);
            return $this->fetch();
        }
    }

    #拒绝
    public function refuse()
    {
        $ob = db("bof_original")->where(["id" => input("id")])->update(["status" => 3]);
        if ($ob) {
            return json(["code" => 1, "msg" => "已处理"]);
        } else {
            return json(["code" => 2, "msg" => "请稍后重试"]);
        }
    }

    #同意
    public function operation()
    {
        #修改状态为通过
        $ob = db("bof_original")->where(["id" => input("id")])->update(["status" => 2]);
        #清空bof_all字段
        if ($ob) {
            $code = db("bof_original")->where(["id" => input("id")])->value("code");
            $userOne = db("users")->where(["code" => $code])->field(["reg_score", "class"])->find();
            if ($userOne["reg_score"] < config("bof_reg_score")[$userOne["class"]]) {
                return json(["code" => 2, "msg" => "该用户注册积分不足"]);
            }

            $oo = db("users")->where(["code" => $code])
                ->update(["balance" => 0, "reg_score" => $userOne["reg_score"] -= config("bof_reg_score")[$userOne["class"]]]);

            if ($oo) {
                return json(["code" => 1, "msg" => "已处理"]);
            } else {
                return json(["code" => 1, "msg" => "请稍后重试!"]);
            }
        }
        #扣除等级对应积分
        #扣除所有bof->等待下次重新分配
    }

    //积分列表
    public function integrallist()
    {
        if (Request()->isAjax()) {
            $where = [];
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;
            if (!empty($param['code'])) {
                $where["code"] = ["like", "%" . $param['code'] . "%"];
            }
            if (!empty($param['start']) and empty($param['end'])) {
                $where['created_at'] = ['>=', strtotime($param['start'])];
            }
            if (empty($param['start']) and !empty($param['end'])) {
                $where['created_at'] = ['<=', strtotime($param['end'] . "23:59:59")];
            }
            if (!empty($param['start']) and !empty($param['end'])) {
                $where['created_at'] = ['between', [strtotime($param['start']), strtotime($param['end'] . "23:59:59")]];
            }

            //拼装积分搜索
            $arr = ['reg_score', 'prize_score', 'game_score', 'con_score', 'ele_score', 'receive_score', 'pay_score', 'ft_score', 'fund_gold', 'balance'];
            foreach ($arr as $key => $value) {
                if (!empty($param[$value]) && is_numeric($param[$value])) {
                    $where[$value] = ['>=', $param[$value]];
                }
            }

            //开始查询数据
            $field = ["id", "truename", 'code', 'reg_score', 'prize_score', 'game_score', 'con_score', 'ele_score', 'receive_score', 'pay_score', 'ft_score', 'fund_gold', 'balance'];
            $fieldName = ['id' => 'id', 'nickname' => '真实姓名', 'code' => '会员编号', 'reg_score' => '注册积分', 'prize_score' => '奖励积分', 'game_score' => '游戏积分', 'con_score' => '消费积分', 'ele_score' => '电子积分', 'receive_score' => '获赠积分', 'pay_score' => '购物积分', 'ft_score' => '复投积分', 'fund_gold' => '慈善基金', 'balance' => 'BOF余额'];
            $list = Db::name("users")->field($field)->where($where)->limit($pageNumber, $pageSize)->select();
            //导出exel
            if (isset($param['excel']) && $param['excel'] == 'to_excel') {    //导出到excel
                $content = objToArray($list);
                $excel = new Excel();
                $first = ['A1' => '编号', 'B1' => '真实姓名', 'C1' => '会员编号', 'D1' => '注册积分', 'E1' => '奖励积分', 'F1' => '游戏积分', 'G1' => '消费积分', 'H1' => '电子积分', 'I1' => '获赠积分', 'J1' => '购物积分', 'K1' => '复投积分', 'L1' => '慈善基金', 'M1' => 'BOF余额'];
                $excel->toExcel('用户积分列表', $content, $first);
                return json(["code" => 1]);
            }

            //显示表格数据
            $count = Db::name("users")->where($where)->count();
            $model = new UserModel();
            $return['rows'] = $list;
            $return['total'] = $count;
            return json($return);
        }

        $this->assign([
            "class" => self::CLASSS
        ]);
        return $this->fetch();
    }


    /**
     *
     * @param str $table 表名称
     * @param array $where 搜索条件
     * @param integer $pageSize 每页长度
     * @param integer $pageNumber 第几页
     * @param str $name 提示名称
     * @param array $param 参数
     * @return array
     */
    private function listAggregate($table, $where = "", $pageSize, $pageNumber, $name, $param)
    {
        // 公共搜索条件
        if (!empty($param['start']) and empty($param['end'])) {
            $where['created_at'] = ['>=', strtotime($param['start'])];
        }
        if (empty($param['start']) and !empty($param['end'])) {
            $where['created_at'] = ['<=', strtotime($param['end'] . '23:59:59')];
        }
        if (!empty($param['start']) and !empty($param['end'])) {
            $where['created_at'] = ['between', [strtotime($param['start']), strtotime($param['end'] . '23:59:59')]];
        }


        if (empty($table)) {
            return false;
        }
        if (!is_numeric($pageNumber) || !is_numeric($pageSize)) {
            return false;
        }
        $pageNumber = ($pageNumber - 1) * $pageSize;  //设置分页
        // 查询数据
        $list = Db::name($table)->where($where)->order('id desc')->limit($pageNumber, $pageSize)->select();
        $count = Db::name($table)->where($where)->count();
        //数据转化文字
        if ($list !== false) {
            foreach ($list as $key => $value) {
                if (!empty($value['class'])) {
                    $list[$key]['class'] = self::CLASSS[$value['class']];
                }
                if (!empty($value['status'])) {
                    $list[$key]['status'] = self::FORMSTATUS[$value['status']];
                }
                if (!empty($value['created_at'])) {
                    $list[$key]['created_at'] = date('Y-m-d H:i:s', $value['created_at']);
                }
                if (!empty($value['updated_at'])) {
                    $list[$key]['updated_at'] = date('Y-m-d H:i:s', $value['updated_at']);
                }
                if (!empty($value['from_class'])) {
                    $list[$key]['from_class'] = self::FROMCLASS[$value['from_class']];
                }
                if (!empty($value['img'])) {
                    $list[$key]['img'] = "<img style='width:80px;height:100px' onclick=\"clickImg('" . config('web_url') . $value['img'] . "')\" src='" . config('web_url') . $value['img'] . "'/>";
                }

                $operate = [];
                //组合操作按钮
                if ($value['status'] == 1) {
                    $operate = [
                        '通过' => "javascript:UpdateState('" . $value['id'] . "',2,'您确定要激活该" . $name . "吗')",
                        '驳回' => "javascript:UpdateState('" . $value['id'] . "',3,'您确定要驳回该" . $name . "吗')"
                    ];
                } else if ($value['status'] == 2) {
                    $operate = [
                        '禁用' => "javascript:UpdateState('" . $value['id'] . "',4,'您确定要禁用该" . $name . "吗')"
                    ];
                } else if ($value['status'] == 4) {
                    $operate = [
                        '恢复' => "javascript:UpdateState('" . $value['id'] . "',2,'您确定要恢复该" . $name . "吗')"
                    ];
                }


                //添加操作按钮
                $list[$key]['operate'] = showOperate($operate);
            }
        }

        $return['rows'] = $list;
        $return['total'] = $count;
        return $return;

    }


    // 虚升用户级别
    public function DoLevelDate()
    {
        if (empty(input())) return ajax(2, '请输入内容');
        $validate = new DoLeveldateValidate();
        if (!$validate->check(input())) return ajax(2, $validate->getError());
        $data['class_s'] = input('level');
        $data['class_date_s'] = strtotime(date('Ymd'));  //开始虚升的日期
        $data['class_date'] = (input('date') * 60 * 60 * 24) - 100;  //虚升日期
        $data['is_class_date'] = 1;

        $model = new  UserModel();
        if ($model->where(['id' => input('id')])->update($data)) {
            return ajax(1, '虚升成功');
        } else {
            return ajax(2, '虚升失败');
        }
    }

    //虚升用户级别列表
    public function userLevel()
    {
        if (Request()->isAjax()) {
            $model = new UserModel();

            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;

            $where['is_class_date'] = ['neq', 2];
            if (!empty($param['code'])) $where['code'] = $param['code'];

            $field = ['id', 'code', 'class_date', 'is_class_date', 'class_date_s', 'class', 'class_s'];
            $list = $model->field($field)->where($where)->order('class_date_s  desc')->limit($pageNumber, $pageSize)->select();
            $count = $model->where($where)->count();
            $data = [];
            foreach ($list as $key => $value) {
                $data[$key]['class'] = self::CLASSS[$value['class']];
                $data[$key]['class_s'] = self::CLASSS[$value['class_s']];
                $data[$key]['u_code'] = $value['code'];
                $data[$key]['date'] = ceil($value['class_date'] / 60 / 60 / 24) . '  /天';
                $data[$key]['start_date'] = date('Y-m-d H:i:s', $value['class_date_s']);
                $data[$key]['end_date'] = date('Y-m-d H:i:s', ($value['class_date_s'] + $value['class_date']));
                $date = (ceil((($value['class_date_s'] + $value['class_date']) - time()) / 60 / 60 / 24) < 0) ? 0 : ceil((($value['class_date_s'] + $value['class_date']) - time()) / 60 / 60 / 24);
                $data[$key]['surplus'] = $date . '  /天';

                $operate = [];
                if ($value['is_class_date'] == 1) {
                    $operate['结束虚升收益'] = "javascript:end_date('" . $value['id'] . "','" . $value['class'] . "')";
                    $operate['修改虚升收益'] = "javascript:update_date('" . $value['id'] . "','" . $value['code'] . "','" . self::CLASSS[$value['class']] . "','" . $value['class_s'] . "','" . $date . "')";
                    $data[$key]['operation'] = showOperate($operate);
                } else {
                    //添加操作按钮
                    $data[$key]['operation'] = '<span style="color:#f00">已结束</span>';
                }


            }


            $return['rows'] = $data;
            $return['total'] = $count;
            return json($return);

        } else {
            $this->assign(["class" => self::CLASSS]);
            return $this->fetch();
        }
    }


    //结束虚升收益
    public function endDate()
    {
        if (empty(input('id'))) return ajax(2, '请选择要结束的用户');
        $where['id'] = input('id');
        $model = new UserModel();
        //获取原始等级
        $data['class_s'] = $model->where($where)->value('class');
        $data['is_class_date'] = 3;

        if ($model->where($where)->update($data)) {
            return ajax(1, '结束成功');
        } else {
            return ajax(2, '结束失败');
        }

    }


    //修改虚升级别
    public function updateLevel()
    {
        if (empty(input('id'))) return ajax(2, '请选择需要调节的用户');
        if (empty(input('level')) || !in_array(input('level'), [0, 1, 2, 3, 4, 5, 6, 7])) return ajax(2, '请选择正确的虚升级别');
        if (empty(input('date')) || input('date') < 1 || input('date') > 100) return ajax(2, '请输入正确的虚升时间');

        $where['id'] = input('id');
        $data['class_s'] = input('level');
        $data['class_date'] = input('date') * 60 * 60 * 24;

        $model = new UserModel();
        if ($model->where($where)->update($data)) {
            return ajax(1, '修改成功');
        } else {
            return ajax(2, '修改失败');
        }
    }


}