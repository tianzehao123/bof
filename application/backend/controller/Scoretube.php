<?php
namespace app\backend\controller;
use think\Controller;
use think\Db;
#积分管理
class Scoretube extends Base
{   
    const TYPE = ['1'=>'注册积分','2'=>'游戏积分','3'=>'奖励积分','4'=>'消费积分','5'=>'电子积分','6'=>'获赠积分','7'=>'购物积分','8'=>'蓝海积分','9'=>'基金币','10'=>'复投积分'];
    const TYPES = ['reg_score'=>'1','game_score'=>'2','prize_score'=>'3','con_score'=>'4','ele_score'=>'5','receive_score'=>'6','pay_score'=>'7','ft_score'=>'10'];
    const CLASSS = ['0'=>'无','1' => "普卡", "2" => "银卡", "3" => "金卡", "4" => "白金卡", "5" => "黑金卡", "6" => "钻卡", "7" => "蓝钻"];
    const AccountType = [
        '0' => '系统',
        '1' => '直推',
        '2' => '对碰',
        '3' => '贡献',
        '4' => '购买商品',
        '5' => '商城分销',
        '6' => '后台充值',
        '7' => '转账',
        '8' => '会员升级',
        '9' => '购物积分转出',
        '10'=> '奖励积分交易',
        '11'=> '开通新会员',
        '12'=> '积分转换',
        '13'=> '蓝海积分',
        '14'=> '电子积分',
        '15'=> '报单奖励',
        '16'=> '蓝海积分收益'
        // 1直推2对碰3贡献4购买商品 5商城分销（推荐奖）6后台充值 7 转账8会员升级9购物积分转出10奖励积分交易11开通新会员,12积分转换,13bof
    ];
    #拨出查询
    public function DialOut()
    {
        if(request()->isAjax()){
            if(request()->isGet()){
                if(!empty(input())){
                    return json(['code'=>'-1','msg'=>'非法操作']);
                }
                $zsr = db('account')->where('class = 1 and is_add = 1')->sum('score');
                $zzc = substr(db('account')->where('class = 1 and is_add = 2')->sum('score'),1);
                $zyl = round(($zsr - $zzc),2);
                $zbl = round((($zzc / $zsr) * 100 ), 2 ).'%';
                return json(['zsr'=>$zsr,'zzc'=>$zzc,'zyl'=>$zyl,'zbl'=>$zbl]);
                exit;
            }
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;
            $where = ' is_add = 2 and class = 1 ';
            $where1 = ' is_add = 1 and class = 1 ';
            $start = $param['start'];
            $end = $param['end'];
            if($start != '' && $end ==''){
                $where   = $where . " and created_at >=  ".strtotime($start.'0:00:00')." ";
                $where1   = $where . " and created_at >= ".strtotime($start.'0:00:00')." ";
            }elseif($start == '' && $end != ''){
                $where  = $where . " and created_at <= ".strtotime($end.'23:59:59')." ";
                $where1  = $where . " and created_at <= ".strtotime($end.'23:59:59')." ";
            }elseif($start != '' && $end != ''){
                 $where  = $where . " and created_at <= ".strtotime($end.'23:59:59')." and created_at >= ".strtotime($start.'0:00:00')." ";
                $where1  = $where . " and created_at <= ".strtotime($end.'23:59:59')." and created_at >= ".strtotime($start.'0:00:00')." ";
            }
            $zc = Db::query("SELECT sum(score) as zc,FROM_UNIXTIME(created_at,'%Y%m%d') days FROM sql_account where $where GROUP BY days HAVING sum(score) ");              #支出
            $sr = Db::query("SELECT sum(score) as sr,FROM_UNIXTIME(created_at,'%Y%m%d') days FROM sql_account where $where1 GROUP BY days HAVING sum(score) ");              #收入
            $data = [];
            $new_arr = [];
            if(count($zc)<=count($sr)){
                foreach($sr as $key=>$val){
                    $new_arr[$key] = [
                        'zc' => ($zc[$key]['zc']??0) == 0 ? 0 : substr($zc[$key]['zc'],1),
                        'sr' => $sr[$key]['sr']??0,
                        'time' =>$sr[$key]['days'],
                    ];
                }
            }else{
                foreach($zc as $key=>$val){
                    $new_arr[$key] = [
                        'zc' => ($zc[$key]['zc']??0) == 0 ? 0 : substr($zc[$key]['zc'],1),
                        'sr' => $sr[$key]['sr']??0,
                        'time' =>$zc[$key]['days'],
                    ];
                }
            }
            foreach($new_arr as $k=>$v){
                $new_arr[$k]['yl'] = round(($v['sr'] - $v['zc']),2);
                $new_arr[$k]['bl'] = round((($v['zc'] / $v['sr']) * 100) , 2).'%';
            }        
            $return['total'] = count($new_arr);
            $return['rows'] = $new_arr;
            return json($return);
        }    
        return $this->fetch();
    }

    #积分明细
    public function IntegralDetail()
    {   

        
        return $this->fetch();
    }

    #财务明细
    public function FinanceDetail()
    {
        if (Request()->isAjax()) {
            // $where = [];
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;
            $where = [];
            if ($param['code']!='') {
                 $info = db('account')
                        ->alias('a')
                        ->join('users u','a.uid = u.id')
                        ->where(" u.code like '%".$param['code']."%'")
                        ->limit($pageNumber, $pageSize)
                        ->field('u.id')
                        ->select();
                $arr =[];
                foreach($info as $key=>$val){
                    $arr[] = $val['id'];
                }
                $where['uid'] = ['in',$arr];
            }
            if ($param['source']!='') {
                 $where["source"] = $param['source'];
            }   
            if ($param['types']!='') {
                $where['type'] = $param['types'];
            }   
            if ($param['start'] != '' and $param['end'] == '') {
                $where['created_at'] = ['>=', strtotime($param['start'])];
            }
            if ($param['start'] == '' and $param['end'] != '') {
                $where['created_at'] = ['<=', strtotime($param['end'] . "23:59:59")];
            }
            if ($param['start'] != '' and $param['end'] != '') {
                $where['created_at'] = ['between', [strtotime($param['start']), strtotime($param['end'] . "23:59:59")]];
            }
            $data = db('account')->where($where)->field('from_uid,created_at,remark,source,type,score,uid')->limit($pageNumber, $pageSize)->order('created_at desc')->select();
            // return json($data);
            $count = db('account')->where($where)->count();
            if(!empty($data)){
                foreach($data as $key=>$value){
                   $data[$key]['code'] = db('users')->where('id',$value['uid'])->value('code')??'后台';
                   // $data[$key]['code'] = db('users')->where('id',$value['from_uid'])->value('code')??'后台';
                   $data[$key]['uid'] = db('users')->where('id',$value['from_uid'])->value('code')??'后台';
                   $data[$key]['source'] = $this::AccountType[$value['source']];
                   // $data[$key]['created_at'] = date('Y-m-d H:i:s',$value['created_at']);
                   $data[$key]['type'] = $this::TYPE[$value['type']];
                }
            }
            $return['rows'] = $data;
            $return['total'] = $count;
            return json($return);
        }

        $this->assign([
            "info" => $this::AccountType,
            'type' => $this::TYPE,
        ]);
        
        return $this->fetch();
    }

    #充值管理
    public function RechargeAdmin()
    {   
        if(request()->isAjax()){
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;
            $where = [];
            if($param['ucode'] !== ''){
                $info = db('account')
                        ->alias('a')
                        ->join('users u','a.uid = u.id')
                        ->where("a.source = 6 and u.code like '%".$param['ucode']."%'")
                        ->limit($pageNumber, $pageSize)
                        ->field('u.id')
                        ->select();
                $arr =[];
                foreach($info as $key=>$val){
                    $arr[] = $val['id'];
                }
                $where['uid'] = ['in',$arr];
            }
            if($param['style']!=''){
                $where['style'] = $param['style'];
            }
            if($param['type']!=''){
                $where['type'] = $param['type'];
            }
            if ($param['start']!='' and $param['end']=='') {
                $where['created_at'] = ['>=', strtotime($param['start'])];
            }
            if ($param['start']=='' and $param['end']!='') {
                $where['created_at'] = ['<=', strtotime($param['end'] . "23:59:59")];
            }
            if ($param['start']!='' and $param['end']!='') {
                $where['created_at'] = ['between', [strtotime($param['start']), strtotime($param['end'] . "23:59:59")]];
            }
            $where['source'] = 6;
            $where['class'] = 1;
            $info = db('account')
            ->where($where)
            ->field('id,uid,score,cur_score,remark,style,type,is_add,created_at,from_uid')
            ->limit($pageNumber, $pageSize)
            ->order('id desc')
            ->select();
            $count = db("account")->where($where)->count(); 
            if(!empty($info)){
                foreach($info as $key=>$value){
                    $info[$key]['ucode'] = db('users')->where('id',$value['from_uid'])->value('code');
                    $info[$key]['type'] = '后台充值'.$this::TYPE[$value['type']].' 汇款充值';
                    $info[$key]['created_at'] = $value['created_at']!=''?date('Y-m-d H:i:s',$value['created_at']):'<a style="color:red">暂未确认</a>';
                    $info[$key]['top'] = round(($value['cur_score'] - $value['score']),2);
                    $info[$key]['style'] =  $value['style']==1?'<a style="color:green">已确认</a>':'<a style="color:red">未确认</a>';                 
                    if($value['style']!='1'){
                        $operate = [
                            '确认'=>"javascript:affirms('" . $value['id'] . "," . $value['uid'] . "')",
                            '删除'=>"javascript:dels('" . $value['id'] . "')",
                        ];
                    }else{
                        $operate = [
                            '刷新' => "",
                            '删除'=>"javascript:dels('" . $value['id'] . "')",
                        ];
                    }
                    $info[$key]['operate'] = showOperate($operate);
                }
            }
            $return['rows'] = $info;
            $return['total'] = $count;
            return json($return);
        }
        return $this->fetch();
    }

    #充值积分删除
    public function tubedels()
    {
        $id = input('id');
        if($id == '' || !is_numeric($id)){
            return json(['status'=>'-1','msg'=>'非法操作']);
        }
        if(db('account')->where('id',$id)->delete()){
            return json(['status'=>'1','msg'=>'删除成功']);
        }else{
            return json(['status'=>'-1','msg'=>'删除失败']);
        }
    }
    
    #充值积分搜索
    public function search()
    {
        $code = input('code');
        $codes = db('users')->where('code',$code)->field('truename,out_status')->find();
        if(empty($codes)){
            return json(['sta'=>'-1','msg'=>'','data'=>'编号不存在']);
        }
        $arr = ['1'=>'未出局','2'=>'已出局'];
        return json(['sta'=>'1','msg'=>'ok','data'=>'请核实姓名:'.$codes['truename'].'('.$arr[$codes['out_status']].')']);
    }

    #充值积分
    public function recharge()
    {
        $param = input('param.');
        $param = parseParams($param['data']);
        $code = $param['codes'];
        $type = $param['type'];
        $score=$param['score'];                             #充值金额
        if($code =='' || $type == '' || $score == ''){
            return json(['status'=>'-1','msg'=>'请填写完整']);
        }
        $need = ['id',$type];
        $user = db('users')->where('code',$code)->field($need)->find();
        if(empty($user)){
            return json(['status'=>'-1','msg'=>'没有此用户']);
        }
        #充值的积分小于0  为扣除  需要判断是否用户当前积分是否够
        if($score < 0){
            if(db('users')->where('code',$code)->value($type) <= 0){
                return json(['status'=>'-1','msg'=>'此用户积分不足以扣除']);
            }
        }
        #用户
        $data1['cur_score'] = $user[$type] + $score;    
        $data1['remark'] = $param['remark'];
        $data1['class'] = '1';
        $data1['is_add'] = ($score > 0)?'1':'2';
        $data1['type'] = $this::TYPES[$type];
        $data1['style'] = '0';
        $data1['source']  = '6';
        $data1['uid'] = '0';
        $data1['from_uid'] = $user['id'];;
        $data1['score'] = $score;
        if(db("account")->insert($data1)){
            return json(['status'=>'1','msg'=>'操作成功，请确认']);
        }else{
            return json(['status'=>'-1','msg'=>'操作失败']);
        }
    }
    #确认充值
    public function affirms()
    {
        $id = input('id');
        $info = db('account')->where('id',$id)->find();
        db('account')->where('id',$id)->update(['style'=>'1','created_at'=>time()]);
        $arr = array_flip($this::TYPES);
        $data[$arr[$info['type']]] = $info['cur_score'];
        Db::startTrans();
        try{
            Db::name('users')->where('id',$info['from_uid'])->update($data);
            Db::commit();
        } catch(\Exception $e) {
            Db::rollback();
            return json(['status'=>'-1','msg'=>'充值失败']);
        }
        return json(['status'=>'1','msg'=>'充值成功']);            
    }


     #积分转账
    public function IntegraTransfer()
    {   
        if(request()->isAjax()){
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;

            if($param['outcode'] !=''){
                $info = db('account')
                        ->alias('a')
                        ->join('users u','a.uid = u.id')
                        ->where("a.source = 7 and u.code like '%".$param['outcode']."%'")
                        ->limit($pageNumber, $pageSize)
                        ->field('u.id')
                        ->select();
                $arr =[];
                foreach($info as $key=>$val){
                    $arr[] = $val['id'];
                }
                $where['from_uid'] = ['in',$arr];
            }
            if($param['intocode']!=''){
                 $infos = db('account')
                        ->alias('a')
                        ->join('users u','a.uid = u.id')
                        ->where("a.source = 7 and u.code like '%".$param['intocode']."%'")
                        ->limit($pageNumber, $pageSize)
                        ->field('u.id')
                        ->select();
                $arrs =[];
                foreach($infos as $key=>$val){
                    $arrs[] = $val['id'];
                }
                $where['uid'] = ['in',$arr];
            }
            if ($param['start']!='' and $param['end']=='') {
                $where['created_at'] = ['>=', strtotime($param['start'])];
            }
            if ($param['start']=='' and $param['end']!='') {
                $where['created_at'] = ['<=', strtotime($param['end'] . "23:59:59")];
            }
            if ($param['start']!='' and $param['end']!='') {
                $where['created_at'] = ['between', [strtotime($param['start']), strtotime($param['end'] . "23:59:59")]];
            }
            if($param['type']!=''){
                $where['type'] = $param['type'];
            }else{
                $where['type'] = ['in',['1','4']];  
            }
            $where['source'] = '7';
            $data =  db('account')->where($where)->field('uid,from_uid,type,score,created_at')->limit($pageNumber, $pageSize)->select();
            $count = db("account")->where($where)->count(); 
            if(!empty($data)){
                foreach($data as $key=>$value){
                    $data[$key]['uid'] = db('users')->where('id',$value['uid'])->value('code');         #获取的用户id
                    $data[$key]['truename'] = db('users')->where('id',$value['uid'])->value('truename');    #获取的用户真实姓名
                    $data[$key]['from_uid'] = $value['from_uid'] == '0'?'系统':db('users')->where('id',$value['from_uid'])->value('code');    #来自用户id
                    $data[$key]['ftruename'] = $value['from_uid'] == '0'?'系统':db('users')->where('id',$value['from_uid'])->value('truename');    #来自用户真实姓名
                    $data[$key]['type'] = ($value['type']=='1')?'注册积分转账':'消费积分转账';
                    $data[$key]['created_at'] = date('Y-m-d H:i:s',$value['created_at']);
                }
                $return['rows'] = $data;
                $return['total'] = $count;
                return json($return);
            }
            return json(['code'=>'-1','msg'=>'没有数据']);
        }
        return $this->fetch();
    }


    #静态出局
    public function outgoings(){
        if(request()->isAjax()){
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;
            $where = [];
            if($param['code']!=''){
                $where['code'] = ['like','%'.$param['code'].'%'];
            }
            if($param['truename']!=''){
                $where['truename'] = ['like','%'.$param['truename'].'%'];
            }
            if($param['class'] != ''){
                $where['class'] = $param['class'];
            }
            if($param['out_status']!=''){
                $where['out_status'] = $param['out_status'];
            }
            $data = db('users')->where($where)->field('code,truename,class,created_at,nid,out_status')->limit($pageNumber, $pageSize)->order('created_at desc')->select();
            $count = db("users")->where($where)->count(); 
            if(!empty($data)){
                foreach($data as $key=>$value){
                    $data[$key]['code'] = $value['code']??'商城注册,暂未在系统注册';
                    $data[$key]['created_at'] = date('Y-m-d H:i:s',$value['created_at']);
                    $data[$key]['nid'] = db('users')->where('id',$value['nid'])->value('code')??'系统';
                    $data[$key]['out_status'] = $value['out_status'] == 1?'正常':'出局';
                    $data[$key]['class'] = ($value['class']!=''?$this::CLASSS[$value['class']]:'免费会员').($value['class']!=''?'<span style="color:red">('.config('bof_reg_score')[$value['class']].')</span>':'');
                    $data[$key]['truename'] = $value['truename'] != ''?$value['truename']:'此用户未填写';
                    $operate = [
                         '刷新'=>"",
                    ];                  
                    $data[$key]['todo'] = showOperate($operate);
                }
                $return['rows'] = $data;
                $return['total'] = $count;
                return json($return);

            }
            return json(['code'=>'-1','msg'=>'没有数据']); 
        }
        $this->assign('classs',config('name'));
        return $this->fetch();
    }

    #转换管理
    public function conversion()
    {   
        if(request()->isAjax()){
            $param = input('param.');
            $pageSize = $param['pageSize'];
            $pageNumber = ($param['pageNumber'] - 1) * $pageSize;
            $where = [];
            if($param['code'] !== ''){
                $info = db('inte_account')
                        ->alias('a')
                        ->join('users u','a.uid = u.id')
                        ->where("u.code like '%".$param['code']."%'")
                        ->limit($pageNumber, $pageSize)
                        ->field('u.id')
                        ->select();
                $arr =[];
                foreach($info as $key=>$val){
                    $arr[] = $val['id'];
                }
                $where['uid'] = ['in',$arr];
            }
            if($param['turnoutClass']!=''){
                $where['turnoutClass'] = $param['turnoutClass'];
            }
            if($param['intoClass']!=''){
                $where['intoClass'] = $param['intoClass'];
            }
            if ($param['start']!='' and $param['end']=='') {
                $where['created_at'] = ['>=', strtotime($param['start'])];
            }
            if ($param['start']=='' and $param['end']!='') {
                $where['created_at'] = ['<=', strtotime($param['end'] . "23:59:59")];
            }
            if ($param['start']!='' and $param['end']!='') {
                $where['created_at'] = ['between', [strtotime($param['start']), strtotime($param['end'] . "23:59:59")]];
            }
            $data = db('inte_account')->where($where)->limit($pageNumber, $pageSize)->order("created_at desc")->select();
            foreach($data as $key=>$value){
                $data[$key]['turnoutClass'] = $this::TYPE[$value['turnoutClass']] == '注册积分'?'购物积分':$this::TYPE[$value['turnoutClass']];
                $data[$key]['intoClass'] = $this::TYPE[$value['intoClass']] == '注册积分'?'购物积分':$this::TYPE[$value['intoClass']];
                $data[$key]['uid'] = db('users')->where('id',$value['uid'])->value('code')??'';
                $data[$key]['truename'] = db('users')->where('id',$value['uid'])->value('truename');
                $data[$key]['created_at'] = date('Y-m-d H:i:s',$value['created_at']);
                $data[$key]['how'] = round(($value['after']-$value['front']),2);
            }
            $count = db('inte_account')->where($where)->count();
            $return['rows'] = $data;
            $return['total'] = $count;
            return json($return);
        }
        return $this->fetch();
    }

}http://apibof.ewtouch.com/backend/index/index.html#