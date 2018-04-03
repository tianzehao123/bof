<?php
namespace app\home\behavior;

use app\backend\model\UserModel;
use app\backend\model\AccountModel;
use app\backend\model\Config;
use think\Db;
use think\Exception;
use think\Log;
use app\home\controller\Distribution;
/**
 * Class Boom 碰撞奖 贡献奖
 * @package app\home\behavior
 */
class Boom {
    public function run(&$param)
    {
        $this->isBoom($param);
    }

    /** 判断是否发生碰撞
     * @param $order 订单
     */
    public function isBoom($uid)
    {
        ini_set("memory_limit", "2G");
        #获取下单人
        $user = UserModel::find($uid);
        if (empty($user)){
            return json(['status'=>2,'message'=>'用户id传入有误']);
        }

        if(!in_array($user["class"], [1,2,3,4,5,6,7])){
            return json(['status'=>2,'message'=>'免费会员没有B奖']);
        }
        #查找此人的一条线上级
        $up_users = $this->all_user($user->nid);
        $upUsers = UserModel::where('id','in',$up_users)->select();
        #计算是第几碰？
        $num = 0;
        foreach ($upUsers as $key => $upUser){
            #判断左右区业绩是否满足碰撞条件
            if ($upUser->left_ach == 0 || $upUser->right_ach == 0){
                continue;
            }
            #判断用户是否出局 或者 被锁定
            if ($upUser->out_status == 2 || $upUser->islock == 2){
                continue;
            }
            #判断总收益是否大于150w  若有则出局
            if(restriction($upUser->id)){
                continue;
            }
            $num++;
            #产生碰撞奖
            $this->boomScore($upUser,$user,$num);
        }
    }

    /** 碰撞奖
     * @param $user 获奖人
     * @param $fromUser 来自xx
     */
    public function boomScore($user,$fromUser,$num)
    {
        #日志初始化
        Log::init([
            'type'  =>  'File',
            'path'  =>  '../logs/'
        ]);
        #开启事务
        Db::startTrans();
        try{
            #碰撞金额
            $money = 0;
            if ($user->left_ach >= $user->right_ach){
                $money = $user->right_ach;
            }else{
                $money = $user->left_ach;
            }
            # 修改用户左右区结余业绩
            $res1 = UserModel::where('id',$user->id)->setDec('left_ach',$money);
            $res2 = UserModel::where('id',$user->id)->setDec('right_ach',$money);

            if (!$res1){
                throw new Exception("修改用户左区结余业绩异常");
            }
            if (!$res2){
                throw new Exception("修改用户右区结余业绩异常");
            }
            if ($money < 0){
                throw new Exception("结算金额有误");
            }
            #获取配比率
            $matchPercent = 0.6;//$this->getMatchPercent($fromUser);
            #获取对碰奖比例
            $boomPercent = $this->getBoomPercent($user);
            #碰撞处于哪一层的比例
            $layerPercent = $this->getLayerPercent($num);
            #奖励金额
            $score = $money * $matchPercent * $boomPercent * $layerPercent;
//            echo "原奖励金额".$score."</br>";
            #判断是否封顶
            $score = $this->isTopBoomScore($user,$score);
            if ($score > 0){
                #发放对碰奖
                $this->sendBoomScore($user,$fromUser,$score);
                #发放贡献奖
                $this->sendContributionScore($user,$score);
            }
//            echo "碰撞金额：".$money."---最终奖励金额：".$score."---配比率：".$matchPercent.
//                "---碰撞百分比：".$boomPercent."---碰撞层比例：".$layerPercent;
            #提交事务
            Db::commit();
            #写入日志
            Log::write("<<<碰撞完成 新人id：".$fromUser->id."--手机号：".$fromUser->phone." --碰撞金额：".$money."---配比率：".$matchPercent."---获奖人id：".$user->id."--碰撞时间：".date("Y-m-d H:i:s".time()).">>>");
        }catch(Exception $e){
            #回滚事务
            Db::rollback();
//            echo $e->getMessage();
            #写入日志
            Log::write("<<<--错误信息：". $e->getMessage() ."-->>>");
        }
    }

    #发放对碰奖
    public function sendBoomScore($redUser,$fromUser,$score)
    {
        // #用户当前奖励积分
        // $current_score = db('users')->where('id',$redUser->id)->value('prize_score');
        // # 插入记录表
        // $record = AccountModel::create([
        //     'uid' => $redUser->id,
        //     'score' => $score,
        //     'cur_score' =>$current_score + $score,
        //     'remark' => '平衡奖结算',
        //     'class' => 1,
        //     'is_add' => 1,
        //     'type' => 3,
        //     'source' =>2,
        //     'from_uid' => $fromUser->id,
        //     'created_at' => time(),
        // ]);
        // # 修改用户金额
        // $res = UserModel::where(['id'=>$redUser->id])->setInc('prize_score',$score);
        $remark = 'B类结算 ===来自=== '.$fromUser->code; 
        #TODO 发奖之后其他操作
        $Distribution =   new Distribution();
        $res = $Distribution->implement($redUser->id,$fromUser->id,$score,3,$remark);

        if (!$res){
            throw new Exception("更改用户B类异常");
        }
        #TODO 发奖之后其他操作

    }
    #判断对碰奖是否封顶
    public function isTopBoomScore($redUser,$score)
    {
        #获取此人今日拿到的碰撞奖总和
        $todayScore = AccountModel::where(['uid'=>$redUser->id,'source'=>2,'is_add'=>1,'type'=>3,'class'=>1])
            -> where('created_at','>',strtotime(date('Y-m-d ').'00:00:00'))
            -> where('created_at','<',strtotime(date('Y-m-d ').'23:59:59'))
            ->sum('score');
        switch ($redUser->class_s){
            case 1:
                if ($score + $todayScore >= Config::getConfigs('oneTopBoomScore')){
                    $score = Config::getConfigs('oneTopBoomScore') - $todayScore;
                }
                if ($score < 0){
                    $score = 0;
                }
                return $score;
                break;
            case 2:
                if ($score + $todayScore >= Config::getConfigs('twoTopBoomScore')){
                    $score = Config::getConfigs('twoTopBoomScore') - $todayScore;
                }
                if ($score < 0){
                    $score = 0;
                }
                return $score;
                break;
            case 3:
                if ($score + $todayScore >= Config::getConfigs('threeTopBoomScore')){
                    $score = Config::getConfigs('threeTopBoomScore') - $todayScore;
                }
                if ($score < 0){
                    $score = 0;
                }
                return $score;
                break;
            case 4:
                if ($score + $todayScore >= Config::getConfigs('fourTopBoomScore')){
                    $score = Config::getConfigs('fourTopBoomScore') - $todayScore;
                }
                if ($score < 0){
                    $score = 0;
                }
                return $score;
                break;
            case 5:
                if ($score + $todayScore >= Config::getConfigs('fiveTopBoomScore')){
                    $score = Config::getConfigs('fiveTopBoomScore') - $todayScore;
                }
                if ($score < 0){
                    $score = 0;
                }
                return $score;
                break;
            case 6:
                if ($score + $todayScore >= Config::getConfigs('sixTopBoomScore')){
                    $score = Config::getConfigs('sixTopBoomScore') - $todayScore;
                }
                if ($score < 0){
                    $score = 0;
                }
                return $score;
                break;
            case 7:
                if ($score + $todayScore >= Config::getConfigs('sevenTopBoomScore')){
                    $score = Config::getConfigs('sevenTopBoomScore') - $todayScore;
                }
                if ($score < 0){
                    $score = 0;
                }
                return $score;
                break;
        }
    }

    /**
     *     发放贡献奖
     * @param $user 获取碰撞奖的人
     * @param $score 碰撞奖金额
     */
    public function sendContributionScore($fromUser,$score)
    {
        #查找上5代
        ini_set('memory_limit','2G');
        $upUsers = $this->up_line_users($fromUser->id);

        foreach ($upUsers as $key => $upUser){
            #过滤掉当前获取碰撞奖的人
            if ($key == 0){
                continue;
            }
            $redUser = UserModel::find($upUser);
            if (empty($redUser)){
                continue;
            }
            if ($redUser->status != 1) {
                continue;
            }
            #第一层 都可以拿到奖
            if ($key == 1) {
                #获取贡献奖比例
                $percent = $this->getContributionPercentWithLevel($redUser->class);
                $score = $score * $percent;
                if ($score > 0){
                    #发放贡献奖
                    $this->setContributionScore($redUser,$fromUser,$score);
                }
            }
            #第二层 只有银卡及以上级别可以拿到奖
            if ($key == 2 && $redUser->class_s > 1){
                #获取贡献奖比例
                $percent = $this->getContributionPercentWithLevel($redUser->class);
                $score = $score * $percent;
                if ($score > 0){
                    #发放贡献奖
                    $this->setContributionScore($redUser,$fromUser,$score);
                }
            }
            #第三层 只有金卡及以上级别可以拿到奖
            if ($key == 3 && $redUser->class_s > 2){
                #获取贡献奖比例
                $percent = $this->getContributionPercentWithLevel($redUser->class);
                $score = $score * $percent;
                if ($score > 0){
                    #发放贡献奖
                    $this->setContributionScore($redUser,$fromUser,$score);
                }
            }
            #第四层 只有钻卡及以上级别可以拿到奖
            if ($key == 4 && $redUser->class_s > 5){
                #获取贡献奖比例
                $percent = $this->getContributionPercentWithLevel($redUser->class);
                $score = $score * $percent;
                if ($score > 0){
                    #发放贡献奖
                    $this->setContributionScore($redUser,$fromUser,$score);
                }
            }
            #第五层 只有蓝钻级别可以拿到奖
            if ($key == 5 && $redUser->class_s > 6){
                #获取贡献奖比例
                $percent = $this->getContributionPercentWithLevel($redUser->class);
                $score = $score * $percent;
                if ($score > 0){
                    #发放贡献奖
                    $this->setContributionScore($redUser,$fromUser,$score);
                }
            }
        }
    }
    #发放贡献奖封装
    public function setContributionScore($redUser,$fromUser,$score)
    {
        #用户当前奖励积分
        // $current_score = db('users')->where('id',$redUser->id)->value('prize_score');
        # 插入记录表
        // $record = AccountModel::create([
        //     'uid' => $redUser->id,
        //     'score' => $score,
        //     'cur_score' =>$current_score + $score,
        //     'remark' => '贡献奖结算',
        //     'class' => 1,
        //     'is_add' => 1,
        //     'type' => 3,
        //     'source' =>2,
        //     'from_uid' => $fromUser->id,
        //     'created_at' => time(),
        // ]);

        // # 修改用户金额
        // $res = UserModel::where(['id'=>$redUser->id])->setInc('prize_score',$score);

        $remark = 'C类结算 ===来自=== '.$fromUser->code; 
        #TODO 发奖之后其他操作
        $Distribution =   new Distribution();
        $res = $Distribution->implement($redUser->id,$fromUser->id,$score,4,$remark);
        if (!$res){
            throw new Exception("更改用户C类异常");
        }
    }

    /**
     *   根据身份获取贡献奖的比例
     */
    public function getContributionPercentWithLevel($level)
    {
        switch ($level) {
            case 1:{
                #获取奖金比例
                return $percent = Config::getConfigs('oneContributionPercent') / 100;
            }
            case 2:{
                return $percent = Config::getConfigs('twoContributionPercent') / 100;
            }
            case 3:{
                return $percent = Config::getConfigs('threeContributionPercent') / 100;
            }
            case 4:{
                return $percent = Config::getConfigs('fourContributionPercent') / 100;
            }
            case 5:{
                return $percent = Config::getConfigs('fiveContributionPercent') / 100;
            }
            case 6:{
                return $percent = Config::getConfigs('sixContributionPercent') / 100;
            }
            case 7:{
                return $percent = Config::getConfigs('sevenContributionPercent') / 100;
            }
            default:
                return 0;
                break;
        }
    }

    #一条线上所有的上级  根据直推关系 5代
    public function up_line_users($pid, $arr = array(), $num = -1)
    {
        if ($num >= 0) {
            $num++;
        } else {
            $num = 0;
        }
        if ($num == 6) {
            return $arr;
        }
        $up_id = UserModel::where('id', $pid)->value('id');
        $up_pid = UserModel::where('id', $pid)->value('pid');
        if (!empty($up_id) || $up_id > 0) {
            $arr[$num] = $up_id;
            if (!empty($up_pid) || $up_pid > 0) {
                return $this->up_line_users($up_pid, $arr, $num);
            }
        }
        return $arr;
    }
    #从下往上 -> 算出一条线上的所有人  根据接点关系 无限代
    public function all_user($cid, $arr = array(), $num = -1)
    {
        if ($num >= 0) {
            $num++;
        } else {
            $num = 0;
        }
        $up_id = UserModel::where('id', $cid)->value('id');
        $up_cid = UserModel::where('id', $cid)->value('nid');

        if (!empty($up_id) || $up_id > 0) {
            $arr[$num] = $up_id;
            if (!empty($up_cid) || $up_cid > 0) {
                return $this->all_user($up_cid, $arr, $num);
            }
        }
        return $arr;
    }

    #获取配比率
    public function getMatchPercent($user)
    {
        switch ($user->class_s){
            case 1:  #普卡配比率
                return Config::getConfigs('oneMatchPercent') / 100;
            case 2:  #银卡配比率
                return Config::getConfigs('twoMatchPercent') / 100;
            case 3:  #金卡配比率
                return Config::getConfigs('threeMatchPercent') / 100;
            case 4:  #白金卡配比率
                return Config::getConfigs('fourMatchPercent') / 100;
            case 5:  #黑金卡配比率
                return Config::getConfigs('fiveMatchPercent') / 100;
            case 6:  #钻卡配比率
                return Config::getConfigs('sixMatchPercent') / 100;
            case 7:  #蓝钻配比率
                return Config::getConfigs('sevenMatchPercent') / 100;
            default:
                throw new Exception("用户级别异常");
                break;
        }
    }

    #获取碰撞奖比率
    public function getBoomPercent($user)
    {
        switch ($user->class_s){
            case 1:  #普卡碰撞奖比率
                return Config::getConfigs('oneBoomPercent') / 100;
            case 2:  #银卡碰撞奖比率
                return Config::getConfigs('twoBoomPercent') / 100;
            case 3:  #金卡碰撞奖比率
                return Config::getConfigs('threeBoomPercent') / 100;
            case 4:  #白金卡碰撞奖比率
                return Config::getConfigs('fourBoomPercent') / 100;
            case 5:  #黑金卡碰撞奖比率
                return Config::getConfigs('fiveBoomPercent') / 100;
            case 6:  #钻卡碰撞奖比率
                return Config::getConfigs('sixBoomPercent') / 100;
            case 7:  #蓝钻碰撞奖比率
                return Config::getConfigs('sevenBoomPercent') / 100;
            default:
                return 0;
                break;
        }
    }
    #获取每层的碰撞奖比例
    public function getLayerPercent($num)
    {
        if ($num >=1 && $num <=3){
            return Config::getConfigs('oneToThreePercent') / 100;
        }else if ($num >= 4 && $num <= 6){
            return Config::getConfigs('fourToSixPercent') / 100;
        }else if ($num >= 7 && $num <= 10){
            return Config::getConfigs('sevenToTenPercent') / 100;
        }else if ($num >= 11 && $num <= 17){
            return Config::getConfigs('elvenToSeventeenPercent') / 100;
        }else if ($num >= 18 && $num <= 24){
            return Config::getConfigs('eighteenToTwentyFourPercent') / 100;
        }else if ($num >= 25 && $num <= 31){
            return Config::getConfigs('twentyFiveToThirtyOnePercent') / 100;
        }else if ($num >= 32){
            return Config::getConfigs('thirtyTwoPercent') / 100;
        }else {
            throw new Exception("碰撞层数异常！");
        }
    }
}