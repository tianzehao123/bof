<?php

namespace app\home\controller;

use think\Controller;

class Rank extends Controller
{
    const USERS = "users";#用户表
    const RANK = "bof_rank";#列队表
    const CONFIG = "config";#配置表
    const BOFISSUE = "bof_issue";#BOF余量
    const ACCOUNT = "account";#BOF余量

    #处理列队->定时任务 TODO
    public function index()
    {
        #获取加入列队的时间戳 以及等待的时间
        #列队列表
        $rank = db(self::RANK)->where(["status" => 1, "iswhe" => 1])->select();
        if (!$rank) {
            echo "当前系统列队中没有数据";
            return;
        }
        #获取系统限制的等待时间
        $astrict = db(self::CONFIG)->where(["name" => "bof_day"])->value("value");
        foreach ($rank as $v) {

            #获取当前剩余的bof
            $bofNum = db(self::BOFISSUE)->where(["status" => 1])->order("id", "desc")->value("bof_num");
            #获取加入列队的时间 +限制后的时间
            $time = $v["created_at"] + ($astrict * 24 * 60 * 60);
            if (time() >= $time) {
                #排到需要执行的方法
                if ($bofNum) {
                    $ob = $this->userBof($v);
                    #直接发放 status改2
                    $res = db(self::RANK)->where(["uid" => $v['uid']])->update(["status" => 2]);
                    if ($res) {
                        if ($ob) {
                            echo "列队直接发放bof执行完毕!" . "<br>";
                            continue;
                        }
                    } else {
                        continue;
                    }
                } else {
                    #改状态 下次发行的时候进行发放 iswhe改为2
                    $data["iswhe"] = 2;
                    //$data["status"] = 2;
                    $res = db(self::RANK)->where(["uid" => $v['uid']])->update($data);
                    if ($res) {
                        echo "当前系统bof不足以排列到优先级发放!" . "<br>";
                        continue;
                    }
                }
            } else {
                echo "还没有到处理时间!";
                continue;
            }
        }
    }

    #发放bof的方法,
    public function userBof($ob)
    {
        #更新用户bof
        $res = db(self::USERS)->where(["id" => $ob["uid"]])->setInc("balance", $ob["ele_score"]);
        if (db(self::USERS)->where(["id" => $ob["uid"]])->value("ele_score") != 0) {
            $res = db(self::USERS)->where(["id" => $ob["uid"]])->update(["ele_score" => 0]);
        }

        if ($res) {
            #电子积分记录
            $res2 = db(self::ACCOUNT)->insert([
                "uid" => $ob["uid"],
                "score" => $ob["ele_score"],
                "remark" => "电子积分转蓝海积分",
                "class" => 1,
                "is_add" => 2,
                "type" => 5,
                "source" => 14,
                "created_at" => time(),
            ]);
            #bof记录
            $res1 = db(self::ACCOUNT)->insert([
                "uid" => $ob["uid"],
//                "cur_score" => $ob["ele_score"],
                "cur_score" => db(self::USERS)->where(["id" => $ob["uid"]])->value("balance"),
                "score" => $ob["ele_score"],
                "remark" => "系统分配蓝海积分",
                "class" => 2,
                "is_add" => 1,
                "type" => 8,
                "source" => 13,
                "created_at" => time(),
            ]);
            $bofNum = db(self::BOFISSUE)->where(["status" => 1])->order("id", "desc")->field(["id", "bof_num"])->find();
            $res3 = db(self::BOFISSUE)->where(["id" => $bofNum["id"]])->setDec("bof_num", $ob["ele_score"]);
            if ($res1 && $res2 && $res3) {
                return true;
            }
        }
    }
}