<?php
/**
 * Created by PhpStorm.
 * User: ovo
 * Date: 2017/7/21
 * Time: 上午9:32
 */

namespace app\home\controller;

use think\Controller;

class Base extends Controller
{
    function _initialize()
    {
        $uid = session('home.user')["id"];
        $ob = db("users")->where(["id"=>$uid])->find();
        if (!$ob) {
            json(['status' => '10000', 'message' => '您还没有登录'])->send();
            exit;
        }

        #查询当前销售总量
        $bofAll = db(Bofsell::USER)->where(["id" => $uid])->field(["bof_all", "class", "balance", "reg_score"])->find();
        #查询当前直推
        $userAll = db(Bofsell::USER)->where(["pid" => $uid])->field(["class"])->select();
        $zhi = $this->test1($userAll);

        $oo = db("user_upgrade")->where(["uid" => $uid])->find();

        if ((config("bofAll")[$bofAll["class"]]) * 0.8 <= $bofAll["bof_all"] && $zhi && $this->reg_score($bofAll) && !$oo) {
            json(['status' => '20000', 'message' => '达到原点复位条件']);
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

}