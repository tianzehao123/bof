<?php

namespace app\backend\controller;

use app\backend\model\UserModel;
use app\backend\model\ApplyModel;
use think\Request;
use think\Db;
use Service\Useractivate;
use Service\Stock;
use Service\Rerformance;
use Service\Nineservice;
use app\backend\model\UsertestingModel;

/*
* 业绩评估
*/

class Usertesting extends Base
{


    public function index()
    {

        if (Request()->isAjax()) {
            $model = new UsertestingModel();
            $pageSize = input('pageSize');
            $pageNumber = (input('pageNumber') - 1) * $pageSize;
            $where['type'] = empty(input('type')) ? 3 : input('type');
            if (!empty(input('code'))) $where['u_code'] = input('code');

            $field = ['id', 'u_code', 'left_ach', 'right_ach', 'right_all_ach', 'left_all_ach', 'updatetime', 'current_time', 'committee'];
            $list = $model->field($field)->where($where)->limit($pageNumber, $pageSize)->order('id desc')->select();
            $count = $model->where($where)->count();
            $data = [];
            // 整理数据
            foreach ($list as $key => $value) {
                $data[$key]['u_code'] = $value['u_code'];
                $data[$key]['total_all'] = ($value['left_all_ach'] + $value['right_all_ach']) / 100;  //总单量
                $data[$key]['small_total'] = $value['left_all_ach'] < $value['right_all_ach'] ? ($value['left_all_ach'] / 100) : ($value['right_all_ach'] / 100);     //小区总单量
                $data[$key]['left_ach'] = ($value['left_ach'] / 100);  //左区新业绩
                $data[$key]['right_ach'] = ($value['right_ach'] / 100);  //右区新业绩
                $data[$key]['total'] = (($value['right_ach'] + $value['left_ach']) / 100);  //新增总单量
                $data[$key]['small'] = $value['left_ach'] < $value['right_ach'] ? $value['left_ach'] / 100 : $value['right_ach'] / 100; //新增小区单量
                $data[$key]['date'] = $value['current_time'];
                $data[$key]['is'] = $value['committee'] != 1 ? '<b style="color:#f00">是</b>' : '否';
            }

            $return['rows'] = $data;
            $return['total'] = $count;
            return json($return);
        } else {

            return $this->fetch();
        }
    }


    /**
     * 计算用户业绩并更新有达到策略委的人员
     * @param int $uid 用户id
     * @param int $money 增加的金额
     * @return bool
     */
    public function IsStrategyCommittee()
    {

        // 获取本月时间
        $thismonth = date('m');
        $thisyear = date('Y');

        $date = $thisyear . '-' . $thismonth; //本月时间
        $top = $thismonth == 1 ? 12 : $thismonth - 1;
        $date2 = $thismonth . '-' . $top;         //上月时间
        $toptop = $top == 1 ? 12 : $top - 1;
        $date3 = $thismonth . '-' . $toptop;     //上上月时间

        //获取达到第一层条件的用户id
        $model = new UserModel();

        $list = $model->field('id')->where('left_all_ach+right_all_ach >= 10000 and left_all_ach >=3000 and right_all_ach >=3000 and committee = 1')->select();
        if (empty($list)) return ajax(1, '更新完毕');
        $userId = [];
        foreach ($list as $key => $value) {
            $userId[] = $value['id'];
        }


        //获取达到第二层条件的用户
        $UsertestingModel = new UsertestingModel();
        $where['left_ach'] = ['>=', '100'];
        $where['right_ach'] = ['>=', '100'];
        $where['right_ach + left_ach'] = ['>=', '900'];
        $where['type'] = 2;
        $where['uid'] = ['in', $userId];
        $where['current_time'] = $date;

        $list1 = $UsertestingModel->field('id')->where($where)->select();

        if (empty($list1)) return ajax(1, '更新完毕');

        $userId = [];
        foreach ($list1 as $key => $value) {
            $userId[] = $value['id'];
        }

        //获取符合第三层条件的用户
        $where = [];
        $where['type'] = 2;
        $where['current_time'] = ['in', [$date, $date2, $date3]];
        $where['uid'] = ['in', $userId];

        $list2 = $UsertestingModel->field(['id', 'uid', 'left_ach', 'right_ach', 'total_ach'])->select();

        // 把本月业绩与前两个月的业绩相加
        foreach ($list2 as $key => $value) {
            $data[$value['uid']]['left_ach'] = empty($data[$value['uid']]['left_ach']) ? $value['left_ach'] : $data[$value['uid']]['left_ach'] + $value['left_ach'];
            $data[$value['uid']]['right_ach'] = empty($data[$value['uid']]['right_ach']) ? $value['right_ach'] : $data[$value['uid']]['right_ach'] + $value['right_ach'];
            $data[$value['uid']]['total_ach'] = empty($data[$value['uid']]['total_ach']) ? $value['total_ach'] : $data[$value['uid']]['total_ach'] + $value['total_ach'];
            $data[$value['uid']]['uid'] = $value['uid'];
            $data[$value['uid']]['id'] = $value['id'];
        }

        $result = [];
        //判断是否有满足条件的用户
        foreach ($data as $key => $value) {
            if ($value['total_ach'] >= 450 && $value['right_ach'] >= 150 && $value['right_ach'] >= 150) {
                $result[$key]['id'] = $value['uid'];
                $result[$key]['committee'] = 2;
                $result2[] = $value['uid'];
            }
        }

        if (empty($result)) return ajax(1, '更新完毕');

        //执行修改记录
        $UsertestingModel->startTrans();
        $model->startTrans();
        try {
            //把达成条件的用户升级为策略委
            $row = $model->saveAll($result);
            $row2 = $UsertestingModel->where(['uid' => ['in', $result2]])->update(['committee' => 2]);
            if ($row !== false && $row2 !== false) {

                $model->commit();
                $UsertestingModel->commit();
                return ajax(1, '更新完毕');
            } else {
                $model->rollBack();
                $UsertestingModel->rollBack();
                return ajax(2, '更新失败');
            }
        } catch (PDOException $e) {
            $model->rollBack();
            $UsertestingModel->rollBack();
            return false;
        }
    }


    /**
     * 增加业绩记录
     * @param int $uid 用户id
     * @param int $money 增加的金额
     * @return bool
     */
    public function addAchievement($uid, $money)
    {
        if (empty($uid) || empty($money)) return false;
        //查询出所有增加业绩的用户
        $list = $this->nodeLineSort($uid);
        if (empty($list)) return false;
        //计算出各自区域需要增加的金钱
        $data = [];
        $user_id = [];
        $region = [1 => 'left_all_ach', 2 => 'right_all_ach'];
        foreach ($list as $key => $value) {
            foreach ($list as $k => $v) {
                if ($value['id'] != $uid && $value['id'] == $v['nid']) {
                    //判断是需要在增加左区还是右区
                    $data[$key]['id'] = $value['id'];
                    $data[$key]['region'] = 'left_ach';
                    if ($v['region'] == 2) $data[$key]['region'] = 'right_ach';
                    $data[$key]['code'] = $value['code'];
                    $user_id[] = $value['id'];
                    $data[$key]['left_all_ach'] = $value['left_all_ach'];
                    $data[$key]['right_all_ach'] = $value['right_all_ach'];
                    $data[$key][$region[$v['region']]] = $value[$region[$v['region']]] + $money;
                }
            }
        }


        if (empty($data)) return true;
        //查询出当天 和 当月的 用户业绩记录
        $UsertestingModel = new UsertestingModel();
        $day = date('Y-m-d', time());         //当天时间
        $month = date('Y-m', time());           //当月时间

        $field = ['id', 'uid', 'total_ach', 'left_ach', 'right_ach'];
        $dayList = $UsertestingModel->where(['uid' => ['in', $user_id], 'current_time' => $day, 'type' => 1])->select();
        //获取所有上级的今天记录
        $monthList = $UsertestingModel->where(['uid' => ['in', $user_id], 'current_time' => $month, 'type' => 2])->select();
        //获取所有上级的本月的记录
        $quarterList = $UsertestingModel->where(['uid' => ['in', $user_id], 'current_time' => $month, 'type' => 3])->select();


        $day_user = [];  //今天存在记录的用户
        $month_user = [];  //这个月存在记录的用户
        $day_data = [];  //今天的记录修改后
        $month_data = [];  //本月的记录修改后
        $quarter_user = [];  //这个季度存在记录的用户
        $quarter_data = [];  //这个季度记录修改后

        //获取不存在记录的用户id,修改存在的用户记录
        foreach ($data as $key => $value) {
            // 今天
            foreach ($dayList as $k => $v) {
                if ($value['id'] == $v['uid']) {
                    $day_data[$key]['id'] = $v['id'];
                    $day_data[$key]['total_ach'] = $v['total_ach'] + $money;
                    $day_data[$key][$value['region']] = $v[$value['region']] + $money;
                    $day_data[$key]['updatetime'] = date('Y-m-d h:i:s', time());
                    $day_data[$key]['right_all_ach'] = $v['right_all_ach'];
                    $day_data[$key]['left_all_ach'] = $v['left_all_ach'];
                    $day_user[] = $value['id'];
                }
            }
            //本月
            foreach ($monthList as $a => $b) {
                if ($value['id'] == $b['uid']) {
                    $month_data[$key]['id'] = $b['id'];
                    $month_data[$key]['total_ach'] = $b['total_ach'] + $money;
                    $month_data[$key][$value['region']] = $b[$value['region']] + $money;
                    $month_data[$key]['updatetime'] = date('Y-m-d h:i:s', time());
                    $month_data[$key]['right_all_ach'] = $b['right_all_ach'];
                    $month_data[$key]['left_all_ach'] = $b['left_all_ach'];
                    $month_user[] = $value['id'];
                }
            }
            $region = ['left_ach' => 'left_all_ach', 'right_ach' => 'right_all_ach'];
            //本季度
            foreach ($quarterList as $c => $d) {
                if ($value['id'] == $d['uid']) {
                    $quarter_data[$key]['id'] = $d['id'];
                    $quarter_data[$key]['total_ach'] = $d['total_ach'] + $money;
                    $quarter_data[$key][$value['region']] = $d[$value['region']] + $money;
                    $quarter_data[$key]['total_ach'] = $d['total_ach'] + $money;
                    $quarter_data[$key][$region[$value['region']]] = $d[$region[$value['region']]] + $money;
                    $quarter_data[$key]['updatetime'] = date('Y-m-d h:i:s', time());
                    $quarter_data[$key]['right_all_ach'] = $d['right_all_ach'];
                    $quarter_data[$key]['left_all_ach'] = $d['left_all_ach'];
                    $quarter_user[] = $value['id'];
                }
            }
        }


        $day_add = [];
        $month_add = [];
        $quarter_add = [];
        $quarter_add_user = [];

        // 获取本月时间
        $thismonth = date('m');
        $thisyear = date('Y');
        $date = $thisyear . '-' . $thismonth; //本月时间
        $top = $thismonth == 1 ? 12 : $thismonth - 1;
        $date2 = $thismonth . '-' . $top;         //上月时间
        $toptop = $top == 1 ? 12 : $top - 1;
        $date3 = $thismonth . '-' . $toptop;     //上上月时间

        //添加不存在的记录
        foreach ($data as $key => $value) {
            //添加不存在的今天记录
            if (!in_array($value['id'], $day_user)) {
                $day_add[$key]['uid'] = $value['id'];
                $day_add[$key]['type'] = 1;
                $day_add[$key]['u_code'] = $value['code'];
                $day_add[$key]['total_ach'] = $money;
                $day_add[$key]['left_ach'] = 0;
                $day_add[$key]['right_ach'] = 0;
                $day_add[$key][$value['region']] = $money;
                $day_add[$key]['left_all_ach'] = $value['left_all_ach'];
                $day_add[$key]['right_all_ach'] = $value['right_all_ach'];
                $day_add[$key]['updatetime'] = date('Y-m-d h:i:s', time());
                $day_add[$key]['addtime'] = date('Y-m-d h:i:s', time());
                $day_add[$key]['current_time'] = $day;

            }
            //添加不存在的本月记录
            if (!in_array($value['id'], $month_user)) {
                $month_add[$key]['uid'] = $value['id'];
                $month_add[$key]['type'] = 2;
                $month_add[$key]['u_code'] = $value['code'];
                $month_add[$key]['total_ach'] = $money;
                $month_add[$key]['left_ach'] = 0;
                $month_add[$key]['right_ach'] = 0;
                $month_add[$key][$value['region']] = $money;
                $month_add[$key]['left_all_ach'] = $value['left_all_ach'];
                $month_add[$key]['right_all_ach'] = $value['right_all_ach'];
                $month_add[$key]['updatetime'] = date('Y-m-d h:i:s', time());
                $month_add[$key]['addtime'] = date('Y-m-d h:i:s', time());
                $month_add[$key]['current_time'] = $month;
            }

            //获取不存在季度记录的用户id
            if (!in_array($value['id'], $quarter_user)) $quarter_add_user[] = $value['id'];
        }


        //获取本季度没有记录的用户前两个月记录 并组合本季度的业绩
        if (!empty($quarter_add_user)) {
            //获取本季度前两个月的业绩

            $where['uid'] = ['in', $quarter_add_user];
            $where['current_time'] = ['in', [$top, $toptop]];
            $list = $UsertestingModel->field(['uid', 'left_ach', 'right_ach'])->where($where)->select();
            //获取总业绩

            $model = new UserModel();
            $list2 = $model->field(['id', 'left_all_ach', 'right_all_ach', 'code'])->where(['id' => ['in', $quarter_add_user]])->select();

            $region = ['left_ach' => 'left_all_ach', 'right_ach' => 'right_all_ach'];
            // 如果前两个月没有业绩则直接添加一个空的新业绩

            foreach ($quarter_add_user as $key => $value) {
                //组合总业绩
                foreach ($list2 as $k => $v) {
                    if ($value == $v['id']) {
                        $quarter_add[$value]['left_all_ach'] = $v['left_all_ach'];
                        $quarter_add[$value]['right_all_ach'] = $v['right_all_ach'];
                        $quarter_add[$value]['u_code'] = $v['code'];
                    }
                }
                // 初始化

                foreach ($data as $k => $v) {
                    if ($v['id'] == $value) {
                        $quarter_add[$value]['uid'] = $value;
                        $quarter_add[$value]['left_ach'] = 0;
                        $quarter_add[$value]['right_ach'] = 0;
                        $quarter_add[$value]['type'] = 3;
                        $quarter_add[$value]['updatetime'] = date('Y-m-d h:i:s', time());
                        $quarter_add[$value]['addtime'] = date('Y-m-d h:i:s', time());
                        $quarter_add[$value]['current_time'] = $month;
                        $quarter_add[$value]['total_ach'] = $money;
                        $quarter_add[$value][$v['region']] = $money;
                        $quarter_add[$value][$region[$v['region']]] += $money;
                        $quarter_add[$value]['type'] = 3;
                    }
                }
            }
        }


        //执行修改记录
        $UsertestingModel->startTrans();
        try {
            $result1 = $UsertestingModel->saveAll($day_add);        //添加今天的业绩记录
            $result2 = $UsertestingModel->saveAll($day_data);       //修改今天的业绩记录
            $result3 = $UsertestingModel->saveAll($month_add);      //添加本月的业绩记录
            $result4 = $UsertestingModel->saveAll($month_data);     //修改本月的业绩记录
            $result5 = $UsertestingModel->saveAll($quarter_add);    //添加本月的业绩记录
            $result6 = $UsertestingModel->saveAll($quarter_data);   //修改本月的业绩记录

            if ($result1 !== false && $result2 !== false && $result3 !== false && $result4 !== false) {
                $UsertestingModel->commit();
                return true;
            } else {
                $UsertestingModel->rollBack();
                return false;
            }
        } catch (PDOException $e) {
            $UsertestingModel->rollBack();
            return false;
        }
    }


    /**
     * 返回所有上级信息
     * @param  int $uid 用户id
     * @return bool
     */
    public function nodeLineSort($uid)
    {
        if (empty($uid)) return false;
        $model = new UserModel();
        //获取所有上级id
        $all_nid = $model->where(['id' => $uid])->value('all_nid');
        //拼接自己的id 清除第一个系统id 0;
        if (empty($all_nid)) false;
        $all_nid = explode(',', $all_nid . $uid);
        array_shift($all_nid);
        //查看所有上级
        $field = ['id', 'nid', 'code', 'left_all_ach', 'right_all_ach', 'region'];
        $list = $model->field($field)->where(['id' => ['in', $all_nid]])->select();
        return $list;
    }


}