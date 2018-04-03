<?php
namespace app\backend\controller;

use app\backend\model\Node;
use think\Controller;
use think\Db;

use app\backend\model\TeamOrderModel;
use app\backend\model\UserModel;
use app\backend\model\Config as Configs;
use app\backend\model\AccountModel;


class Base extends Controller
{
    public function _initialize()
    {   

        if(empty(session('username'))){
            $this->redirect(url('login/index'));
        }

        //检测权限
        $control = lcfirst( request()->controller() );
        $action = lcfirst( request()->action() );

        //跳过登录系列的检测以及主页权限
        if(!in_array($control, ['login', 'index'])){

            if(!in_array($control . '/' . $action, session('action'))){
                $this->error('没有权限');
            }
        }

        //获取权限菜单
        $node = new Node();

        $this->assign([
            'username' => session('username'),
            'menu' => $node->getMenu(session('rule')),
            'rolename' => session('role')
        ]);

    }



    #团队奖--快乐奖
    public function teamPrize()
    {   
        
        #查询所有用户
        $user = UserModel::where('phone','<>','')->field('id,pid,t_class,score,balance')->select();
        $wordMoney = Configs::getConfigs('word_turnover');   //全球营业额
        $data = []; //分红数据
        $userData = []; //修改用户身份数据
        foreach ($user as $k => $v) {

            switch ($v->t_class) {
                case 0: //没身份
                case 1: //经理
                case 2: //总监
                $result = self::PromotionDirector($v->id,$user,$wordMoney,$v->score,$v->balance,$v->t_class);
                    break;
                case 3: //董事
                $account = [];
                $userUpdate = [];
                $account[] = self::addAccount($v->id,2,4,$wordMoney,0.9,0.01);    //余额
                $account[] = self::addAccount($v->id,1,4,$wordMoney,0.1,0.01);    //积分
                $userUpdate[] = self::updateAccount($v->id,$v->balance + $wordMoney * 0.01 * 0.9,$v->score + $wordMoney * 0.01 * 0.1,$v->t_class); 
                $result = [$account,$userUpdate];
                    break;                
            }
            if (!empty($result)) {
                $data = array_merge($data,$result[0]);
                $userData = array_merge($userData,$result[1]);
            }
        }

        #分割添加记录数组--每组5000批量修改
        Db::startTrans();
        try {
            $addnum = count($data);
            $addjie = (int)ceil($addnum / 4000);
            $addstart = 0;
            for ($e=1; $e <= $addjie ; $e++) { 
                $shuliang = (int)ceil(($addnum - $addstart) / ($addjie - $e +1));
                $addData[$e-1] = array_slice($data,$addstart,$shuliang);
                $addstart += $shuliang;

            }
            $model = new  AccountModel();
            foreach ($addData as $key => $value) {
                $res = $model->allowField(true)->insertAll($value);            
                if (!$res) {
                    throw new Exception("插入记录失败", 1);
                }
            }
            unset($addjie);
            unset($addData);

            #分割修改用户余额/积分/身份数组--每组5000批量修改
            $editnum = count($userData);
            $editjie = (int)ceil($editnum / 5000);
            $editstart = 0;
            for ($e=1; $e <= $editjie ; $e++) { 
                $shuliang = (int)ceil(($editnum - $editstart) / ($editjie - $e +1));
                $EditData[$e-1] = array_slice($userData,$editstart,$shuliang);
                $editstart += $shuliang;

            }
            $UserModel = new UserModel();
            foreach ($EditData as $key => $value) {
                
                $res = $UserModel->allowField(true)->saveAll($value);
                if (!$res) {
                    throw new Exception("修改记录失败", 1);
                }
            }
            unset($editjie);
            unset($EditData);
            Db::commit();
            echo "success";
        } catch (Exception $e) {
            Db::rollback();
            echo $e->getMessage();
        }


    }

    #晋升经理,判断条件
    protected static function PromotionManager($uid,$allUser,$wordMoney,$score,$balance)
    {   
        #获取所有下级
        $allUsers = getChildenAll($uid,$allUser,'xiaoyawei');
        #没下级返回出去
        if ( empty($allUsers) ) {
            return [];
        }
        #求所有下级的业绩
        $orderMoney = TeamOrderModel::whereIn('uid',$allUsers)->sum('money');
        #业绩小于10W  返回出去
        if ($orderMoney < 100000) {
            return [];
        }

        $ztUser = UserModel::where('pid',$uid)->count();
        #直推少于10人 返回出去
        if ($ztUser < 10) {
            return [];
        }

        unset($allUsers);
        $account = [];
        $userUpdate = [];
        $account[] = self::addAccount($uid,2,4,$wordMoney,0.9,0.03);    //余额
        $account[] = self::addAccount($uid,1,4,$wordMoney,0.1,0.03);    //积分

        $userUpdate[] = self::updateAccount($uid,$score + $wordMoney * 0.03 * 0.9,$balance + $wordMoney * 0.03 * 0.1,1); 
        return [$account,$userUpdate];
    }


    #晋升总监,判断条件
    protected static function PromotionSupervisor($uid,$allUser,$wordMoney,$score,$balance,$t_class)
    {   
        #获取所有下级
        $allUsers = getChildenAll($uid,$allUser);
        #没下级返回出去
        if ( empty($allUsers) ) {
            return [];
        }
        #求所有下级的业绩
        $orderMoney = TeamOrderModel::whereIn('uid',$allUsers)->sum('money');
        #业绩小于30W  返回出去
        if ($orderMoney < 300000) {
            return [];
        }
        #判断伞下有几个经理
        $Manager = UserModel::whereIn('id',$allUsers)->where('t_class',1)->count();
        if ($t_class == 1) {
            $Manager -=1;
        }

        if ($Manager < 3) {
            #晋升经理,判断条件
            if ($orderMoney >= 100000) {
                $result = self::PromotionManager($uid,$allUser,$wordMoney,$score,$balance,$t_class);
                return $result;
            }
            return [];
        }
        unset($allUsers);

        $account = [];
        $userUpdate = [];
        $account[] = self::addAccount($uid,2,4,$wordMoney,0.9,0.02);    //余额
        $account[] = self::addAccount($uid,1,4,$wordMoney,0.1,0.02);    //积分

        $userUpdate[] = self::updateAccount($uid,$score + $wordMoney * 0.02 * 0.9,$balance + $wordMoney * 0.02 * 0.1,2); 
        return [$account,$userUpdate];
    }

    #晋升董事,判断条件
    protected static function PromotionDirector($uid,$allUser,$wordMoney,$score,$balance,$t_class)
    {   
        $allUser = json_decode(json_encode($allUser),true);
        #获取所有下级
        $allUsers = getChildenAll($uid,$allUser,'zhanwei');
        #没下级返回出去
        if ( empty($allUsers) ) {
            return [];
        }

        #求所有下级的业绩
        $orderMoney = TeamOrderModel::whereIn('uid',$allUsers)->sum('money');
        #业绩小于100W  返回出去
        if ($orderMoney < 1000000) {
            #晋升总监,判断条件
            if ($orderMoney >= 300000 ) {
                $result = self::PromotionSupervisor($uid,$allUser,$wordMoney,$score,$balance,$t_class);
                return $result;
            }
            #晋升经理,判断条件
            if ($orderMoney >= 100000) {
                $result = self::PromotionManager($uid,$allUser,$wordMoney,$score,$balance,$t_class);
                return $result;                
            }
            return [];
        }

        #判断伞下有几个总监
        $Manager = UserModel::whereIn('id',$allUsers)->where('t_class',2)->count();

            
        if ($t_class == 1) {
            $Manager -=1;
        }

        if ($Manager < 3) {

            #晋升总监,判断条件
            if ($orderMoney >= 300000 ) {
                $result = self::PromotionSupervisor($uid,$allUser,$wordMoney,$score,$balance,$t_class);
                return $result;
            }
            #晋升经理,判断条件
            if ($orderMoney >= 100000) {
                $result = self::PromotionSupervisor($uid,$allUser,$wordMoney,$score,$balance,$t_class);
                return $result;                
            }            
            return [];
        }
        unset($allUsers);

        $account = [];
        $userUpdate = [];
        $account[] = self::addAccount($uid,2,4,$wordMoney,0.9,0.02);    //余额
        $account[] = self::addAccount($uid,1,4,$wordMoney,0.1,0.02);    //积分

        $userUpdate[] = self::updateAccount($uid,$score + $wordMoney * 0.02 * 0.9,$balance + $wordMoney * 0.02 * 0.1,2); 
        return [$account,$userUpdate];
    }

    #添加用户的余额/积分+-记录
    protected static function addAccount($uid,$class,$type,$wordMoney,$discount,$discount1)
    {
        if ($class == 1) {
            return [
                'uid'        =>$uid,
                'score'    => $wordMoney * $discount1 * $discount,
                'remark'     =>'快乐奖',
                'class'      =>$class,
                'type'       =>$type,
                'create_at' =>time()
            ];
        }
        return [
            'uid'        =>$uid,
            'balance'    => $wordMoney * $discount1 * $discount,
            'remark'     =>'快乐奖',
            'class'      =>$class,
            'type'       =>$type,
            'create_at' =>time()
        ];        
    }

    #修改用户的余额/积分
    protected static function updateAccount($uid,$balance,$score,$t_class)
    {
        return [
            'id'     => $uid,
            'score'  => $score,
            'balance'=> $balance,
            't_class'=> $t_class
        ];
    }

}


function add()
{   
    $data = [];
    for ($i=2; $i < 15; $i++) { 
        if ($i < 10) {
            $data[$i-2]['phone'] = '1310381074'.( $i -1 );
        }else{
            $data[$i-2]['phone'] = '131038107'.$i;
        }
        $data[$i-2]['pid'] = 10;
        $data[$i-2]['password'] = md5($i);
        $data[$i-2]['nickname'] = md5($i);
        $data[$i-2]['created_at'] = time();
    }

    db('users')->insertAll($data);
}

function addorder()
{
    $user = db('users')->column('id');
$data = [];
    for ($i=0; $i < 1000 ; $i++) { 
        $data[$i]['uid'] = rand(1,count($user));
        $data[$i]['money'] = 1000;
        $data[$i]['created_at'] = time();
    }
    db('team_order')->insertAll($data);

}

