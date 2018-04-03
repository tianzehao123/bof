<?php
namespace app\bofshop\controller;

//用户控制器
use app\backend\model\OrderModel;
use app\backend\model\OrderDetailModel;
use think\Controller;
use think\Db;
use think\Exception;
use app\backend\model\UserModel;
use app\backend\model\AccountModel;

class Users extends Controller {
    private $uid;

    private $status = ['1'=>'立即付款','2'=>'提醒发货','3'=>'订单详情','4'=>'取消订单','5'=>'再来一单','6'=>'删除订单','7'=>'确定收货'];
    private $statusKey = [1,2,3,4,5,6,7];
    private $opion  = [1=>[1=>'立即付款',4=>'取消订单'],
                       2=>[2=>'提醒发货',3=>'订单详情'],
                       3=>[7=>'确认收货',3=>'订单详情'],
                       4=>[5=>'再来一单',6=>'删除订单'],
                       5=>[5=>'再来一单',6=>'删除订单']
                      ];




    private $state = [1=>'待支付',2=>'待发货',3=>'待收货',4=>'已完成',5=>'已取消',6=>'已删除'];


    function _initialize(){
        $this->uid = session('home.user')['id'];
    }

    //用户信息
    public function user_info(){
        $need_info = ['pid','phone','con_score','pay_score','receive_score','reg_score','headimgurl'];
        $return = db('users')->where(['id'=>$this->uid])->field($need_info)->find();
        $return['p_phone'] = db('users')->where(['id'=>$return['pid']])->value('phone');
        return ajax(1,'查询成功',$return);
    }

    //各个状态订单数量
    public function order_num(){
        $return['daizhifu'] = db('order')->where(['uid'=>$this->uid,'status'=>1])->count();
        $return['daifahuo'] = db('order')->where(['uid'=>$this->uid,'status'=>2])->count();
        $return['daishouhuo'] = db('order')->where(['uid'=>$this->uid,'status'=>3])->count();
        $return['yiwancheng'] = db('order')->where(['uid'=>$this->uid,'status'=>4])->count();
        return ajax(1,'查询成功',$return);
    }

    //修改用户头像
    public function edit_headimg(){
        $img = input('img');
        $s = db('users')->where(['id'=>$this->uid])->update(['headimgurl'=>$img]);
        if($s){
            return ajax(1,'修改成功');
        }else{
            return ajax(2,'修改失败');
        }
    }


    /**
     * 获取订单信息
     * @param $where     搜索条件
     * @param $pageSize  页数
     * @param $pageNumer 每页长度
     * @return array
     */
    public function my_order(){
        //需要查询的订单状态order_status 
        $where = [];
        if(!empty(input('order_status'))){
            if(input('order_status')<=4)$where['status'] = input('order_status');
        }else{
            $where['status'] = ['not in',[5,6]];
        }                                                                        
        $where['uid'] = $this->uid;
        $order_model = new OrderModel();
    
        //设置分页
        // $pageSize   = empty(input('pageSize'))?1:input('pageSize');
        // $pageNumber = empty(input('pageNumber'))?3:input('pageNumber');
        // $pageSize =  ($pageSize-1) * $pageNumber;
        //设置需要的字段
        $field = ['id','address_name','price','score','order_sn','status','payment_method','created_at','created_at'];
        
    
        $list  = $order_model->field($field)->where($where)->order('id desc')->select();
        $count = $order_model->where($where)->count();//数据总条数
        
        if($list===false) return ajax(2,'数据查询失败');
        if(empty($list)) return ajax(1,'查询成功',['count'=>0,'list'=>[]]);
        //翻译数据
        foreach ($list as $key => $value) {
           $oid[] = $value['id'];
        }

        //查询订单详情
        $detail_model = new OrderDetailModel(); 
        $field = ['id','oid','gid','g_num','gimg','gname'];
        $list2 = $detail_model->field($field)->where(['oid'=>['in',$oid]])->order('id desc')->select();
        


       $opion  = [1=>['立即付款','取消订单'],
                 2=>['提醒发货','订单详情'],
                 3=>['确认收货','订单详情'],
                 4=>['再来一单','删除订单'],
                 5=>['再来一单','删除订单']
                ];

        $int = 0;
        $int2 = 0;
        $array = [];
        $data2 = [];
        $data = [];

        // echo '<pre>';
        // $list = objToArray($list);
        // var_dump($list);
        // die;
        //补全商品数量 商品id  商品图片
        foreach($list as $key =>$value){
            foreach($list2 as $k=>$v){

                if($value['id']==$v['oid']){
                    
                    if(!in_array($value['id'],$array)){
                         $array[] = $value['id'];
                        
                        if($value['payment_method']==1){
                             $data[$int]['g_num'] = $v['g_num'];
                             $data[$int]['gid']   = $v['gid'];
                             $data[$int]['gimg']  = $v['gimg'];
                             $data[$int]['name']  = $v['gname'];
                             $data[$int]['id']    = $value['id'];
                             $data[$int]['address_name']    = $value['address_name'];
                             $data[$int]['price']           = $value['price'];
                             $data[$int]['score']           = $value['score'];
                             $data[$int]['price']           = $value['price'];
                             $data[$int]['order_sn']        = $value['order_sn'];
                             $data[$int]['payment_method']  = $value['payment_method']; 
                             $data[$int]['created_at']      = date('Y-m-d h:i:s',$value['created_at']);
                             $data[$int]['button'] = $opion[$value['status']];
                             $data[$int]['status'] = $this->state[$value['status']];
                             $int++;
                        }else{
                             $data2[$int2]['g_num'] = $v['g_num'];
                             $data2[$int2]['gid']   = $v['gid'];
                             $data2[$int2]['gimg']  = $v['gimg'];
                             $data2[$int2]['name']  = $v['gname'];
                             $data2[$int2]['id']    = $value['id'];
                             $data2[$int2]['address_name']    = $value['address_name'];
                             $data2[$int2]['price']           = $value['price'];
                             $data2[$int2]['score']           = $value['score'];
                             $data2[$int2]['price']           = $value['price'];
                             $data2[$int2]['order_sn']        = $value['order_sn'];
                             $data2[$int2]['created_at']      = date('Y-m-d h:i:s',$value['created_at']);
                             $data2[$int2]['payment_method']  = $value['payment_method'];
                             $data2[$int2]['button'] = $opion[$value['status']];
                             $data2[$int2]['status'] = $this->state[$value['status']];
                             $int2++;
                        }
                    }                
                }
            }

            //翻译状态 根据状态添加操作按钮

            
        }


       return ajax(1,'查询成功',['count'=>$count,'list'=>$data,'list2'=>$data2]);
    }

    //修改订单状态
    public function  UpdataState(){
        $where['uid'] = $this->uid;
        if(empty(input('oid'))) return ajax(2,'请选择订单');
        $where['id'] = input('oid');
        //检查登录用户下是否存在该订单  获取订单当前状态
        $orderStatus = Db::name('order')->where($where)->value('status');
        if(empty($orderStatus)) return ajax(2,'该订单不存在');
        
        //当前状态
        if(!in_array($orderStatus,$this->statusKey)) return ajax(2,'您选择的订单状态不正常'); 
        $state = [1=>'新订单',2=>'付款',3=>'发货',4=>'收货',5=>'取消',6=>'删除'];

        //修改为
        if(empty(input('statusUpdate')) || !isset($this->state[input('statusUpdate')])) return ajax(2,'请选择要修改的状态');
        if((input('statusUpdate')!=($orderStatus+1)) && input('statusUpdate')!=6 && $orderStatus!=4 && $orderStatus!=1) return ajax(2,'请选择要修改的状态');
        //检查是否在修改的状态内
        $result = Db::name('order')->where($where)->Update(['status'=>input('statusUpdate')]);
        if($result!==false){
            return  ajax(1,$state[input('statusUpdate')].'成功');
        }else{
            return  ajax(2,$state[input('statusUpdate')].'失败');
        }
    }


    //我的积分
    public function my_score(){
        $need_info = ['con_score','pay_score','receive_score','reg_score'];
        $return = db('users')->where(['id'=>$this->uid])->field($need_info)->find();
        return ajax(1,'查询成功',$return);
    }

    //获赠积分详情
    /*
    *page  页数
    *type  请求类型
    */
    public function receive_score_detail(){
        $num = 5;     //一页数量
        $page = input('page')??'1';
        $type = input('type');
        $xtype = '7';
        if($type == 'huozeng'){
            $xtype = '6';
        }elseif($type == 'zhuce'){
            $xtype = '1';
        }elseif($type == 'xiaofei'){
            $xtype = '4';
        }else{
            $xtype = '7';
        }
        $where = [
            'uid' => $this->uid,
            'type' => $xtype
        ];
        $return['all_num'] = db('account')->where($where)->count();
        $return['all_page'] = ceil($return['all_num'] / $num);
        if($page > $return['all_page']){
            $page = $return['all_page'];
        }
        if($page < '1'){
            $page = '1';
        }
        $return['page'] = $page;
        $return['list'] = db('account')->where($where)->limit($num * ($page - 1),$num)->order('id desc')->select();
        foreach($return['list'] as $k=>$v){
            $return['list'][$k]['updated_at'] = date('Y-m-d H:i:s',$v['created_at']);
        }
        return ajax('1','查询成功',$return);
    }

    /**
     *  我的会员
     *  level       级别
     *  pageNumber  第几页
     *  pageSize    每页显示数量 （可不传,默认10）
     */
    public function myMembers()
    {
        # 获取用户id
        $uid = $this->uid;
        $level = input('level');
        if (empty($level)){
            return ajax('2','请传入查看会员的级别！');
        }

        $level = $level;
        $limit = input('pageSize');
        if (empty($limit)){
            $limit = 100;
        }
        $pageNumber = input('pageNumber');
        if (empty($pageNumber) || $pageNumber < 1){
            $pageNumber = 1;
        }
        $offset = ($pageNumber - 1) * $limit;

        switch ($level){
            case 1:
                $users = UserModel::field('id,nickname,truename,code')->where('pid',$uid)->select();
                $data = $this->getUserData($users,$limit,$offset);
                return ajax('1','获取成功',$data);
                break;
            case 2:
                $userIds = $this->downUser($uid,2);
                $users = UserModel::field('id,nickname,truename,code')->where('id','in',$userIds)->select();

                $data = $this->getUserData($users,$limit,$offset);

                return ajax('1','获取成功',$data);
                break;
            case 3:
                $userIds = $this->downUser($uid,3);
                $users = UserModel::field('id,nickname,truename,code')->where('id','in',$userIds)->select();
                $data = $this->getUserData($users,$limit,$offset);

                return ajax('1','获取成功',$data);
                break;
            default :
                break;
        }

    }

    #封装数据
    public function getUserData($users,$limit,$offset)
    {
        $uid =$this->uid;

        $num = 0;
        $data = [];
        $total = 0;
        foreach ($users as $key => $user){
            //source 1:直推 type 4：消费积分 is_add 1：收入 class 1 ：积分
            $accounts = AccountModel::where(['uid'=>$uid,'from_uid'=>$user['id'],'source'=>1,'is_add'=>1,'class'=>1])
                ->field('id,score,uid,created_at,from_uid')
                ->limit($offset,$limit)
                ->order('id desc')
                ->select();
            $total += AccountModel::where(['uid'=>$uid,'from_uid'=>$user['id'],'source'=>1,'is_add'=>1,'class'=>1])->count();
            foreach ($accounts as $k => $account){
                $data[$num]['id'] = $account['id'];
                $data[$num]['name'] = $account->user1['code'];
                $data[$num]['score'] = $account['score'];
                $data[$num]['created_at'] = date("Y-m-d H:i:s",$account['created_at']);
                $num ++;
            }

        }


        $list['data'] = $data; 
        $list['total'] = $total;
        return $list;
    }

    #获得指定下级的方法
    public function downUser($id,$num,$down = 0){
        $where["pid"] = ["IN",$id];
        $test = UserModel::where($where)->field('id,pid')->select();
        $user_id = [];
        foreach($test as $v){
            $user_id[] = $v["id"];
        }
        $down++;
        if($num != $down){
            return $this->downUser($user_id,$num,$down);
        }
        return $user_id;
    }

    /**
     * 推荐奖
     *  无参数
     */
    public function pushScore()
    {
        # 获取用户id
        $uid = $this->uid;
        //source 1:直推奖  is_add 1：收入 class 1 ：积分
        $accounts = AccountModel::where(['uid'=>$uid,'source'=>1,'is_add'=>1,'class'=>1])
            ->field('id,score,cur_score,uid,created_at,from_uid')
            ->order('id desc')
            ->select();
        $data = [];

        $allScore = 0;
        foreach ($accounts as $key => $account){
            $data[$key]['id'] = $account['id'];
            $data[$key]['name'] = $account->users['code'];
            $data[$key]['score'] = $account['cur_score'];
            $data[$key]['created_at'] = date("Y-m-d H:i:s",$account['created_at']);
            $allScore += $account['score'];
        }

        $list['data'] = $data;
        $list['allScore'] = $allScore;
        return ajax('1','获取成功',$list?:'暂未有推荐奖记录！');
    }

    /**
     * 收货地址列表
     */
    public function addressList()
    {
        # 获取用户id
        $uid = $this->uid;
        $addresses = db('address')->field('id,name,phone,description,default')->where('uid',$uid)->order('id','desc')->select();
        return ajax('1','获取成功',$addresses ?:'暂未添加收货地址!');

    }

    /**
     * 添加收获地址
     * address      地址
     * name         收货人姓名
     * phone        收货人手机号
     * is_default   0 不是 1 是
     */
    public function addAddress()
    {
        Db::startTrans();
        try{
            #收货地址
            $data = [];
            $data['uid'] = $this->uid;
            if (empty($data['uid'])){
                return ajax('2','请重新登录！');
            }
            $data['description'] = input('address');
            if (empty($data['description'])){
                return ajax('2','请填写收货地址！');
            }
            #收货人
            $data['name'] = input('name');
            if (empty($data['name'])){
                return ajax('2','请填写收货人姓名！');
            }
            #收货人手机号
            $data['phone'] = input('phone');
            if (empty($data['phone'])){
                return ajax('2','请填写收货人手机号');
            }
            #是否设置默认
            $data['default'] = input('is_default');
            if (empty($data['default']) || $data['default'] == 0){
                $data['default'] = 0;
            }else{
                $data['default'] = 1;
                #查找是否有默认收货地址
                $address = db('address')->where(['uid'=>$data['uid'],'default'=>1])->find();
                if (empty($address)){ #如果没有默认收货地址 -> 直接插入
                }else{ #如果有默认收货地址
                    # 先将默认收货地址 修改为 非默认
                    $res = db('address')->where('id',$address['id'])->update(['default'=>0,'updated_at'=>time()]);
                    if (!$res){
                        throw new Exception("修改默认地址失败！");
                    }
                }
            }
            $data['created_at'] = time();
            $data['updated_at'] = time();
            #添加收货地址
            $res = db('address')->insert($data);
            if (!$res){
                throw new Exception("地址添加异常！");
            }
            Db::commit();
            return ajax('1','地址添加成功！');
        }catch (Exception $e){
            Db::rollback();
            return ajax('2','地址添加失败！');
        }
    }
    /**
     * 修改地址页面
     * id   地址id
     */
    public function userAddress()
    {
        # 获取用户id
        $uid = $this->uid;
        if (empty($uid)){
            return ajax('2','请重新登录！');
        }
        $addressId = input('id');
        if (empty($addressId)){
            return ajax('2','请传入地址ID');
        }
        $addresses = db('address')->field('id,name,phone,description,default')->where('uid',$uid)->where('id',$addressId)->find();
        if(empty($addresses)){
            return ajax('-1','没有此收货地址');
        }
        return ajax('1','获取成功',$addresses);    
    }

     /**
     * 修改地址
     * id   地址id
     */
    public function userAddressEdit()
    {
        Db::startTrans();
        try{
            #收货地址
            $data = [];
            $data['uid'] = $this->uid;
            if (empty($data['uid'])){
                return ajax('2','请重新登录！');
            }
            $addressId = input('id');
            if (empty($addressId)){
                return ajax('2','请传入地址ID');
            }
            $data['description'] = input('address');
            if (empty($data['description'])){
                return ajax('2','请填写收货地址！');
            }
            #收货人
            $data['name'] = input('name');
            if (empty($data['name'])){
                return ajax('2','请填写收货人姓名！');
            }
            #收货人手机号
            $data['phone'] = input('phone');
            if (empty($data['phone'])){
                return ajax('2','请填写收货人手机号');
            }
            #是否设置默认
            $data['default'] = input('is_default');
            if (empty($data['default']) || $data['default'] == 0){
                $data['default'] = 0;
            }else{
                $data['default'] = 1;
                #查找是否有默认收货地址
                $address = db('address')->where(['uid'=>$data['uid'],'default'=>1])->where("id != $addressId")->find();
                if (empty($address)){ #如果没有默认收货地址 -> 直接插入
                }else{ #如果有默认收货地址
                    # 先将默认收货地址 修改为 非默认
                    $res = db('address')->where('id',$address['id'])->update(['default'=>0,'updated_at'=>time()]);
                    if (!$res){
                        throw new Exception("修改默认地址失败！");
                    }
                }
            }
            $data['updated_at'] = time();
            #添加收货地址
            $res = db('address')->where('id',$addressId)->update($data);
            if (!$res){
                throw new Exception("地址修改异常！");
            }
            Db::commit();
            return ajax('1','地址修改成功！');
        }catch (Exception $e){
            Db::rollback();
            return ajax('2','地址修改失败！');
        }
        
            
    }



    /**
     * 设置默认收货地址
     * id   地址id
     */
    public function setDefaultAddress()
    {
        Db::startTrans();
        try {
            # 获取用户id
            $uid = $this->uid;
            if (empty($uid)){
                return ajax('2','请重新登录！');
            }
            $addressId = input('id');
            if (empty($addressId)){
                return ajax('2','请传入地址ID');
            }
            $address = db('address')->where('id',$addressId)->find();
            if (empty($address)){
                return ajax('2','没有此收货地址');
            }
            if ($address['default'] == 0){ #如果不是默认收货地址
                #查看是否有默认收货地址
                $default_address = db('address')->where(['uid'=>$address['uid'],'default'=>1])->find();
                if (empty($default_address)){ #如果没有默认收货地址 -> 直接将传过了的地址设置为默认收货地址
                    $res = db('address')->where('id',$addressId)->update(['default'=>1,'updated_at'=>time()]);
                    if (!$res){
                        throw new Exception("默认地址修改异常");
                    }
                }else {  #如果有默认收获地址
                    #1. 将默认收货地址 改为 非默认
                    $res = db('address')->where('id',$default_address['id'])->update(['default'=>0,'updated_at'=>time()]);
                    if (!$res){
                        throw new Exception("默认地址修改异常");
                    }
                    #2. 将传过来的收货地址 设置为 默认收货地址
                    $res1 = db('address')->where('id',$addressId)->update(['default'=>1,'updated_at'=>time()]);                            if (!$res1){
                        throw new Exception("默认地址修改异常");
                    }
                }
            }else { #如果是默认收货地址
                return ajax('2','该地址已经是默认收货地址了');
            }
            Db::commit();
            return ajax('1','地址修改成功');
        }catch (Exception $e){
            Db::rollback();
            return ajax('2','地址修改失败');
        }

    }

     /**
     * 删除地址
     * id   地址id
     */
    public function userAddressDel()
    {
        # 获取用户id
        $uid = $this->uid;
        if (empty($uid)){
            return ajax('2','请重新登录！');
        }
        $addressId = input('id');
        if (empty($addressId)){
            return ajax('2','请传入地址ID');
        }
        if(db('address')->where('id',$addressId)->delete()){
            return ajax('1','删除成功');
        }else{
            return ajax('2','删除失败');
        }
    }

    /**
     * 修改密码
     * type         1:修改登陆密码 2：修改支付密码
     * phone        手机号
     * password     密码
     * repassword   确认密码
     * ver_code     验证码
     */
    public function editPassword()
    {
        Db::startTrans();
        # 获取用户id
        $uid = $this->uid;
        if (empty($uid)){
            return ajax('2','请重新登陆');
        }
        $user = UserModel::find($uid);
        $type = input('type');
        if (empty($type)){
            return ajax(2,'请传入修改的密码类型！');
        }
        $inputPhone = input('phone');
        if (empty($inputPhone)){
            return ajax(2,'请输入手机号！');
        }
        if ($user->phone != $inputPhone){
            return ajax(2,'请填写注册时的手机号！');
        }
        $password  = input('password');
        if (empty($password)){
            return ajax(2,'请输入密码！');
        }
        $repassword = input('repassword');
        if (empty($repassword)){
            return ajax(2,'请重新输入密码！');
        }
        #密码需要一致
        if($password != $repassword){
            return ajax('2','密码不一致');
        }
        if ($type == 2){
            // 正则匹配手机号
            $preg = '/^\d{6}$/';
            if(!preg_match($preg,$password)){
                return ajax('2','请输入6位纯数字密码！');
            }
        }
        $ver_code  = input('ver_code');
        if (empty($ver_code)){
            return ajax(2,'请输入验证码！');
        }
        // 今天的开始时间
        $start = strtotime('today');
        // 结束时间
        $end = strtotime('tomorrow');
        #查出发送的验证码
        $code = db('sms')
            ->where('time','>=',$start)
            ->where('time','<',$end)
            ->where(['type'=>$type,'status'=>0,'phone'=>$inputPhone])
            ->order('id','desc')
            ->find();
        #是否发送过验证码
        // if(!$code){
        //     return ajax('2','此手机号未发送手机号');
        // }
        //10分钟内有效
        // $expiry = $code['time']+60*10;
        // if(time()>$expiry){
        //     db('sms')->where('id',$code['id'])->update(['status'=>'2']);
        //     return ajax('2','验证码已过期');
        // }
        // #验证码是否正确
        // if($ver_code != $code['code']){
        //     return ajax('2','短信验证码错误');
        // }
        #验证码正确  改变验证码状态
        db('sms')->where('id',$code['id'])->update(['status'=>1]);
        if ($type == 1){
            #要修改的数据
            $data = [
                'password'      =>  md5($password),
                'updated_at'    =>  time(),
            ];
        }else {
            #要修改的数据
            $data = [
                'two_password'  =>  md5($password),
                'updated_at'    =>  time(),
            ];
        }
        #判断
        if(db('users')->where('phone',$inputPhone)->update($data)){
            Db::commit();
            return ajax('1','密码修改成功');
        }else{
            Db::rollback();
            return ajax('2','密码修改失败');
        }
    }


}