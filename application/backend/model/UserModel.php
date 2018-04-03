<?php
namespace app\backend\model;

use think\Model;
use think\Session;
use app\home\controller\Distribution;


class UserModel extends Model
{
    protected $table = 'sql_users';


    public $list = [];


    public function account(){
        return $this->hasMany('AccountModel','uid','id');
    }
    public function banks(){
        return $this->hasMany('BankModel','uid','id');
    }
    public function address(){
        return $this->hasOne('AddressModel','uid','id');
    }
    public function beaddress(){
        return $this->belongsTo('AddressModel','aid','id');
    }
    /**
     * 根据搜索条件获取用户列表信息
     * @param $where
     * @param $offset
     * @param $limit
     */
    public function getUsersByWhere($where, $offset, $limit)
    {
        return $this->where($where)->limit($offset, $limit)->order('id desc')->select();
    }
    #是否为报单中心
    public function getMerchant($uid)
    {
        $userOne = db("form_core")->where(["uid"=>$uid])->find();
        if($userOne){
            return true;
        }else{
            return false;
        }
    }

    public function getAwardsUser($where,$yeji){
        $users = $this->where($where)->order('id desc')->select();
        foreach($users as $k=>$v){
            $user_yeji = awards($v['id'])['yeji'];
            if($yeji == 1){
                if($user_yeji < 200000 || $user_yeji >= 600000){
                    unset($users[$k]);
                }
            }elseif($yeji == 2){
                if($user_yeji < 600000 || $user_yeji >= 1200000){
                    unset($users[$k]);
                }
            }elseif($yeji == 3){
                if($user_yeji < 1200000){
                    unset($users[$k]);
                }
            }
        }
        return $users;
    }

    /**
     * 根据搜索条件获取所有的用户数量
     * @param $where
     */
    public function getAllUsers($where)
    {
        return $this->where($where)->count();
    }

    /**
     * 插入管理员信息
     * @param $param
     */
    public function insertUser($param)
    {
        try{

            $result =  $this->save($param);
            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => -1, 'data' => '', 'msg' => $this->getError()];
            }else{

                return ['code' => 1, 'data' => '', 'msg' => '添加用户成功'];
            }
        }catch( PDOException $e){

            return ['code' => -2, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 编辑管理员信息
     * @param $param
     */
    public function editUser($param)
    {
        try{

            $result =  $this->save($param, ['id' => $param['id']]);

            if(false === $result){
                // 验证失败 输出错误信息
                return ['code' => 0, 'data' => '', 'msg' => $this->getError()];
            }else{

                return ['code' => 1, 'data' => '', 'msg' => '编辑用户成功'];
            }
        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }

    /**
     * 根据管理员id获取角色信息
     * @param $id
     */
    public function getOneUser($id)
    {
        return $this->where('id', $id)->find();
    }

    /**
     * 删除管理员
     * @param $id
     */
    public function delUser($id)
    {
        try{

            $this->where('id', $id)->delete();
            return ['code' => 1, 'data' => '', 'msg' => '删除管理员成功'];

        }catch( PDOException $e){
            return ['code' => 0, 'data' => '', 'msg' => $e->getMessage()];
        }
    }


    protected function getCreatedAtAttr($value)
    {
        $value = is_numeric($value)?date("Y-m-d h:i:s",$value):$value;
        return $value;
    }
    


    /**
     * 查询所有子级
     * @param integer    $UserID 用户编号
     * @param string     $where  按哪个字段条件查询
     * @param integer    $layer  第几级 (为空则查询所有下级)
     * @param array      $field  需要获取的字段
     * @param boolean    $type   是否按层排序
     * @param integer    $int    当前循环第几层(不可填)
     * @return Boolean   
     */
    public function SelectSon($userId,$where,$field=['id'],$layer=null,$type=false,$int=1)
    {   
         if(!empty($layer)){
            if($int>=$layer){return "查询完毕";}
         }

         $model = new UserModel();
         if(empty($userId)){ return false;}
         $row = $model->field($field)->where([$where=>$userId])->select();
         if($row !==false and !empty($row)){
             if($type!=false){
                 foreach ($row as $key => $value) {
                      $this->list[$int][] = $value;
                      $this->SelectSon($value['id'],$where,$field,$layer,$type,$int+1);
                 }
             }else{
                 foreach ($row as $key => $value) {
                      $this->list[] = $value;
                      $this->SelectSon($value['id'],$where,$field,$layer,$type,$int+1);
                 }
             }
         }else{
             return false;
         }
    }


    /**
     * 分配报单奖
     * @param string     $money     钱数
     * @return Boolean   
     */
    public function DeclarationForm($money){
        //获取登录者id 
        $id = Session::get("home.user")['id'];
        //获取该用户的报单中心和用户
        $data = $this->field(['code','bd_id'])->where(['id'=>$id])->find();
        // 获取该报单中心的状态
        $Fmodel  = new FromModel();
        $Fdata   = $Fmodel->field(['ucode','uid','from_class','status'])->where(['ucode'=>$data['bd_id'],'status'=>2])->find();
        if(empty($Fdata)) return true;
        //报单中心用户的余额
        $balance = $this->where(['id'=>$Fdata['uid']])->value('prize_score');
        //为正常状态的报单中心发放奖励
        if(!empty($Fdata)){
             switch ($Fdata['from_class']) {
                 case '1':
                     $money = $money * 0.02;
                     return $this->grantDeclarationForm($Fdata['uid'],$money,$balance,$id);
                     break;
                 case '2':
                     $money = $money* 0.03;
                     return $this->grantDeclarationForm($Fdata['uid'],$money,$balance,$id);
                     break;
                 case '3':
                     $money = $money* 0.05;
                     return $this->grantDeclarationForm($Fdata['uid'],$money,$balance,$id);
                     break;
             }
        }
    }


    /**
     * 发放报单奖励
     * @param string     $form_id     报单中心用户ID
     * @param string     $money       奖励金额
     * @param string     $balance     余额
     * @param string     $userId      来源用户ID
     * @return Boolean   
     */
    public function grantDeclarationForm($form_id,$money,$balance,$userId){
        #判断总收益是否大于150w  若有则出局
        if(restriction($userId)) return 1;
        $fcode = $this->where(['id'=>$userId])->value('code');

        $remark = 'D类结算 ===来自=== '.$fcode; 
        #TODO 发奖之后其他操作
        $Distribution =   new Distribution();

        $res = $Distribution->implement($form_id,$userId,$money,1,$remark);
        return $form_id;
        // //修改报单中心奖励积分金额
        // $data['prize_score']  =  $balance + $money;
        // $this->startTrans();
        // $result = $this->where(['id'=>$form_id])->update($data);
        // //增加奖励记录
        // $data2['uid']       = $form_id;
        // $data2['score']     = $money;
        // $data2['cur_score'] = $balance + $money;
        // $data2['remark']    = "报单奖励";
        // $data2['type']      = 1;
        // $data2['is_add']    = 1;
        // $data2['class']     = 1;
        // $data2['from_uid']  = $userId;        
        // $data2['created_at'] = time();
        // $data2['source'] = 15;


        // $Amodel  = new AccountModel();
     
        // $Amodel->startTrans();
        // $Aresult =  $Amodel->insert($data2);
        // //提交报单奖
        // if($result!==false &&  $Aresult!==false){
        //     //提交事务
        //     $Amodel->commit();
        //     $this->commit();
        //     return 1;
        // }else{
        //     //事务回归
        //     $Amodel->rollBack();
        //     $this->rollBack();
        //     return false;
        // }
    }


}
