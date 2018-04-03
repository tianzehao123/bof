<?php

namespace Service;

use think\Db;

/**
 * bof涨幅
 * User: yangqiu
 * Date: 2018/1/9
 * Time: 17:57
 */
class Stock
{
    const USER = "users";
    const DISC = "disc";
    const CONFIG = "config";
    const ISSUE = "bof_issue";
    const ACCOUNT = "account";
    const DEAL = "bof_deal";
    const PER = "per";

    private $bofCurrent;#当前bof股价
    private $bofCurrentAll;#当前bof总数
    private $bofCurrentNum;#当前bof数量
    private $market;#bof股价上涨标准
    private $original;#原始股价
    private $per;#综合业绩

    public function __construct()
    {
        if (!db(self::DISC)->count()) {
            $this->bofCurrent = db(self::CONFIG)->where(["name" => "current_price"])->value("value");
        } else {
            $this->bofCurrent = db(self::DISC)->order("id", "desc")->find()["market_price"];//TODO sql_disc大盘表
        }
        $this->market = db(self::CONFIG)->where(["name" => "market"])->value("value");#上涨多少 升值
        $this->bofCurrentAll = db(self::CONFIG)->where(["name" => "bof_all"])->value("value");
        $this->bofCurrentNum = db(self::ISSUE)->where(["status" => 1])->order("id", "desc")->value("bof_num");
        $this->original = db(self::CONFIG)->where(["name" => "current_price"])->value("value");
        $this->per = db(self::PER)->sum("num");
    }

    #计算应该上涨的股票价格
    public function loadAccount()
    {
        $accountWhere = [];
        #获取当前总销售额度(注册积分)
        //$reg_score = db(self::USER)->sum("reg_score");
        $maxId = db(self::ACCOUNT)->order("id", "desc")->value("id");
        if (!$maxId) {
            return false;
        }
        //$accountWhere["id"] = ["<=",$maxId];
        $accountWhere["is_add"] = 2;
        $accountWhere["source"] = ["IN", [8, 11]];
        $reg_score = db(self::ACCOUNT)->where($accountWhere)->sum("score");
        $nn = !$this->per ? 0 : $this->per;
        $reg_score = abs($reg_score) - $nn;
        if ($reg_score < 0) {
            return false;
        }
        #原始股值
        $original = $this->original;
        #计算应该上涨的股值
        $reg_bof_num = floor($reg_score / $this->market) * $this->market * $original / 1000000;
        if (floor(($reg_bof_num + $original) * 100000) == floor($this->bofCurrent * 100000)) {
            return "该轮已经增值过了!";
        }
        #上涨股值+当前股值
        $bof_disc = $reg_bof_num + $original;
        #如果当前股值<0.4 则继续上涨
        if ($this->bofCurrent < 0.4) {
            $data["market_price"] = $bof_disc >= 0.4 ? 0.4 : $bof_disc;
            $data["created_at"] = time();
            $res = db(self::DISC)->insert($data);
            if ($res) {
                $this->usersMarket();
                return "股价上涨成功!,执行半自动托管成功!(若满足拆分条件,则直接拆分)";
            }
        } else {
            #就检查当前BOF是否售空 若售空则直接进行拆分
            if (!$this->bofCurrentNum) {
                return $this->Resolution($reg_score);
            }
        }
    }

    #半自动托管
    public function usersMarket()
    {
        #查询所有半托管的用户卖出记录
        $userBof = db(self::DEAL)->where(["pay_status" => 2])->field("uid")->select();
        #获取当前股价
        $guV = $this->bofCurrent;

        foreach ($userBof as $v) {
            $userOne = db(self::USER)->where(["id" => $v["uid"]])->
            field(["id", "bof_num", "class", "balance", "balance_return",
                "game_score", "pay_score", "fund_gold", "con_score", "prize_score", "bof_num", "balance"])->find();
            if ($userOne["bof_num"] > 8) {
                echo "该用户已经自动销售8次";
                continue;
            }
            if ($userOne["class"] == 0) {
                continue;
            }
            $bofOne = db(self::DEAL)->where(["uid" => $v["uid"], "type" => 2])->order("created_at", "desc")
                ->value("sell_price");

            if (round($this->bofCurrent - $bofOne, 4) < 0.02) {
                continue;
            }
            #计算下次拆分的bof  bof+回购的bof
            $bofNum = $userOne["balance"] * 2 - $this->bofCapping($userOne["class"]);
            if ($bofNum < 0) {
                continue;
            }

            #半托管销售
            if ($guV == 0.22) {
                #1
                $num = $this->halfMarket(1) / 100;
                $this->calculate($bofNum, $num, $userOne);
            } else if ($guV == 0.24) {
                #2
                $num = $this->halfMarket(2) / 100;
                $this->calculate($bofNum, $num, $userOne);
            } else if ($guV == 0.26) {
                #3
                $num = $this->halfMarket(3) / 100;
                $this->calculate($bofNum, $num, $userOne);
            } else if ($guV == 0.28) {
                #4
                $num = $this->halfMarket(4) / 100;
                $this->calculate($bofNum, $num, $userOne);
            } else if ($guV == 0.30) {
                #5
                $num = $this->halfMarket(5) / 100;
                $this->calculate($bofNum, $num, $userOne);
            } else if ($guV == 0.32) {
                #6
                $num = $this->halfMarket(6) / 100;
                $this->calculate($bofNum, $num, $userOne);
            } else if ($guV == 0.34) {
                #7
                $num = $this->halfMarket(7) / 100;
                $this->calculate($bofNum, $num, $userOne);
            }
            //  else {
            //     //echo "当前股值不能进行半自动托管";
            //     continue;
            // }
        }
        return true;
    }

    #拆分
    public function Resolution($reg_score)
    {
        ini_set('memory_limit', '2560M');
        #获取所有用户的bof  并且对其进行bof拆分  添加记录
        $userClass = db(self::USER)->where(["status" => 1, "islock" => 1, "out_status" => 1, "balance" => ['>', 0], "class" => ['>', 0]])->field(["id", "balance", "bof_all"])->select();
        $arr = [];
        Db::startTrans();
        try {
            foreach ($userClass as $v) {
                $userOne = db(self::USER)->where(["id" => $v["id"]])->field(["balance", "class", "id"])->find();
                if ($userOne["class"] == 0) {
                    continue;
                }
                if ($v["balance"] >= $this->bofCapping($userOne["class"]) ||
                    $v["bof_all"] >= $this->bofCappingAll($userOne["class"])) {
                    $data["out_status"] = 2;#出局;注:出局后不再享受拆分
                }
                $bof = $v["balance"] * 2;#拆分 2倍 并且重置用户手动或半托管的次数
                $data["balance"] = $bof;#获取拆分后的bof
                $data["bof_num"] = 0;#重置销售次数和
                $data["pattern"] = 0;#重置销售方式
                $res = db(self::USER)->where(["id" => $v["id"]])->update($data);
                $data1["uid"] = $v["id"];
                $data1["score"] = $bof / 2;
                $data1["cur_score"] = $bof;
                $data1["remark"] = "BOF拆分";
                $data1["class"] = 2;
                $data1["is_add"] = 1;
                $data1["type"] = 8;
                $data1["source"] = 16;
                $data1["created_at"] = time();
                $data1["updated_at"] = time();
                $arr[] = $data1;
                file_put_contents("test.txt", "用户:" . $v["id"] . "拆分完毕\n", FILE_APPEND);
                // if (!$res) {
                //     Db::rollback();
                // }
            }
            $res1 = db(self::ACCOUNT)->insertAll($arr);
            $res2 = db(self::PER)->insert(["num" => $reg_score]);
            if (!$res1 || !$res2) {
                Db::rollback();
            }
            #拆分成功后股价重新回到原始股价0.2
            db(self::DISC)->insert(["market_price" => $this->original, "created_at" => time()]);
            Db::commit();
        } catch (\Exception $e) {
            Db::rollback();
        }

        return true;
    }

    #账户余额封顶->超出即出局
    public function bofCapping($cc)
    {
        $arr = ["", 1500, 4500, 7500, 15000, 45000, 65000, 130000];
        return $arr[$cc];
    }

    #账户总量封顶 && 卖出==总量封顶
    public function bofCappingAll($cc)
    {
        $arr = ["", 3000, 9000, 15000, 30000, 90000, 160000, 320000];
        return $arr[$cc];
    }

    #半自动销售 参数设置
    public function halfMarket($cc)
    {
        $half = ["", 18, 18, 16, 15, 13, 12, 10, 9];
        return $half[$cc];
    }

    #计算1-7等级下次溢出部分的bof(余额+回购)
    public function calculate($bofNum, $num, $class)
    {
        $arr = [
            '1' => 50,
            '2' => 52,
            '3' => 54,
            '4' => 56,
            '5' => 58,
            '6' => 60,
            '7' => 62
        ];

        bcscale(6);
        $num = bcmul($bofNum, $num);
        #存储卖出记录
        $sell_num = bcmul($num, "0.9");#卖出量
        #慈善基金
        $reward_score = bcmul(bcmul($sell_num, "0.05"), $bofPrice);
        #游戏积分
        $game_score = $reward_score;
        #原点回购
        $bof_break = bcmul(bcmul($sell_num, "0.3"), (config("Level")[$userOne["class"]] / 100));
        #消费积分
        $nineSix = bcmul(bcmul($sell_num, "0.6"), $bofPrice);
        $con_score = bcmul($nineSix, "0.3");
        #复投积分
        $nineSixSeven = bcmul($nineSix, "0.7");
        #购物积分
        $pay_score = bcmul($nineSixSeven, "0.2");
        #奖励积分
        $prize_score = bcmul($nineSixSeven, "0.7");

        $account["game_score"] = $data1["game_score"] = $class["game_score"] += $game_score;
        $account["reward_score"] = $data1["fund_gold"] = $class["fund_gold"] += $reward_score;//TODO
        $account["con_score"] = $data1["con_score"] = $class["con_score"] += $con_score;
        $account["prize_score"] = $data1["prize_score"] = $class["prize_score"] += $prize_score;
        $account["pay_score"] = $data1["pay_score"] = $class["pay_score"] += $pay_score;
        $data1["balance"] = $class["balance"] -= $num;
        $data1["bof_num"] = $class["bof_num"] += 1;#卖出一次就+1  默认0
        $data1["balance_return"] = $class["balance_return"] + $bof_break;#balance_return  回购数量
        $uid = $class["id"];
        if (!$this->accountrecord($account, $uid)) {
            return "请稍后重试!";
        }
        $res1 = db(self::USER)->where("id", $class["id"])->update($data1);
        if ($res1) {
            return true;
        }

    }

    public function accountrecord($data, $uid = 0)
    {
        $newdata = [];
        $userOne = db(self::USER)->where(["id" => $uid])->field(["game_score", "balance", "fund_gold", "con_score", "prize_score"])->find();
        $newdata["uid"] = $uid;
        $newdata["class"] = 1;
        $newdata["is_add"] = 1;
        $newdata["type"] = 2;
        $newdata["source"] = 16;
        if (isset($data["game_score"])) {
            $newdata["cur_score"] = $data["game_score"];
            $newdata["remark"] = "游戏积分-BOF半自动卖出收益";
            $newdata["is_add"] = 1;
            $newdata["type"] = 2;
            $newdata["created_at"] = date("Y-m-d H:i:s", time());
            db("account")->insert($newdata);
        }
        if (isset($data["reward_score"])) {
            $newdata["cur_score"] = $data["reward_score"];
            $newdata["remark"] = "基金币-BOF半自动卖出收益";
            $newdata["is_add"] = 1;
            $newdata["type"] = 9;
            $newdata["created_at"] = date("Y-m-d H:i:s", time());
            db("account")->insert($newdata);
        }
        if (isset($data["con_score"])) {
            $newdata["cur_score"] = $data["con_score"];
            $newdata["remark"] = "消费积分-BOF半自动卖出收益";
            $newdata["created_at"] = date("Y-m-d H:i:s", time());
            $newdata["is_add"] = 1;
            $newdata["type"] = 4;
            db("account")->insert($newdata);
        }
        if (isset($data["prize_score"])) {
            $newdata["cur_score"] = $data["prize_score"];
            $newdata["remark"] = "奖励积分-BOF半自动卖出收益";
            $newdata["created_at"] = date("Y-m-d H:i:s", time());
            $newdata["is_add"] = 1;
            $newdata["type"] = 3;
            db(self::ACCOUNT)->insert($newdata);
        }
        if (isset($data["pay_score"])) {
            $newdata["score"] = $data["pay_score"];
            $newdata["cur_score"] = $userOne["pay_score"];
            $newdata["remark"] = "购物积分-BOF半自动卖出收益";
            $newdata["created_at"] = time();
            $newdata["is_add"] = 1;
            $newdata["type"] = 7;
            db(self::ACCOUNT)->insert($newdata);
        }
        return true;
    }
}