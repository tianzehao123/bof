<?php

namespace Service;

/**
 * Created by 激活用户 调用 直接分配bof如果没有则加入队列.
 * User: yangqiu
 * Date: 2018/1/9
 * Time: 17:57
 */
class Useractivate
{
    const USER = "users";
    const ISSUE = "bof_issue";
    const ACCOUNT = "account";
    const RANK = "bof_rank";
    const DEAL = "bof_deal";
    const CONFIG = "config";
    const DISC = "disc";


    private $uid;#用户id
    private $con;#当前股价
    private $flag;#激活为空 升级不为空
    private $money;#该分配积分

    public function __construct($uid, $con=0, $money = 0, $flag = "")
    {
        if (!db(self::DISC)->count()) {
            $this->con = db(self::CONFIG)->where(["name" => "current_price"])->value("value");
        } else {
            $this->con = db(self::DISC)->order("id", "desc")->find()["market_price"];//TODO sql_disc大盘表
        }
        $this->uid = $uid;
        $this->flag = $flag;
        $this->money = $money;
    }

    public function loadUser()
    {
        $where = [];
        $where["id"] = $this->uid;
        // if (empty($this->flag)) {
        //     $where["status"] = 3;
        // } else {
        //     $where["status"] = 1;
        // }
        $where["islock"] = 1;
        // dd($where);
        $userClass = db(self::USER)->where($where)->field(["class", "balance", "id"])->find();
        if (!$userClass) {
            return "该用户不存在!";
        }
        if ($userClass["balance"] > 0 && empty($this->flag)) {
            return "该用户已经分配过BOF了!";
        }

        if (db(self::RANK)->where(["uid" => $this->uid, "status" => 1])->find()) {
            return "正在列队等待分配bof!";
        }

        if (empty($this->flag)) {
            $bofPrice = $this->levelUser($userClass);
        } else {
            $bofPrice = $this->levelUser($userClass, $this->money);
        }
        if($bofPrice <= 0){
            return "免费会员,无需分配";
        }
        $ob = $this->bofIssue($bofPrice);
        if ($ob == 1) {
            return $this->useraccount($bofPrice);
        } else {
            return "已加入队列";
        }
    }

    #计算补差升级bof
    public function compensation($uid)
    {
        $upgrade = db("user_upgrade")->where(["uid" => $uid])->find();

        $num = $this->levelUser($upgrade["class_at"]) - $this->levelUser($upgrade["class"]);
        return $num;
    }

    #从当前发行的bof中减去此次分配的bof
    public function bofIssue($bofPrice)
    {
        $bofissuet = db(self::ISSUE)->where(["status" => 1])->order("id", "desc")->field(["bof_num", "id"])->find();
        #如果当前剩余的bof不够  则加入列队 TODO
        if ($bofissuet["bof_num"] < $bofPrice) {
            $resAdd = db(self::RANK)->insert([
                "uid" => $this->uid,
                "ele_score" => $bofPrice,
                "market_price" => $this->con,
                "created_at" => time(),
            ]);
            if ($resAdd) {
                #返还电子积分
                $res = db(self::USER)->where(["id" => $this->uid])->setInc("ele_score", $bofPrice);
                if ($res) {
                    $res1 = db(self::ACCOUNT)->insert([
                        "uid" => $this->uid,
                        "score" => $bofPrice,
                        "cur_score" => db(self::USER)->where(["id" => $this->uid])->value("ele_score"),
                        "remark" => "电子积分",
                        "class" => 1,
                        "is_add" => 1,
                        "type" => 5,
                        "source" => 14,
                        "created_at" => time(),
                    ]);
                    if ($res1) {
                        return 2;
                    }
                }
            }
        }
        if (!$bofissuet) {
            return "该信息有误,请稍后重试";
        }
        $data["bof_num"] = $bofissuet["bof_num"] -= $bofPrice;
        $res = db(self::ISSUE)->where(["id" => $bofissuet["id"]])->update($data);
        if ($res) {
            return 1;
        }
    }

    #通过级别获取相对应的bof
    public function levelUser($class, $money = 0)
    {
        $bofNum = 0;
        $class = $class["class"];
        switch ($class) {
            case 0:
                $bofNum = $money ?? $money * 0.5 / $this->con;
                break;
            case 1:
                $bofNum = (!$money) ? 100 * 0.5 / $this->con : $money * 0.5 / $this->con;
                break;
            case 2:
                $bofNum = (!$money) ? 300 * 0.52 / $this->con : $money * 0.52 / $this->con;
                break;
            case 3:
                $bofNum = (!$money) ? 500 * 0.54 / $this->con : $money * 0.54 / $this->con;
                break;
            case 4:
                $bofNum = (!$money) ? 1000 * 0.56 / $this->con : $money * 0.56 / $this->con;
                break;
            case 5:
                $bofNum = (!$money) ? 3000 * 0.58 / $this->con : $money * 0.58 / $this->con;
                break;
            case 6:
                $bofNum = (!$money) ? 5000 * 0.60 / $this->con : $money * 0.60 / $this->con;
                break;
            case 7:
                $bofNum = (!$money) ? 10000 * 0.62 / $this->con : $money * 0.62 / $this->con;
                break;
        }
        return round($bofNum,6);
    }

    public function useraccount($account)
    {
        #给该用户加上bof  然后存储到记录表
        $userClass = db(self::USER)->where(["id" => $this->uid])->setInc("balance", $account);
        $userBalance = db(self::USER)->where(["id" => $this->uid])->value("balance");
        if ($userClass) {
            $res = db(self::ACCOUNT)->insert([
                "uid" => $this->uid,
                "cur_score" => $userBalance,
                "score" => $account,
                "remark" => "系统分配蓝海积分",
                "class" => 2,
                "is_add" => 1,
                "type" => 8,
                "source" => 13,
                "created_at" => time(),
            ]);
            #此处增加买入记录
            $res1 = db(self::DEAL)->insert([
                "uid" => $this->uid,
                "order_sn" => orderNumBof(),
                "sell_price" => $this->con,
                "sell_num" => $account,
                "status" => 2,
                "type" => 1,
                "created_at" => time(),
                "updated_at" => time()
            ]);
            if ($res && $res1) {
                return "已激活";
            }
        }
    }
}