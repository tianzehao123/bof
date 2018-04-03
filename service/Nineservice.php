<?php

namespace Service;
use app\home\controller\Distribution;
use think\Session;
/**
 * 9级分销
 * User: yangqiu
 * Date: 2018/1/9
 * Time: 17:57
 */
class Nineservice
{
    const USER = "users";
    const CONFIG = "config";

    private $uid;#被激活用户的id
    private $flag;#考虑到升级

    public function __construct($uid,$flag=0)
    {
        $this->uid = $uid;
        $this->flag = $flag;

    }

    public function loadUser()
    {
        ini_set('memory_limit', '2560M');
        #获取该用户的身份等级
        $userOne = db(self::USER)->where(["id" => $this->uid, "status" => 1, "out_status" => 1, "islock" => 1])
            ->field(["class", "pid", "id",'code'])->find();
        $userOne["class"] == 0 ? 1 : $userOne["class"];
        if(!in_array($userOne["class"], [0,1,2,3,4,5,6,7])){
            return "22222";
        }
        
        #获取该用户的9代上级
        if ($userOne["pid"]) {
            $num = 0;
            $upUser = $this->upUserAll($userOne["id"], []);
            $arr = [];
            #由于是9级分销 所以只循环9次
            for ($i = 0; $i < count($upUser); $i++) {
                if ($upUser[$i]) {
                    $userOne_nine = db(self::USER)->where(["id" => $upUser[$i]])->field(["class", "prize_score", "id"])->find();
                    if ($i == 0) {
                        switch ($userOne_nine["class"]) {
                            case 0:
                                #奖励普卡1代 0 == 1
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "puone")->value("value") / 100);
                                break;
                            case 1:
                                #奖励普卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "puone")->value("value") / 100);
                                break;
                            case 2:
                                #奖励银卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "yinone")->value("value") / 100);
                                break;
                            case 3:
                                #奖励金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "jinone")->value("value") / 100);
                                break;
                            case 4:
                                #奖励白金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "baijinone")->value("value") / 100);
                                break;
                            case 5:
                                #奖励黑金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "heijinone")->value("value") / 100);
                                break;
                            case 6:
                                #奖励钻卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "zuanone")->value("value") / 100);
                                break;
                            case 7:
                                #奖励蓝钻1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "lanzuanone")->value("value") / 100);
                                break;
                            default:
                                $num = 0;
                                break;
                        }
                    }
                    if ($i == 1) {
                        switch ($userOne_nine["class"]) {
                            case 0:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 1:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 2:
                                #奖励银卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "yintwo")->value("value") / 100);
                                break;
                            case 3:
                                #奖励金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "jintwo")->value("value") / 100);
                                break;
                            case 4:
                                #奖励白金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "baijintwo")->value("value") / 100);
                                break;
                            case 5:
                                #奖励黑金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "heijintwo")->value("value") / 100);
                                break;
                            case 6:
                                #奖励钻卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "zuantwo")->value("value") / 100);
                                break;
                            case 7:
                                #奖励蓝钻1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "lanzuantwo")->value("value") / 100);
                                break;
                            default:
                                $num = 0;
                                break;
                        }
                    }
                    if ($i == 2) {
                        switch ($userOne_nine["class"]) {
                            case 0:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 1:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 2:
                                #奖励银卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "yinthree")->value("value") / 100);
                                break;
                            case 3:
                                #奖励金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "jinthree")->value("value") / 100);
                                break;
                            case 4:
                                #奖励白金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "baijinthree")->value("value") / 100);
                                break;
                            case 5:
                                #奖励黑金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "heijinthree")->value("value") / 100);
                                break;
                            case 6:
                                #奖励钻卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "zuanthree")->value("value") / 100);
                                break;
                            case 7:
                                #奖励蓝钻1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "lanzuanthree")->value("value") / 100);
                                break;
                            default:
                                $num = 0;
                                break;
                        }
                    }
                    if ($i == 3) {
                        switch ($userOne_nine["class"]) {
                            case 0:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 1:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 2:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 3:
                                #奖励金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "jinfour")->value("value") / 100);
                                break;
                            case 4:
                                #奖励白金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "baijinfour")->value("value") / 100);
                                break;
                            case 5:
                                #奖励黑金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "heijinfour")->value("value") / 100);
                                break;
                            case 6:
                                #奖励钻卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "zuanfour")->value("value") / 100);
                                break;
                            case 7:
                                #奖励蓝钻1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "lanzuanfour")->value("value") / 100);
                                break;
                            default:
                                $num = 0;
                                break;
                        }
                    }
                    if ($i == 4) {
                        switch ($userOne_nine["class"]) {
                            case 0:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 1:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 2:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 3:
                                #奖励金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "jinfive")->value("value") / 100);
                                break;
                            case 4:
                                #奖励白金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "baijinfive")->value("value") / 100);
                                break;
                            case 5:
                                #奖励黑金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "heijinfive")->value("value") / 100);
                                break;
                            case 6:
                                #奖励钻卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "zuanfive")->value("value") / 100);
                                break;
                            case 7:
                                #奖励蓝钻1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "lanzuanfive")->value("value") / 100);
                                break;
                            default:
                                $num = 0;
                                break;
                        }
                    }
                    if ($i == 5) {
                        switch ($userOne_nine["class"]) {
                            case 0:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 1:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 2:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 3:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 4:
                                #奖励白金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "baijinsix")->value("value") / 100);
                                break;
                            case 5:
                                #奖励黑金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "heijinsix")->value("value") / 100);
                                break;
                            case 6:
                                #奖励钻卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "zuansix")->value("value") / 100);
                                break;
                            case 7:
                                #奖励蓝钻1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "lanzuansix")->value("value") / 100);
                                break;
                            default:
                                $num = 0;
                                break;
                        }
                    }
                    if ($i == 6) {
                        switch ($userOne_nine["class"]) {
                            case 0:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 1:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 2:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 3:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 4:
                                #奖励白金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "baijinseven")->value("value") / 100);
                                break;
                            case 5:
                                #奖励黑金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "heijinseven")->value("value") / 100);
                                break;
                            case 6:
                                #奖励钻卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "zuanseven")->value("value") / 100);
                                break;
                            case 7:
                                #奖励蓝钻1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "lanzuanseven")->value("value") / 100);
                                break;
                            default:
                                $num = 0;
                                break;
                        }
                    }
                    if ($i == 7) {
                        switch ($userOne_nine["class"]) {
                            case 0:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 1:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 2:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 3:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 4:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 5:
                                #奖励黑金卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "heijineight")->value("value") / 100);
                                break;
                            case 6:
                                #奖励钻卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "zuaneight")->value("value") / 100);
                                break;
                            case 7:
                                #奖励蓝钻1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "lanzuaneight")->value("value") / 100);
                                break;
                            default:
                                $num = 0;
                                break;
                        }
                    }
                    if ($i == 8) {
                        switch ($userOne_nine["class"]) {
                            case 0:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 1:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 2:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 3:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 4:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 5:
                                #奖励银卡1代
                                $num = 0;
                                break;
                            case 6:
                                #奖励钻卡1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "zuannine")->value("value") / 100);
                                break;
                            case 7:
                                #奖励蓝钻1代
                                $num = $this->levelScore($userOne["class"],$this->flag) * (db(self::CONFIG)->where("name", "lanzuannine")->value("value") / 100);
                                break;
                            default:
                                $num = 0;
                                break;
                        }
                    }
                }
                if(restriction($upUser[$i])){
                    continue;
                    
                }

               $Distrbution = new Distribution();

               if(!empty($num) && isset($i)){

                   if($i <= 8){
                       $remarks = ($i + 1) . "类 A类奖结算 ===来自===".$userOne['code'];
                       $result = $Distrbution->implement($upUser[$i],$this->uid,$num,2,$remarks);
                   }
               }

           }

        }
    
    }

    #通过登记获得每个用户的积分
    public function levelScore($cc,$flag)
    {
        $arr = ["", 100, 300, 500, 1000, 3000, 4900, 10000];
        if($flag > 0){
            return $flag;
        }else{
            return $arr[$cc];
        }

    }

    #通过用户id获取该用户9代上级
    public function upUserAll($pid, $arr)
    {
        $pids = db(self::USER)->where(["id" => $pid, "status" => 1, "out_status" => 1, "islock" => 1])->value("pid");

        if ($pids) {
            $arr[] = $pids;
            return $this->upUserAll($pids, $arr);
        }
        return $arr;
    }
}