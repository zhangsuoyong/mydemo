<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 18:52
 */

namespace app\tkfadmin\controller;


use app\common\exception\ServerException;
use app\common\model\Account;
use app\common\model\AdminLog;

use app\common\model\OrderDemand;
use app\common\model\UserAccount;
use app\common\model\UserActionLog;
use app\common\serviceimpl\UserServiceImpl;
use app\common\validate\UserValidate;
use think\Db;
use think\Exception;
use think\Request;


class User extends Base
{
  protected $service;

  public function __construct(Request $request = null, UserServiceImpl $service)
  {
    parent::__construct($request);
    $this->service = $service;
  }

  public function index()
  {
    $where = [];
    $query = [];
    if(input('get.part')!=0){
    	$where['user_part']=input('get.part');
    }
   
    if (input('id')) {
      $where['id'] = input('id');
      $query['id'] = input('id');
    } else {
      $query['id'] = '';
    }
    if (input('nickname')) {
      $where['nickname'] = input('nickname');
      $query['nickname'] = input('nickname');
    } else {
      $query['nickname'] = '';
    }
    if (input('real_name')) {
      $where['real_name'] = input('real_name');
      $query['real_name'] = input('real_name');
    } else {
      $query['real_name'] = '';
    }
    if (input('phone')) {
      $where['phone'] = input('phone');
      $query['phone'] = input('phone');
    } else {
      $query['phone'] = '';
    }
    if (input('user')) {
      $where['user'] = input('user');
      $query['user'] = input('user');
    } else {
      $query['user'] = '';
    }
    if (input('is_lock')) {
      $where['is_lock'] = input('is_lock');
      $query['is_lock'] = input('is_lock');
    } else {
      $query['is_lock'] = '';
    }

    $list = $this->service->getList($where, $query)->each(function ($item) {
    		$item['total_profit']=$this->sumUserDraw($item->id);
      if ($item['is_valid']) {
        $item['yz_state'] = '是';
      } else {
        $item['yz_state'] = '否';
      }
      $orderDeand=new OrderDemand();
      $item['provide_num']=$orderDeand->where('uid',$item['id'])->where('type',1)->where(' state in (1,2) ')->sum('amount');
      $item['receive_num']=$orderDeand->where('uid',$item['id'])->where('type',2)->where(' state in (1,2) ')->sum('amount');
      return $item;
    });
    $data = $where;
    $data['list'] = $list;
    
    // foreach ($list as $va){
    	
    // 	halt($va->account->gold_seed);
    // }
    
    // halt($data);
    return view('index', $data);
  }
  
  //统每个人的提取的分享奖金
  
  protected function sumUserDraw ($uid){
  
  $orders=\app\common\model\Order::query("SELECT SUM(profit) AS total_profit, uid FROM `tkf_order` WHERE state = 3 And  uid = $uid");
  if(!$orders[0]['uid']){
  	return 0;
  }
  
  return $orders[0]['total_profit'];
  	
  	
  }

  public function insert()
  {
  	
  	//halt(request()->isPost());
    if (!request()->isPost()) {
      return returnJson(0, '网络错误');
    }
   // halt(1);
    $post = input('post.');
    $validate = new UserValidate();
    $res = $validate->scene('admin_add')->check($post);
    if (!$res) {
      throw new ServerException($validate->getError());
    }
    if (isEmail(input('user'))) {
    } else if (strlen(input('user')) == 11) {
    } else {
      throw new ServerException('账号格式错误');
    }
    $user = \app\common\model\User::get(['user' => input('user')]);
    if ($user) {
      throw new ServerException('该账号已注册');
    }
    if ($post['ident'] == 1) {
      if (!input('post.invite')) {
        throw new ServerException('请输入邀请码');
      }
      $user = \app\common\model\User::get(['invite' => $post['invite']]);
      if (!$user) {
        throw new ServerException('邀请码错误');
      }
      $post['path'] = $user['path'] . $user['id'] . ',';
      $post['pid'] = $user['id'];
      unset($post['invite']);
     // halt($user->getData('id'));
     $str=$user->getData('path');
     
     $arr=explode(",", $str);
     //halt($arr);
     $tid=$arr[2]?$arr[2]:0;
    // halt($tid);
      $post['tid'] =$tid ;
    }
    return $this->service->user_add($post);
  }

  public function edit()
  {
    $user = \app\common\model\User::get(input('id'));
    $userAccount = UserAccount::get(['uid' => input('id')]);
    $info = ['name' => $user['real_name'], 'phone' => $user['phone'], 'id' => $user['id'],
        'bank_name' => $userAccount['bank_name'], 'bank_num' => $userAccount['bank_num'], 'alipay' => $userAccount['alipay']];
    return view('edit', ['list' => $info]);
  }

  public function update()
  {
    $post = input('post.');
    if (!input('id')) {
      throw new ServerException('id error');
    }
    if (!input('real_name')) {
      throw new ServerException('请输入真实姓名');
    }
    if (!input('phone')) {
      throw new ServerException('请输入联系方式');
    }
    if (!input('bank_name')) {
      throw new ServerException('请输入开户行');
    }
    if (!input('bank_num')) {
      throw new ServerException('请输入银行账号');
    }
    Db::startTrans();
    try {
      UserAccount::update(['bank_name' => $post['bank_name'], 'bank_num' => $post['bank_num'], 'alipay' => $post['alipay']], ['uid' => $post['id']]);
      \app\common\model\User::update(['real_name' => $post['real_name'], 'phone' => $post['phone']], ['id' => $post['id']]);
      Db::commit();
      return returnJson(1, '修改成功');
    } catch (Exception $e) {
      Db::rollback();
      return returnJson(0, $e->getMessage());
    }
  }

  public function edit_login()
  {
    $id = input('id');
    return view('edit_login', ['id' => $id]);
  }

  public function update_login()
  {
    $id = input('id');
    if (!input('id')) {
      throw new ServerException('id error');
    }
    if (!input('password')) {
      throw new ServerException('请输入登陆密码');
    }
    \app\common\model\User::update(['password' => encrypt(input('password'))], ['id' => input('id')]);
    return returnJson(1, '修改成功');
  }

  public function edit_pay()
  {
    $id = input('id');
    return view('edit_pay', ['id' => $id]);
  }

  public function update_pay()
  {
    $id = input('id');
    if (!input('id')) {
      throw new ServerException('id error');
    }
    if (!input('paypassword')) {
      throw new ServerException('请输入支付密码');
    }
    \app\common\model\User::update(['paypassword' => encrypt(input('paypassword'))], ['id' => input('id')]);
    return returnJson(1, '修改成功');
  }


  // 团队体系
  public function team()
  {
    $model = new \app\common\model\User();
    $where = '';
    if (input('id')) {
      $where['a.id'] = input('param.id');
      $uid = input('param.id');

      $list = $model->team($where);
      $this->assign('list', $list);
      $this->assign('uid', $uid);
      return view();
    }
  }


  // 团队体系 ajax
  public function team_list()
  {
    $model = new \app\common\model\User();
    $where['a.pid'] = input("post.id");
    $list = $model->team($where);
    return json($list);
  }

  public function log()
  {
    $where = [];
    $query = input('param.');
    if (input('id')) {
      $where['uid'] = ['=', input('id')];
    } else {
      $query['id'] = '';
    }
    if (input('start')) {
      $where['create_time'] = ['>=', input('start')];
    } else {
      $query['start'] = '';
    }
    if (input('end')) {
      $where['create_time'] = ['<=', input('end')];
    } else {
      $query['end'] = '';
    }
    $model = new UserActionLog();
    $list = $model->where($where)->order('create_time desc')->paginate(15, false, ['query' => $query])->each(function ($item) {
      $item['user'] = \app\common\model\User::get($item['uid'])['user'];
      $item['nickname'] = \app\common\model\User::get($item['uid'])['nickname'];
      return $item;
    });
    $query['list'] = $list;
    return view('log', $query);
  }

  public function edit_level()
  {
    $id = input('id');
    $user = \app\common\model\User::get($id);
    $level = $user->getData('level');
    $state = ['0' => '无', '1' => 'C1', '2' => 'C2', '3' => 'C3',
        '4' => 'C4', '5' => 'C5', '6' => 'C6'];
    return view('edit_level', ['id' => $id, 'list' => $state, 'level' => $level]);
  }

  public function update_level()
  {
    Db::startTrans();
    try {
      $user = \app\common\model\User::get(input('id'));
      if (input('level')) {
        if ($user->getData('level')) {
          $user->save(['level' => input('level')]);
        } else {
          $this->service->qc_codeAndInvite($user, input('level'));
        }
      } else {
        $user->save(['level' => 0, 'state' => '2', 'invite' => '']);
      }
      Db::commit();
      return returnJson(1, '修改成功');
    } catch (Exception $e) {
      Db::rollback();
      return returnJson(0, '修改失败');
    }
  }

  public function edit_qzlevel()
  {
    $id = input('id');
    $user = \app\common\model\User::get($id);
    $level = $user->getData('qz_level');
    $state = ['0' => '无', '1' => 'C1', '2' => 'C2', '3' => 'C3',
        '4' => 'C4', '5' => 'C5', '6' => 'C6'];
    return view('edit_qzlevel', ['id' => $id, 'list' => $state, 'level' => $level]);
  }

  public function update_qzlevel()
  {
    Db::startTrans();
    try {
      $user = \app\common\model\User::get(input('id'));
      if (input('qz_level')) {
        if ($user->getData('invite')) {
          $user->save(['qz_level' => input('qz_level')]);
        } else {
          $this->service->qc_codeAndInvite($user, $user->getData('level'));
          $user->save(['qz_level' => input('qz_level')]);
        }
      } else {
        $user->save(['qz_level' => 0]);
      }
      Db::commit();
      return returnJson(1, '修改成功');
    } catch (Exception $e) {
      Db::rollback();
      return returnJson(0, '修改失败');
    }
  }


  public function is_cash()
  {
    $user = \app\common\model\User::get(input('id'));
    if ($user->getData('if_cash') == 1) {
      $user->save(['if_cash' => 2]);
    } else {
      $user->save(['if_cash' => 1]);
    }
    return returnJson(1, '修改成功');
  }

  public function state()
  {
    $user = \app\common\model\User::get(input('id'));
    if ($user->getData('is_lock') == 1) {
      $user->save(['is_lock' => 2]);
    } else {
      $user->save(['is_lock' => 1]);
    }
    return returnJson(1, '修改成功');
  }

  public function del()
  {
    if (input('?post.id')) {
      $id = input('post.id');
      $model = \app\common\model\User::get(['pid' => $id]);
      if ($model) {
        throw new ServerException("该用户有下级不能删除");
      }
      $model = \app\common\model\User::get(['id' => $id]);
      $int = $model->delete();
      if ($int) {
        AdminLog::add_log("删除用户");
        return returnJson(1, '删除成功');
      } else {
        return returnJson(0, '删除失败,请稍后重试 ！');
      }
    }
  }

  //用户激活
  public function activation()
  {
    Db::startTrans();
    try {
      $user = \app\common\model\User::get(input('id'));
        if ($user->getData('level')) {
          $user->save(['level' => 0,'state'=>2,'invite'=>'']);
        } else {
          $this->service->qc_codeAndInvite($user,1,1);
        }
      Db::commit();
      return returnJson(1, '修改成功');
    } catch (Exception $e) {
      Db::rollback();
      return returnJson(0, '修改失败');
    }
  }


  //清空天使券
  public function truc_ticket()
  {
    $account=new Account();
    $account->query("update tkf_account set ticket = 0 ");
    $account->commit();
    return returnJson(1,'操作成功');
  }
  
  public function getInactiveUser (){
  $inactiveUser=UserActionLog::where('msg','like','120小时未排单%')->column('uid');
		
 $where = [];
    $query = [];
    if (input('id')) {
      $where['id'] = input('id');
      $query['id'] = input('id');
    } else {
      $query['id'] = '';
    }
    if (input('nickname')) {
      $where['nickname'] = input('nickname');
      $query['nickname'] = input('nickname');
    } else {
      $query['nickname'] = '';
    }
    if (input('real_name')) {
      $where['real_name'] = input('real_name');
      $query['real_name'] = input('real_name');
    } else {
      $query['real_name'] = '';
    }
    if (input('phone')) {
      $where['phone'] = input('phone');
      $query['phone'] = input('phone');
    } else {
      $query['phone'] = '';
    }
    if (input('user')) {
      $where['user'] = input('user');
      $query['user'] = input('user');
    } else {
      $query['user'] = '';
    }
    if (input('is_lock')) {
      $where['is_lock'] = input('is_lock');
      $query['is_lock'] = input('is_lock');
    } else {
      $query['is_lock'] = '';
    }

    $list = $this->service->getInactiveList($inactiveUser,$where, $query)->each(function ($item) {
      if ($item['is_valid']) {
        $item['yz_state'] = '是';
      } else {
        $item['yz_state'] = '否';
      }
      $orderDeand=new OrderDemand();
      $item['provide_num']=$orderDeand->where('uid',$item['id'])->where('type',1)->where(' state in (1,2) ')->sum('amount');
      $item['receive_num']=$orderDeand->where('uid',$item['id'])->where('type',2)->where(' state in (1,2) ')->sum('amount');
      return $item;
    });
    $data = $where;
    $data['list'] = $list;
    return view('inactiveUser', $data);
  	
  }
  
  public function savePart(){
  	$uid=input('post.user_id');
  	$part=input('post.part');
  	
  	if(!$uid||is_null($part)){
  		return returnJson(0,'参数错误');
  	}
  	
  	$user=\app\common\model\User::get ($uid);
  	if($user){
  		$user->user_part=$part;
  		$user->save();
  		return returnJson(1,'修改成功');
  	}else{
  		
  		return returnJson(0,'未知用户');
  	}
  	
  	
  }
  
  //自动排单开关
  public function auto_if_cash(){
  	if(is_null(input('id'))||is_null(input('state'))){
  		return returnJson(0,'参数错误');
  	}
  	
  	$user=\app\common\model\User::get(input('id'));
  	if(!$user){
  		return returnJson(0,'参数错误');
  	}
  	
  	if(input('state')==1){
  		$user->auto_if_cash=0;
  		
  		$user->save();
  	
  		return returnJson(1,'修改成功');
  		
  	}else{
  		$user->auto_if_cash=1;
  		$user->save();
  	
  		return returnJson(1,'修改成功');
  	}
  	
  
  }
  
  
  
}