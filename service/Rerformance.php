<?php

namespace Service;

use think\Hook;


/**
 * 给激活用户的接点人左右增加业绩
 * User: yangqiu
 * Date: 2018/1/9
 * Time: 17:57
 */
class Rerformance
{

    const USER = "users";

    private $uid;#用户id
    private $level;#用户级别 1,2,3,4,5,6,7
    private $flag;#升级标志

    public function __construct($uid, $level,$flag = 0)
    {   

        $this->uid = $uid;
        $this->level = $level;
        $this->flag = $flag;
    }

    public function loadUser() #接点人nid 位置region 1左 2
    {


        #该用户的业绩
        $torerfor = $this->userLevel();

        if ($this->flag <= 0) {
            #该用户的业绩
            $torerfor = $this->userLevel();
        } else {
            #升级的单子
            $torerfor = $this->flag;
        }

        #查询用用户的位置

        $userRegion = db(self::USER)->where(["id" => $this->uid])->field(["region", "nid"])->find();
        $ob = $this->userUp($userRegion, $torerfor);
        $uid = $this->uid;
        Hook::listen('boom', $uid);
        return $ob;

    }

    #通过用户级别获取对应的业绩
    public function userLevel()
    {
        $rerfor = [0, 100, 300, 500, 1000, 3000, 5000, 10000];
        return $rerfor[$this->level];
    }

    #查询该用户的所有接点人
    public function userUp($userRegion, $torerfor)
    {
        $userOne = db(self::USER)->where("id", $userRegion["nid"])->field(["class","nid", "region", "left_all_ach", "right_all_ach", "left_ach", "right_ach"])->find();
        if (!$userOne) {
            return "执行完毕1!";
        }

        if(!in_array($userOne["class"], [0,1,2,3,4,5,6,7])){
            return "执行完毕2!";
        }
        $data = [];
        if ($userRegion["region"] == 1) {
            $data["left_all_ach"] = $userOne["left_all_ach"] += $torerfor;
            $data["left_ach"] = $userOne["left_ach"] += $torerfor;
        } else {
            $data["right_all_ach"] = $userOne["right_all_ach"] += $torerfor;
            $data["right_ach"] = $userOne["right_ach"] += $torerfor;
        }
        $ob = db(self::USER)->where("id", $userRegion["nid"])->update($data);
        if ($ob) {
            return $this->userUp($userOne, $torerfor);
        } else {
            return "修改失败";
        }

    }

}