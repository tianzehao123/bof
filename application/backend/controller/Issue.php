<?php
/**
 * Created by PhpStorm.
 * User: ovo
 * Date: 2017/7/10
 * Time: 下午6:08
 */

namespace app\backend\controller;

use app\home\controller\Rank;

class Issue extends Base
{
    const RANK = "bof_rank";
    const ACCOUNT = "account";
    const DEAL = "bof_deal";

    public function index()
    {
        #bof剩余总量
        $bof_all = db("config")->where("name", "bof_all")->value("value");
        #当前股价
        if (!db("disc")->count()) {
            $current_price = db("config")->where(["name" => "current_price"])->value("value");
        } else {
            $current_price = db("disc")->order("id", "desc")->value("market_price");//TODO sql_disc大盘表
        }
        #当前发行剩余bof
        $current_bof = db("bof_issue")->where(["status" => 1])->order("id", "desc")->value("bof_num");
        $current_bof = !$current_bof ? 0 : $current_bof;
        //halt($bof_all);
        #发行提示 发行需要消耗给排队等待中的用户
        $bofNum = db(self::RANK)->where(["status" => 2, "iswhe" => 2])->sum("ele_score");
        $bofNum = !$bofNum ? 0 : $bofNum;
        #BOF总卖出
        $bofSeell = db(self::DEAL)->where(["type"=>2])->sum("sell_num");
        $bofSeell = !$bofSeell ? 0 : $bofSeell;
        #当前市场业绩
        $bof_user = db(self::ACCOUNT)->where(["is_add" => 2, "source" => ["IN",[11,8]]])->sum("score");
        $bof_all = round($bof_all,0);
        $current_bof = round($current_bof,0);
        return $this->fetch("issue/index", ['bof_all' => $bof_all, 'bof_user' => abs($bof_user),
            'current_price' => $current_price, 'current_bof' => $current_bof, 'bofNum' => $bofNum, 'bofSeell' => $bofSeell]);
    }

    #发行
    public function addbof()
    {
        $param = input('param.');
        $bofnum = parseParams($param['data'])["bof_num"];
        if (!$bofnum) {
            return json(["code" => 2, "msg" => "输入有误"]);
        }
        #当前发行bof(包括0)  不管当前发行的有没有  直接发行成功 剩余的直接加入总量
        $issue = db("bof_issue")->where(["status" => 1])->order("id", "desc")->value("bof_num");
        if ($issue) {
            $bofnum = $bofnum + $issue;
        }
        if ($bofnum <= 0) {
            return json(["code" => 2, "msg" => "当前发行无法填补需求量!"]);
        }

        if (!db("disc")->count()) {
            $bofCurrent = db("config")->where(["name" => "current_price"])->value("value");
        } else {
            $bofCurrent = db("disc")->order("id", "desc")->find()["market_price"];//TODO sql_disc大盘表
        }
        $data["bof_num"] = $bofnum;
        $data["bof_price"] = $bofCurrent;
        $data["bof_residue"] = db("config")->where("name", "bof_all")->value("value") - $bofnum;
        $data["created_at"] = time();
        $res = db("bof_issue")->insert($data);
        if ($res) {
            db("config")->where("name", "bof_all")->setDec("value", $bofnum);

            $rank = db(self::RANK)->where(["iswhe" => 2])->select();
            if ($rank) {
                foreach ($rank as $v) {
                    $oo = new Rank();
                    $oo->userBof($v);
                    db(self::RANK)->where(["id" => $v["id"]])->update(["status" => 2]);
                }
            }
            return json(["code" => 1, "msg" => "发行成功"]);
        }
    }

    #列队
    public function bofGo()
    {
        if (Request()->isAjax()) {
            $where = [];
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;
            if (!empty($param['code'])) {
                $code = db("users")->where(["code" => $param['code']])->value("id");
                if ($code) {
                    $where["uid"] = ["like", "%" . $code . "%"];
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
            $list = db("bof_rank")->where($where)->limit($pageNumber, $pageSize)->select();
            $count = db("bof_rank")->where($where)->count();
            foreach ($list as $k => $v) {
                $userOne = db("users")->where("id", $v["uid"])->field(["code","phone"])->find();
                $list[$k]["uid"] = !$userOne["code"]?$userOne["phone"]:$userOne["code"];
                $list[$k]["created_at"] = date("Y-m-d H:i:s", $v["created_at"]);
                $list[$k]["status"] = $v["status"] == 1 ? "未处理" : "已处理";
                $list[$k]["oper"] = "<input type='checkbox' value='" . $v["id"] . "' name='allId'>";

                if ($v["status"] == 1) {
                    $operate = [
                        '分配bof' => "javascript:bofEdit('" . $v['id'] . "','" . $v['uid'] . "')",
                    ];
                } else {
                    $operate = [
                        '分配bof' => "javascript:void(0)",
                    ];
                }
                $list[$k]['operate'] = showOperate($operate);
            }

            $return['rows'] = $list;
            $return['total'] = $count;
            return json($return);
        }

        return $this->fetch("issue/rank");
    }

    public function bofEdit()
    {
        $current_bof = db("bof_issue")->where(["status" => 1])->order("id", "desc")->value("bof_num");
        if(!$current_bof){
            return json(["code" => 2, "msg" => "当前没有发行BOF,不可分配!"]);
        }
        $rank = db(self::RANK)->where(["id" => input('id')])->find();
        if ($rank["status"] == 1) {
            $oo = new Rank();
            $ob = $oo->userBof($rank);
            db(self::RANK)->where(["id" => input('id')])->update(["status" => 2]);
            if ($ob) {
                return json(["code" => 1, "msg" => "分配成功!"]);
            }
        } else {
            return json(["code" => 2, "msg" => "该用户已经处理过了"]);
        }
    }

    public function bofRecord()
    {
        if (Request()->isAjax()) {
            $where = [];
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;

            if (!empty($param['status'])) {
                $where["status"] = $param['status'];
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
            $list = db("bof_issue")->where($where)->order("created_at", "desc")->limit($pageNumber, $pageSize)->select();
            $count = db("bof_issue")->where($where)->count();

            $num = 0;
            foreach ($list as $k => $v) {
                $num++;
                $list[$k]["created_at"] = date("Y-m-d H:i:s", $v["created_at"]);
                $list[$k]["bs"] = $v["status"] == 1 ? "公司发行" : "公司回购";
                $list[$k]["id"] = $num;
            }

            $return['rows'] = $list;
            $return['total'] = $count;
            return json($return);
        }

        return $this->fetch("issue/bofrecord");
    }

    public function message()
    {
        if (Request()->isAjax()) {
            $where = [];
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;

            if (!empty($param['code'])) {
                $where['code'] = $param['code'];
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
            $list = db("message")->where($where)->order("created_at", "desc")->limit($pageNumber, $pageSize)->select();
            $count = db("message")->where($where)->count();

            $num = 1;
            foreach ($list as $k => $v) {
                $list[$k]["created_at"] = date("Y-m-d H:i:s", $v["created_at"]);
                $list[$k]["content"] = $v["content"] ? substr($v["content"], 0, 21) . "..." : "";
                $operate = [
                    '查看详情' => "javascript:messageOne('" . $v['id'] . "')",
                ];
                $list[$k]['operate'] = showOperate($operate);
                $list[$k]['num'] = $num;
                $num++;
            }

            $return['rows'] = $list;
            $return['total'] = $count;
            return json($return);
        }
        return $this->fetch("issue/message");
    }

    public function messageOnes()
    {
        $messageOne = db("message")->where(["id" => input("id")])->find();
        $messageOne["created_at"] = date("Y-m-d H:i:s", $messageOne["created_at"]);
        return ajax(0, "ok", $messageOne);
    }

}