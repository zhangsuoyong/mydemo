<?php


namespace app\api\controller;


use app\common\exception\ServerException;
use app\common\model\Account;
use app\common\model\AccountLog;
use app\common\model\Feedback;
use app\common\model\Notice;
use app\common\model\System_config;
use app\common\model\User;
use app\common\model\UserAccount;
use app\common\model\UserActionLog;
use app\common\serviceimpl\UserServiceImpl;
use app\common\validate\UserValidate;
use PragmaRX\Google2FA\Google2FA;
use think\Db;
use think\Exception;
use think\Session;

class Persion extends UserBase

{
  //个人中心
  public function index()
  {
    $user = User::get(input('post.id'));
    $data['img'] = $user['picname'];
    $data['nickname'] = $user['nickname'];
    $data['user'] = $user['user'];
    $data['invite'] = $user['invite'];
    $data['level'] = $user['level'];
    return returnJson(1, '查询成功', $data);
  }

  //个人资料
  public function info()
  {
    $user = User::get(input('post.id'));
    $data['img'] = $user['picname'];
    $data['nickname'] = $user['nickname'];
    $data['user'] = $user['user'];
    return returnJson(1, '查询成功', $data);
  }

  //修改个人资料
  public function update_info()
  {
    $data = [];
    if (input('file.img')) {
      $res = up('img', 'user');
      if ($res['code'] == 1) {
        $data['picname'] = $res['msg'];
      } else {
        throw new ServerException('头像上传失败');
      }
    }

    $user = User::get(input('post.id'));
    $int = $user->save($data);
    if ($int) {
      UserActionLog::addActionLog(input('id'), '修改个人资料');
      return returnJson(1, '修改成功');
    } else {
      return returnJson(0, '修改失败');
    }
  }

  //查看个人支付信息
  public function select_user_info()
  {
    $account = User::get(input('id'));
    $useraccount = UserAccount::get(['uid' => input('id')]);
    $data['name'] = $account['real_name'];
    $data['phone'] = $account['phone'];
    $data['bank_name'] = $useraccount['bank_name'];
    $data['bank_num'] = $useraccount['bank_num'];
    $data['alipay'] = $useraccount['alipay'];
    return returnJson(1, '查询成功', $data);

  }

  //更改个人支付信息
  public function update_user_info()
  {
    $post = input('post.');
    //如果他填过信息 后台不允许修改时，不能修改
    $user = User::get(input('id'));
    $config = System_config::get(['name' => 'edit_user_info_flag'])['value'];
    if ($user['real_name']) {
      if (!$config) {
        throw new ServerException('当前不允许修改个人支付信息');
      }
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
    if (!input('bank_num/d')) {
      throw new ServerException('请输入银行卡号');
    }
    if (!input('paypassword')) {
      throw new ServerException('请输入二级密码');
    }
    if ($user->getData('is_google')) {
      $google2fa = new Google2FA();
      if (input('paypassword') != $google2fa->getCurrentOtp($user->getData('google'))) {
        throw new ServerException('验证码输入错误');
      }
    } else {
      if ($user['paypassword'] != encrypt(input('paypassword'))) {
        throw new ServerException('二级密码错误');
      }
    }
    
   
   
    if(UserAccount::where('bank_num','=',input('bank_num/d'))->where('uid','<>',input('id'))->find()){
    		throw new ServerException('银行卡号已存在');
    }
    
    

    if (strlen($post['phone']) != 11) {
      throw new ServerException('请输入正确的手机号');
    }
    if(User::where('phone','=',$post['phone'])->where('id','<>',input('id'))->find()){
    	 throw new ServerException('手机号已存在');
    }
    Db::startTrans();
    try {
      UserAccount::update(['bank_name' => $post['bank_name'], 'bank_num' => $post['bank_num'], 'alipay' => ($post['alipay']?$post['alipay']:'')], ['uid' => $post['id']]);
      User::update(['real_name' => $post['real_name'], 'phone' => $post['phone']], ['id' => $post['id']]);
      UserActionLog::addActionLog(input('id'), '更改个人支付信息');
      Db::commit();
      return returnJson(1, '修改成功');
    } catch (Exception $e) {
      Db::rollback();
      return returnJson(0, $e->getMessage());
    }
  }

  //修改二级密码
  public function update_paypassword()
  {
    $post = input('post.');
    $validate = new UserValidate();
    $res = $validate->scene('repaypassword')->check($post);
    if (!$res) {
      throw new ServerException($validate->getError());
    }
    $user = User::get(input('id'));
    if ($post['user'] != $user['user']) {
      throw new ServerException('账号与当前登陆账号不匹配');
    }
    if (session('user') != $post['user'] || strtoupper(session('code')) != strtoupper($post['code'])) {
      throw new ServerException('验证码错误');
    }
    unset($post['code']);
    if (!$post['password']) {
      throw new ServerException('密码不能为空');
    }

    $post['paypassword'] = encrypt($post['password']);
    unset($post['repassword']);

    $int = User::update(['paypassword' => $post['paypassword']], ['id' => $post['id']]);
    if ($int) {
      UserActionLog::addActionLog(input('id'), '修改二级密码');
      return returnJson(1, '修改成功');
    } else {
      return returnJson(0, '修改失败');
    }
  }

  //修改密码
  public function update_password()
  {
    $post = input('post.');
    $validate = new UserValidate();
    $res = $validate->scene('repassword')->check($post);
    if (!$res) {
      throw new ServerException($validate->getError());
    }
    unset($post['repassword']);
    $user = User::get(input('id'));
    if ($user['password'] != encrypt(input('oldpassword'))) {
      throw new ServerException('原密码输入错误');
    }
    unset($post['oldpassword']);
    if (!$post['password']) {
      throw new ServerException('密码不能为空');
    }
    $int = $user->save(['password' => encrypt($post['password'])]);
    if ($int) {
      UserActionLog::addActionLog(input('id'), '修改登陆密码');
      return returnJson(1, '修改成功');
    } else {
      return returnJson(0, '修改失败');
    }
  }

  //查看我的团队
  public function select_team()
  {
    $data = [];
    $post = input('post.');
    if (!input('post.pid')) {
      throw new ServerException('请输入上级id');
    }
    $id = $post['id'];
    $user = User::get(function ($query) use ($id) {
      $query->field('picpath,picname,nickname,phone')->where('id', $id);
    });
    $count = $this->select_team_count($post['id']);
    $user['count'] = $count;
    $data['info'] = $user;
    $pid = $post['pid'];
    $list = User::all(function ($query) use ($pid) {
      $query->field('nickname,phone,create_time,id,invite,state')->where('pid', $pid);
    });
    foreach ($list as $k => $item) {
      $list[$k]['count'] = $this->select_team_count($item['id']);
      $list[$k]['create_time'] = date('Y-m-d', strtotime($item['create_time']));
    }
    $data['list'] = $list;
    return returnJson(1, '查询成功', $data);

  }

  //查看团队人数
  public function select_team_count($id)
  {
    $count = User::get(function ($query) use ($id) {
      $query->field('count(*) count')->where("find_in_set($id,path )");
    });
    return $count['count'];
  }

  //谷歌验证码页面
  public function google_index()
  {
    $user = User::get([input('post.id')]);
    $data = ['google' => 0, 'is_google' => 0];
    if ($user['google']) {
      $data['google'] = 1;
    }
    if ($user['is_google']) {
      $data['is_google'] = $user['is_google'];
    }
    return returnJson(1, '查询成功', $data);
  }

  //谷歌验证码重置
  public function google_reset()
  {
    $user = User::get(input('post.id'));
    $int = $user->save(['google' => '', 'is_google' => 0]);
    if ($int) {
      UserActionLog::addActionLog(input('id'), '重置谷歌验证码');
      return returnJson(1, '重置成功');
    } else {
      return returnJson(0, '重置失败');
    }
  }

  public function is_google()
  {
    $user = User::get(input('id'));
    if (!$user['google']) {
      throw new ServerException('请先绑定谷歌验证码');
    }
    if ($user->getData('is_google')) {
      $int = $user->save(['is_google' => 0]);
      UserActionLog::addActionLog(input('id'), '关闭谷歌验证码');
    } else {
      UserActionLog::addActionLog(input('id'), '开启谷歌验证码');
      $int = $user->save(['is_google' => 1]);
    }
    if ($int) {

      return returnJson(1, '修改成功');
    } else {
      return returnJson(0, '修改失败');
    }
  }

  //生成谷歌验证码
  public function google_start()
  {
    $google2fa = new Google2FA();
    $user = User::get(input('id'));
    if ($user['google']) {
      $google = $user['google'];
    } else {
      if (!session('google')) {
        $google = $google2fa->generateSecretKey();
        session('google', $google);
      } else {
        $google = session('google');
      }
    }
    return returnJson(1, '生成成功', $google);
  }

  //绑定google验证码
  public function google_bind()
  {
    $code = input('post.code');
    $google2fa = new Google2FA();
    if (!session('google')) {
      throw new ServerException('私钥超时，请重新生成');
    }
    if ($code != $google2fa->getCurrentOtp(session('google'))) {
      throw new ServerException('验证码输入错误');
    }
    $int = User::update(['google' => session('google'), 'is_google' => 1], ['id' => input('post.id')]);
    if ($int) {
      UserActionLog::addActionLog(input('id'), '绑定谷歌验证码');
      return returnJson(1, '绑定成功');
    } else {
      return returnJson(0, '绑定失败');
    }
  }

  //意见反馈
  public function view()
  {
    $content = input('content');
    $int = Feedback::create(['uid' => input('id'), 'content' => $content]);
    if ($int) {
      UserActionLog::addActionLog(input('id'), '提交意见反馈');
      return returnJson(1, '反馈成功');
    } else {
      return returnJson(0, '反馈失败');
    }
  }

  //通知列表
  public function notice_list()
  {
    $id = input('id');
    $list = Feedback::all(function ($query) use ($id) {
      $query->field('id,title,create_time,is_read')->where(['tid' => $id])->order('create_time desc');
    });
    return returnJson(1, '查询成功', $list);
  }

  //获取具体的通知
  public function notice_one()
  {
    if (!input('notice_id')) {
      throw new ServerException('请传入通知id');
    }
    $id = input('notice_id');
    $notice = Feedback::get(function ($query) use ($id) {
      $query->field('id,title,content,create_time')->where(['id' => $id])->order('create_time desc');
    });
    $notice->save(['is_read' => 1]);
    return returnJson(1, '查询成功', $notice);
  }

  //公告列表
  public function notice_index()
  {
    $list = Notice::all(function ($query) {
      $query->field('id,title,create_time')->where('state', 1)->order('create_time');
    });
    return returnJson(1, '查询成功', $list);
  }

  //查看某个公告
  public function notice_find_one()
  {
    if (!input('notice_id')) {
      throw new ServerException('请传入公告id');
    }
    $id = input('notice_id');
    $list = Notice::get(function ($query) use ($id) {
      $query->field('id,title,content,create_time')->where(['id' => $id, 'state' => 1]);
    });
    return returnJson(1, '查询成功', $list);
  }

  //激活用户
  public function create_invite()
  {
    //判断用户激活状态
    $user = User::get(input('tid'));
    if ($user['invite']) {
      throw new ServerException('请不要重复激活');
    }
    //扣除激活码
    $account = Account::get(['uid' => input('id')]);
    if ($account['action_coin'] < 1) {
      throw new ServerException('激活码不足,请先充值');
    }
    $server = new UserServiceImpl();
    $puser = User::get(input('id'));
    Db::startTrans();
    try {
      $account->setDec('action_coin', 1);
      AccountLog::addCoinLog(input('id'), 5, 1, '-', "激活用户{$user['nickname']}扣除");
      UserActionLog::addActionLog(input('id'), "激活用户{$user['nickname']}");
       
      $server->qc_codeAndInvite($user);
     
      UserActionLog::addActionLog($user['id'], "被用户{$puser['nickname']}激活");
      Db::commit();
      return returnJson(1, '激活成功');
    } catch (Exception $e) {
      Db::rollback();
      return returnJson(0, $e->getMessage());
    }
  }

  //查看分享链接
  public function invite_index()
  {
  	
  		if(!is_cli()){
		$serverName=$_SERVER['HTTP_HOST'];	
  	}else{
  		$serverName=config('domain');
  	}
    $id = input('id');
    $invite = User::get($id)->getData('invite');
    if (!$invite) {
      throw new ServerException('该用户暂未激活');
    } else {
      return returnJson(1, '获取成功', ['url' => 'http://' . $serverName . "/uploads/invite/".$id.".png"]);
    }
  }

  //退出登陆
  public function login_out()
  {
    Session::clear();
    return returnJson(1, '退出成功');
  }

  //帮助中心
  public function question()
  {
    $list = \app\common\model\Question::all();
    return returnJson(1, '查询成功', $list);
  }

  //防撞单开关
  public function is_order_first()
  {
    $user = User::get(input('id'));
    if ($user->getData('is_order_first') == 1) {
      $user->save(['is_order_first' => 0]);
    } else {
      $user->save(['is_order_first' => 1]);
    }
    return returnJson(1, '修改成功');
  }

  public function is_order_flag()
  {
    $user = User::get(input('id'));
    return returnJson(1, '查询成功', $user->getData('is_order_first'));
  }
  
  public function kanbudong (){
  	
  
  
  	$server=new UserServiceImpl();
  	// $users=User::all();
  	
  	// $user=User::get(680);
  	// $server->create_invite($user->id,$user->invite,$user);
  	var_dump('访问成功');
  	 die;
  	
  	$users=User::where('state','=',1)->select();
  		// $server->create_invite($user->id,$user->invite,$user);
  		
  		// die;
  	foreach($users as $user){
  		$server->create_invite($user->id,$user->invite,$user);
  	}
  	
  	
  }

}