<?php
/**
 * Created by PhpStorm.
 * User: ovo
 * Date: 2017/7/10
 * Time: 下午6:08
 */
namespace app\backend\controller;

use app\backend\model\AccountModel;
class Account extends Base{
    //账户明细
    public function index()
    {
        $type = config('account_type');
        if(request()->isAjax()){

            $param = input('param.');

            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = [];
            $and_where = [];
            if (isset($param['user_code']) && !empty($param['user_code'])) {
                $uid = db('users')->where(['code'=>$param['user_code']])->value('id');
                $where['uid'] = $uid;
            }
            //分类
            if (isset($param['class']) && !empty($param['class'])) {
                $where['class'] = $param['class'];
            }
            //类型
            if (isset($param['type']) && !empty($param['type'])) {
                $where['type'] = $param['type'];
            }
            //时间
            if (isset($param['start']) && !empty($param['start'])) {
                $param['start'] = strtotime($param['start']);
                $where['created_at'] = ['>=',$param['start']];
            }
            if (isset($param['end']) && !empty($param['end'])) {
                $param['end'] = strtotime($param['end'].' 23:59:59');
                $and_where['created_at'] = ['<=',$param['end']];
            }
            $user = new AccountModel();
            $selectResult = $user->getAccountByWhere($where,$and_where,$offset, $limit);

            foreach($selectResult as $key=>$vo){
                $selectResult[$key]['ucode'] = db('users')->where(['id'=>$vo['uid']])->value('code');
                $selectResult[$key]['created_at'] = date('Y-m-d H:i:s',$vo['created_at']);
                $selectResult[$key]['class'] = $vo['class'] == '1'?'积分':'蓝海积分';
                $selectResult[$key]['type'] = $type[$vo['type']];
                $selectResult[$key]['from_uid'] = $vo['from_uid'] != 0?db('users')->where(['id'=>$vo['from_uid']])->value('code'):'系统';

            }

            if(isset($param['excel']) && $param['excel'] == 'to_excel'){    //导出到excel
                $content = objToArray($selectResult);
                foreach($content as $k=>$v){
                    unset($content[$k]['is_add']);
                    unset($content[$k]['source']);
                    unset($content[$k]['updated_at']);
                }
                $excel = new Excel();
                $first = ['A1'=>'编号','B1'=>'用户id','C1'=>'收入/支出数量','D1'=>'剩余数量','E1'=>'原因','F1'=>'分类','G1'=>'类型','H1'=>'来自用户编号','I1'=>'发放时间','J1'=>'用户编号'];
                $excel->toExcel('订单列表',$content,$first);
                return json(['code'=>1]);
            }

            $return['total'] = $user->getAllAccount($where);  //总数据
            $return['rows'] = $selectResult;

            return json($return);
        }
        $this->assign([
           'type' => $type
        ]);
        return $this->fetch();
    }

}