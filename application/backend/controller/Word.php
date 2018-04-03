<?php
/**
 * Created by PhpStorm.
 * User: ovo
 * Date: 2017/7/10
 * Time: 下午6:08
 */
namespace app\backend\controller;

use app\backend\model\UserModel;
use app\backend\model\ApplyModel;
use app\backend\model\TeamOrderModel;

use think\Db;
class Word extends Base{
    //用户列表
    public function index()
    {

        if(request()->isAjax()){

            $param = input('param.');

            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = [];
            $where['t_class'] = ['>=',3];
            if (isset($param['nickname']) && !empty($param['nickname'])) {
                $where['nickname'] = ['like', '%' . $param['nickname'] . '%'];
            }

            if (isset($param['phone']) && !empty($param['phone'])) {
                $where['phone'] = ['like', '%' . $param['phone'] . '%'];
            }

            $user = new UserModel();
            $selectResult = $user->getUsersByWhere($where, $offset, $limit);
            $allUser = $user->where('phone','<>','')->field('id,pid,t_class')->select();
            $allUser = json_decode(json_encode($allUser),true);
            #获取所有下级
            $status = config('t_class');

            foreach($selectResult as $key=>$vo){
                $allUsers = getChildenAll($vo->id,$allUser,'zhanwei');
                #没下级返回出去
                if ( empty($allUsers) ) {
                    return [];
                }

                #求所有下级的业绩
                $orderMoney = TeamOrderModel::whereIn('uid',$allUsers)->sum('money');
                $selectResult[$key]['money'] = $orderMoney;
                $operate = [];
                if ($vo->t_class == 3) {
                    $operate = [
                        '升级为银董' => url('word/edit', ['id' => $vo['id'],'t_class'=>4])
                    ];                       
                }else if ($vo->t_class == 4) {
                    $operate = [
                        '升级为金董' => url('word/edit', ['id' => $vo['id'],'t_class'=>5])
                    ];  
                }elseif ($vo->t_class == 5) {
                    $operate = [
                        '升级为皇冠' => url('word/edit', ['id' => $vo['id'],'t_class'=>6])
                    ];  
                }
                $selectResult[$key]['t_class'] = $status[$vo->t_class];
                $selectResult[$key]['operate'] = showOperate($operate);
                unset($allUsers);
            }

            $return['total'] = $user->getAllUsers($where);  //总数据
            $return['rows'] = $selectResult;

            return json($return);
        }

        return $this->fetch();
    }

    public function edit()
    {
        $param = input('param.');
        $t_class = UserModel::where('id',$param['id'])->value('t_class');
        $allUser =UserModel::where('phone','<>','')->field('id,pid,t_class')->select();
        $allUser = json_decode(json_encode($allUser),true);
        $allUsers = getChildenAll($param['id'],$allUser,'zhanwei');
        #求所有下级的业绩
        $orderMoney = TeamOrderModel::whereIn('uid',$allUsers)->sum('money');

        switch ($param['t_class']) {
            case 4:
                if ($orderMoney < 3000000) {
                    exit("<script>alert('条件不满足');history.go(-1)</script>");
                }
                break;
            case 5:
                if ($orderMoney < 6000000) {
                    exit("<script>alert('条件不满足');history.go(-1)</script>");
                }            
                break;
            case 6:
                if ($orderMoney < 10000000) {
                    exit("<script>alert('条件不满足');history.go(-1)</script>");
                }    
                break;            
        }

        $res = UserModel::where('id',$param['id'])->update(['t_class'=>$param['t_class']]);
        if ($res) {
            exit("<script>alert('修改成功');history.go(-1)</script>");
        }else{
            exit("<script>alert('修改失败');history.go(-1)</script>");
        }
    }

}