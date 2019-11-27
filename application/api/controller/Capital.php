<?php


namespace app\api\controller;


use app\common\exception\ServerException;
use app\common\model\Account;
use app\common\model\AccountLog;
use app\common\model\Order;
use app\common\model\OrderDemand;
use app\common\model\System_config;
use app\common\model\User;
use app\common\model\UserActionLog;
use app\common\serviceimpl\UserServiceImpl;
use PragmaRX\Google2FA\Google2FA;
use think\Config;
use think\Db;
use think\Exception;
use think\Paginator;

class Capital extends UserBase
{
  //主页
  public function index()
  {
    $account = Account::get(function ($query) {
      $query->field('pos,pow,point,buy_coin,action_coin,pass_card,ticket,gold_seed')->where('uid', input('id'));
    });

    $order=new Order();
   $amount= $order->where('uid',input('id'))->where('state',1)->sum('amount');
   $profite= $order->where('uid',input('id'))->where('state',1)->sum('profit');
    $account['pow']=$amount+$profite;
    return returnJson(1, '查询成功', $account);
  }

  //资产详情
  public function detail()
  {
    $id = input('id');
    $type = input('type');
    if (!input('type')) {
      throw new ServerException('请传入分类');
    }
    //分页
    $page = input("page") ? input("page") : 1;
    $pageSize = 10;
    $p = ($page - 1) * $pageSize . "," . $pageSize;
    $list = AccountLog::all(function ($query) use ($id, $type, $p) {
      $query->field('num,sign,msg,create_time')->where('uid', $id)->where('type', $type)->limit($p)->order('create_time desc');
    });

    return returnJson(1, '查询成功', $list);
  }

  //资产转移
  public function asset_transfer()
  {
    if (!input('user')) {
      throw new ServerException('请输入对方账户');
    }
    if (!input('num')) {
      throw new ServerException('请输入数量');
    }
    if (!input('paypassword')) {
      throw new ServerException('请输入二级密码');
    }
    if (!input('type')) {
      throw new ServerException('请输入类型');
    }
    $user = User::get(input('id'));
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
    switch (input('type')) {
      //派单币
      case 4:
        $type = 'buy_coin';
        $str = '互助币';
        break;
      //激活码
      case 5:
        $type = 'action_coin';
        $str = '激活码';
        break;
      case 7:
        $type = 'ticket';
        $str = '天使券';
        break;
    }
    $account = Account::get(input('id'));
    if ($account[$type] < input('num')) {
      throw new ServerException("剩余 $str 不足");
    }
    $to_user = User::get(['user' => input('user')]);
    if (!$to_user) {
      throw new ServerException('该用户不存在');
    }

    if ($to_user['id'] == input('id')) {
      throw new ServerException('不能自己转给自己');
    }

    if (strpos($to_user['path'], $user['path']) === false) {
      throw new ServerException('只能转给自己团队中的人');
    }

    $to_account = Account::get(['uid' => $to_user['id']]);
    Db::startTrans();
    try {

      $account->setDec($type, input('num'));
      AccountLog::addCoinLog($user['id'], input('type'), input('num'), '-', "转账给用户{$to_user['nickname']}");
      UserActionLog::addActionLog(input('id'), "转账给用户{$to_user['nickname']}");

      $to_account->setInc($type, input('num'));
      AccountLog::addCoinLog($to_user['id'], input('type'), input('num'), '+', "用户{$user['nickname']}转账");
      UserActionLog::addActionLog($to_user['id'], "用户{$user['nickname']}转账");
      Db::commit();
      return returnJson(1, '转移成功');
    } catch (Exception $e) {
      Db::rollback();
      throw new ServerException('');
    }
  }

  //冻结积分详情
  public function pos_detail()
  {
    $order = new Order();
    $list = $order->where('uid', input('post.id'))->where('state in (1,2)')->order('create_time desc')->select();
    foreach ($list as $k => $v) {
     $int = (strtotime($v['next_gain_time']) - strtotime(date("Y-m-d"))) / 3600 / 24 +1;
     if($int > 0){
       if($v['state']['value']==2){
         $list[$k]['next_gain_time']=0;
       }else{
         $list[$k]['next_gain_time']=$int;
       }
     }else{
       $list[$k]['next_gain_time']=0;
     }
    }
    return returnJson('1', '查询成功', $list);
  }

  //提本金
  public function withdraw()
  {
    $order = new Order();
    Db::startTrans();
      try {
        $list = $order->where('uid', input('post.id'))->lock(true)->where('state in (1,2)')->order('create_time desc')->find();
        if ($list['id'] != input('order_id')) {
          throw new ServerException('只有最后一笔订单可以提本金');
        } else {
          $list=$order->lock(true)->where('id',input('order_id'))->find();
          if(!$list){
            throw new ServerException('订单不存在,请核对后再提交!');
          }
          if ($list['state']['value'] == 1) {
            throw new ServerException('订单释放时间未到');
          }else if($list->getData('state') !=2){
           throw new ServerException('订单已处理');
          }
        $user = User::get(input('id'));
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

        //获取系统提现时间
        $config = System_config::get(['name' => 'tx_flag'])['value'];
        if ($config) {
          $this->is_start();
        }
		
		 //写入数据
		\app\common\model\OrderSh::create(['oid'=>input('order_id'),'state'=>3]);	
//        $account = Account::get(input('id'));
        //更改订单状态
//        $list->save(['state' => 4]);
//        $account->setDec('pow', ($list['amount'] + $list['profit']));
//        //加日志
//        // $demand = $this->putDemand(input('id'), $list['amount'], 2, 1);
//        $account->setInc('pos', $list['amount']);
//        AccountLog::addCoinLog(input('id'), 1,  $list['amount'], '+', '解冻');
//        UserActionLog::addActionLog($user['id'], "提取本金");
        Db::commit();
        return returnJson(1, '提取成功');
      }
    } catch (Exception $e) {
      Db::rollback();
      return returnJson(0, '请稍后重试');
    }
  }

  //解冻
  public function thaw()
  {
    $order = new Order();
    $server = new UserServiceImpl();
    Db::startTrans();
      try {
        $list = $order->where('uid', input('post.id'))->where('state in (1,2)')->lock(true)->order('create_time desc')->find();
        if ($list['id'] == input('order_id')) {
          throw new ServerException('最后一笔订单不可以解冻');
        }  else {
          $list=$order->lock(true)->where('id',input('order_id'))->find();
          if(!$list){
            throw new ServerException('订单不存在,请核对后再提交!');
          }
          if ($list->getData('state') == 1) {
            throw new ServerException('订单释放时间未到');
          }else if($list->getData('state') !=2){
            throw new ServerException('订单已处理');
          }
          $account = Account::get(input('id'));
          //加日志
          $account->setInc('pos', ($list['amount'] + $list['profit']));
          AccountLog::addCoinLog(input('id'), 1, ($list['amount'] + $list['profit']), '+', '解冻');
          //送通证
          $config = System_config::get(['name' => 'pass_card'])['value'];
          $num = doubleval(doubleval($list['amount']) / doubleval($config));

          $account->setInc('pass_card', $num);
          AccountLog::addCoinLog(input('id'), 6, $num, '+', '提供帮助送通证');
          //扣除冻结资产
          $account->setDec('pow', ($list['amount'] + $list['profit']));
          //返利
          $user = User::get(input('id'));
          $user->save(['is_valid' => 1]);
          //升级团队
          $server->team_up($user->getData('pid'));
          //进行返利
          if ($list->getData('day')) {
            $this->rebate($user, 1, $list['profit']*0.8,$user->getData('nickname'));
          }
          //更改订单状态
          $list->save(['state' => 3]);
          UserActionLog::addActionLog($user['id'], "解冻资产");
          Db::commit();     
        }
        return returnJson(1, '解冻成功');
      } catch (Exception $e) {
        Db::rollback();
        return returnJson(0, $e->getMessage());
      }
  }

  //返利
  public function rebate($user, $int, $amount,$name)
  {
    //查找上级
    $userF = User::get($user->getData('pid'));
    if (!$userF) {
      return;
    }
    //查询用户等级
    $level = $userF->getData('qz_level') ? $userF->getData('qz_level') : $userF->getData('level');
    $level_u = $user->getData('qz_level') ? $user->getData('qz_level') : $user->getData('level');
    //判断是否是有效会员
    if ($userF->getData('is_valid') && $userF->getData('is_lock') ==2 ) {
//      //当父级等级大于自己等级时进行返利
//      if ($int == 1 || $level > $level_u) {
        //查询用户能拿几代
        $config = System_config::get(['name' => $level, 'class' => 'zt_ds'])['value'];
        //如果代数大于或等于当前代数则获取返利
        if ($config >= $int) {
          //获取当前代数的比例
          $num = System_config::get(['name' => $level, 'class' => 'zt_bl'])['value'];
          //计算返利金额
          $rebate_num = $amount * $num;
          //增加金额
          $account = Account::get($userF['id']);
          $account->setInc('point', $rebate_num);
          AccountLog::addCoinLog($userF['id'], 3, $rebate_num, '+',"第{$int}代 用户{$name} 解冻返利" );
          //计算剩余金额
          $amount = $amount - $rebate_num;
//        }
      }
    }
    $int = $int + 1;
    $this->rebate($userF, $int, $amount,$name);
  }


  //通证页面
  public function pass_index()
  {
    $account = Account::get(input('id'))['pass_card'];
    $user = User::get(input('id'))['pass_address'];
    $url = request()->domain() . "/uploads/pass/" . input('id') . ".png";
    return returnJson(1, '获取成功', ['num' => $account, 'address' => $user, 'url' => $url]);
  }

  //通证转移
  public function pass_transfer()
  {
    if (!input('pass_address')) {
      throw new ServerException('请输入转入地址');
    }
    $to_user = User::get(['pass_address' => input('pass_address')]);
    if (!$to_user) {
      throw new ServerException('地址不存在');
    }
    if (!input('num')) {
      throw new ServerException('请输入数量');
    }
    if (!input('paypassword')) {
      throw new ServerException('请输入二级密码');
    }
    $user = User::get(input('id'));
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

    //扣除
    $account = Account::get(input('id'));
    $to_user_account = Account::get($to_user['id']);
    if ($account['pass_card'] < input('num')) {
      throw new ServerException('剩余通证不足');
    }

    Db::startTrans();
    try {
      $account->setDec('pass_card', input('num'));
      AccountLog::addCoinLog($account['uid'], 6, input('num'), '-', '转出扣除');
      UserActionLog::addActionLog($user['id'], "转账给用户{$to_user['nickname']}");

      $to_user_account->setInc('pass_card', input('num'));
      AccountLog::addCoinLog($to_user_account['uid'], 6, input('num'), '+', '转入');
      UserActionLog::addActionLog($to_user['id'], "给用户{$to_user['nickname']}转账");
      Db::commit();
      return returnJson(1, '转账成功');
    } catch (Exception $e) {
      Db::rollback();
      return returnJson(0, $e->getMessage());
    }
  }

  // 添加需求
  private function putDemand($uid, $amount, $type, $genre, $matchTime = 0)
  {
    $demand = new OrderDemand();
    return $demand->addDemand($uid, $amount, $type, $genre, $matchTime);
  }

  //查询用户
  public function select()
  {
    $user = User::get(['user' => input('user')]);
    if ($user) {
      return returnJson(1, '获取成功', $user->getData('nickname'));
    }
    return returnJson(0, '该用户不存在');
  }


}