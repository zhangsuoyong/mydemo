<?php

namespace app\api\controller;

use app\common\exception\ServerException;
use app\common\model\Account;
use app\common\model\AccountLog;
use app\common\model\Chat;
use app\common\model\Order;
use app\common\model\OrderDemand;
use app\common\model\OrderTransaction;
use app\common\model\System_config;
use app\common\model\User;
use app\common\model\UserActionLog;
use app\common\server\yzmServer;
use PragmaRX\Google2FA\Google2FA;
use think\Db;

class Transaction extends UserBase
{

  protected $orderTransaction;
  protected $orderDemand;
  protected $userCoinLog;
  protected $order;
  protected $request;
  protected $user;
  protected $chat;

  public function __construct(OrderTransaction $orderTransaction, OrderDemand $orderDemand, Order $order, User $user, Chat $chat, AccountLog $userCoinLog)
  {
    parent::__construct();
    $this->request = request();
    $this->orderTransaction = $orderTransaction;
    $this->orderDemand = $orderDemand;
    $this->userCoinLog = $userCoinLog;
    $this->order = $order;
    $this->user = $user;
    $this->chat = $chat;
  }


  // 交易列表
  public function transaction_list()
  {
    $post = input('post.');
    $demandId = $post['demand_id'];
    $flag = $post['flag'];
    if ($flag != 1 && $flag != 2) {
      throw new ServerException("flag error");
    }
    $list = [];
    if ($flag == 1) {
      $list = $this->orderTransaction->where('buy_did', $demandId)->orderRaw('FIELD(`state`,3,2,1,4),update_time desc')->select()->toArray();

      $transactionCountSum = $this->orderTransaction->where('buy_did', $demandId)->count('id');
      $transactionCountLockNum = $this->orderTransaction->where('buy_did', $demandId)->where('state = 2 and state = 3')->count('id');

      $transactionSumAmount = $this->orderDemand->where('id', $demandId)->value('amount');
      $transactionOnAmount = $this->orderDemand->where('id', $demandId)->value('on_amount + lock_amount');
      $transactionResidueAmount = $this->orderDemand->where('id', $demandId)->value('amount - on_amount - lock_amount');
    }

    if ($flag == 2) {
      $list = $this->orderTransaction->where('sell_did', $demandId)->orderRaw('FIELD(`state`,3,2,1,4),update_time desc')->select();

      $transactionCountSum = $this->orderTransaction->where('sell_did', $demandId)->count('id');
      $transactionCountLockNum = $this->orderTransaction->where('sell_did', $demandId)->where('state = 2 or state = 3')->count('id');

      $transactionSumAmount = $this->orderDemand->where('id', $demandId)->value('amount');
      $transactionOnAmount = $this->orderDemand->where('id', $demandId)->value('on_amount + lock_amount');
      $transactionResidueAmount = $this->orderDemand->where('id', $demandId)->value('amount - on_amount - lock_amount');
    }

    return returnJson(1, '获取成功', ['list' => $list, 'count_sum' => $transactionCountSum, 'count_lock_num' => $transactionCountLockNum, 'sum_amount' => $transactionSumAmount, 'on_amount' => $transactionOnAmount, 'residue_amount' => $transactionResidueAmount]);
  }

  // 交易详情
  public function transaction_info()
  {
    if ($this->request->isPost()) {
      $post = $this->request->post();
      $transactionId = $post['transaction_id'];
      $info = $this->orderTransaction->where('id', $transactionId)->find();
      $info['buy_user_name'] =$info['buy_user_name'];
      $info['sell_user_name'] = $info['sell_user_real_name'];
      $info['buy_user_phone'] = User::get($info['buy_uid'])['phone'];
      $info['sell_user_phone'] = User::get($info['sell_uid'])['phone'];
      $info['buy_user_pname'] = User::get(User::get($info['buy_uid'])->getData('pid'))['real_name'];
      $info['buy_user_pphone'] = User::get(User::get($info['buy_uid'])->getData('pid'))['phone'];
      $info['sell_user_pname'] = User::get(User::get($info['sell_uid'])->getData('pid'))['real_name'];
      $info['sell_user_pphone'] = User::get(User::get($info['sell_uid'])->getData('pid'))['phone'];
      $info['buy_account'] = $info['buy_account'];
      $info['sell_account'] = $info['sell_account'];
      if (!$info['evidence_path']) {
        $info['evidence_path'] = null;
      } else {
        $info['evidence_path'] = $this->request->domain() . '/uploads/' . $info['evidence_path'];
      }
      $chat = $this->chat->where('transaction_id', $transactionId)->where('type', 3)->order('create_time desc')->find();

      if ($chat) {
        $info['appeal'] = $chat  ['content'];
      } else {
        $info['appeal'] = '';
      }
      if ($info->getData('state') == 3) {
        $date = date_create($info['create_time']);
        $config = System_config::get(['name' => 'pay_all_time'])['value'];
        date_add($date, date_interval_create_from_date_string("$config hours"));
        $info['expires_time'] = date_format($date, "Y-m-d H:i:s");
      } else if ($info->getData('state') == 2) {
        $date = date_create($info['pay_time']);
        $config = System_config::get(['name' => 'confirm_time'])['value'];
        date_add($date, date_interval_create_from_date_string("$config hours"));
        $info['expires_time'] = date_format($date, "Y-m-d H:i:s");
      } else {
        $info['expires_time'] = '';
      }
      $list=$info->toArray();
      $list['sell_user_name'] = $info['sell_user_real_name'];
      return returnJson(1, '获取成功', ['info' =>$list ]);
    }
  }

  // 确认付款
  public function pay()
  {
    if ($this->request->isPost()) {
      $post = $this->request->post();
      $transactionId = $post['transaction_id'];
      $uid = $post['id'];

      Db::startTrans();
      try {

        $transaction = $this->orderTransaction->where('id', $transactionId)->lock(true)->find();

        if ($uid != $transaction['buy_uid']) {
          return returnJson(0, '非法操作');
        }

        if ($transaction['state']['value'] != 3) {
          return returnJson(0, '该订单已支付');
        }

        // 更新订单状态
        $this->orderTransaction->where('id', $transactionId)->update(['state' => 2, 'pay_time' => date('Y-m-d H:i:s', time())]);
        //刷新页面
        post('', $this->request->domain() . ":3000/refresh");
        UserActionLog::addActionLog(input('id'), "交易号{$post['transaction_id']}确认付款");


        //发短信
//        $server = new yzmServer();
//        $user = User::get($transaction['sell_uid']);
//        $server->send_sms($user->getData('phone'), "尊敬的{$user->getData('nickname')}，您的订单：{$transaction->getData('transaction_id')} 已完成付款，请即时处理。");
        Db::commit();
      } catch (\Exception $e) {
        if($e instanceof ServerException){
          Db::commit();
        }else{
          Db::rollback();
          return returnJson(0, '操作失败');
        }
      }
    }

    return returnJson(1, '操作成功');
  }

  // 确认放行
  public function release()
  {
    if ($this->request->isPost()) {
      $post = $this->request->post();
      $transactionId = $post['transaction_id'];
      $uid = $post['id'];

      Db::startTrans();
      try {
        $user = User::get($uid);
        if ($user->getData('is_google')) {
          $google2fa = new Google2FA();
          if (input('paypassword') != $google2fa->getCurrentOtp($user->getData('google'))) {
            throw new ServerException('验证码输入错误');
          }
        } else {
          if ($user['paypassword'] != encrypt(input('payPass'))) {
            throw new ServerException('二级密码错误');
          }
        }

        $transaction = $this->orderTransaction->where('id', $transactionId)->lock(true)->find();

        if ($uid != $transaction['sell_uid']) {
          return returnJson(0, '非法操作');
        }

        if ($transaction['state']['value'] != 2) {
          return returnJson(0, '该订单已放行');
        }
        // 更新购买需求订单
        $this->orderDemand->editDemandOnAmount($transaction['buy_did'], $transaction['amount']);

        // 更新出售需求订单
        $this->orderDemand->editDemandOnAmount($transaction['sell_did'], $transaction['amount']);

        // 修改交易状态
        $this->orderTransaction->where('id', $transactionId)->update(['state' => 1, 'is_lock' => 2, 'confirm_time' => date('Y-m-d H:i:s', time())]);

        // ===
        $demandNum = $this->orderDemand->where('id', $transaction['buy_did'])->value('demand_num');
        $orderdemant = OrderDemand::get($transaction['buy_did']);

        if ($orderdemant->getData('state') == 1) {
          $int = $this->order_timeout($orderdemant);
          if ($orderdemant->getData('genre') == 3) {
            $day = 0;
          } else {
            $day = 1;
          }
  
          if ($orderdemant->getData('genre') == 4) {
            User::update(['is_valid' => 1], ['id' => $transaction['buy_uid']]);
          }
          
          $account=Account::get($transaction['buy_uid']);
            $account->setInc('pri_account',1);
          // 生成订单
          $this->order->addOrder($transaction['buy_uid'], $orderdemant['amount'], $demandNum, $int, $day);
        }
        post('', $this->request->domain() . ":3000/refresh");
        UserActionLog::addActionLog(input('id'), "交易号{$post['transaction_id']}确认放行");
        //发短信
//        $server = new yzmServer();
//        $user = User::get($transaction['buy_uid']);
//        $server->send_sms($user->getData('phone'), "尊敬的{$user->getData('nickname')}，您的订单：{$transaction->getData('transaction_id')}已完成。");
//        $user = User::get($transaction['sell_uid']);
//        $server->send_sms($user->getData('phone'), "尊敬的{$user->getData('nickname')}，您的订单：{$transaction->getData('transaction_id')}已完成。");

        Db::commit();
        return returnJson(1, '操作成功');
      } catch (\Exception $e) {
          Db::rollback();
          return returnJson(0, '操作失败');
      }
    }
  }

  // 获取交易信息列表
  public function get_transaction_message_list()
  {
    if ($this->request->isPost()) {
      $post = $this->request->post();
      $uid = $post['uid'];
      $transactionId = $post['transaction_id'];

      $transaction = $this->orderTransaction->where('id', $transactionId)->find();

      $list = $this->chat->where('transaction_id', $transactionId)->order('create_time')->select();

      foreach ($list as $key => $value) {
        if ($value['type']['value'] == 2) {
          if (!$value['img']) {
            $list[$key]['img'] = null;
          } else {
            $list[$key]['img'] = $this->request->domain() . '/uploads/' . $value['img'];
          }
        }

        if ($uid = $transaction['buy_uid']) {
          $list[$key]['user_code'] = $transaction['buy_user_code'];
        }

        if ($uid = $transaction['sell_uid']) {
          $list[$key]['user_code'] = $transaction['sell_user_code'];
        }
      }

      return returnJson(1, '获取成功', ['list' => $list]);
    }
  }

  // 添加交易凭证
  public function put_transaction_evidence()
  {
    if ($this->request->isPost()) {
      $post = $this->request->post();
      $uid = $post['id'];
      $transactionId = $post['transaction_id'];
      Db::startTrans();
      try {
        $user = User::get($uid);

        $transaction = $this->orderTransaction->where('id', $transactionId)->lock(true)->find();
        if ($uid != $transaction['buy_uid']) {
          return returnJson(0, '非法操作');
        }
        $uploads = $this->uploads_img('image');
        if ($uploads !== 0) {
          if ($uploads['code'] == 0) {
            return returnJson(0, $uploads['err']);
          } else {
            $image = $uploads['img'];
          }
        }
        $this->orderTransaction->where('id', $transactionId)->update(['evidence_path' => $image]);
        $this->pay();
        UserActionLog::addActionLog(input('id'), "交易号{$post['transaction_id']}添加交易凭证");
        Db::commit();
      } catch (\Exception $e) {
        Db::rollback();
        return returnJson(0, '提交失败');
      }
      return returnJson(1, '提交成功');
    }
  }

  // 添加申诉信息
  public function put_complain_message()
  {
    if ($this->request->isPost()) {
      $post = $this->request->post();
      $uid = $post['id'];
      $transactionId = $post['transaction_id'];
      $message = $post['message'];
      if (!$message) {
        throw new ServerException('申诉内容不能为空');
      }

      Db::startTrans();
      try {
        $transaction = $this->orderTransaction->where('id', $transactionId)->lock(true)->find();

        if ($uid != $transaction['buy_uid'] && $uid != $transaction['sell_uid']) {
          return returnJson(0, '非法操作');
        }

        if ($transaction['state']['value'] != 2) {
          return returnJson(0, '当前交易状态错误无法操作');
        }

        // 添加申诉信息
        $this->chat->addMessage($transactionId, $uid, $message, '', 3);
        // 锁定交易订单
        $this->orderTransaction->where('id', $transactionId)->update(['is_lock' => 1]);
        UserActionLog::addActionLog(input('id'), "交易号{$post['transaction_id']}添加申诉信息");
        post('', $this->request->domain() . ":3000/refresh");
        Db::commit();
      } catch (\Exception $e) {
        Db::rollback();
        throw $e;
        return returnJson(0, '提交失败');
      }
      return returnJson(1, '申诉成功');
    }
  }

  // 添加文字信息
  public function put_text_message()
  {

    if ($this->request->isPost()) {
      $post = $this->request->post();
      $transactionId = $post['transaction_id'];
      $uid = $post['id'];
      $message = $post['content'];
      if (!trim($message)) {
        return returnJson(0, "发送内容不能为空");
      }
      Db::startTrans();
      try {
        $transaction = $this->orderTransaction->where('id', $transactionId)->lock(true)->find();

        if ($uid != $transaction['buy_uid'] && $uid != $transaction['sell_uid']) {
          return returnJson(0, '非法操作');
        }

        $this->chat->addMessage($transactionId, $uid, $message, '', 1);
        post('', $this->request->domain() . ":3000/refresh");
        UserActionLog::addActionLog(input('id'), "交易号{$post['transaction_id']}添加聊天信息");
        Db::commit();
      } catch (\Exception $e) {
        Db::rollback();
        // throw $e;
        return returnJson(0, '提交失败');
      }


      return returnJson(1, '添加成功');
    }
  }

  // 添加图片信息
  public function put_img_message()
  {
    if ($this->request->isPost()) {
      $post = $this->request->post();
      $transactionId = $post['transaction_id'];
      $uid = $post['id'];

      Db::startTrans();
      try {
        $transaction = $this->orderTransaction->where('id', $transactionId)->lock(true)->find();

        if ($uid != $transaction['buy_uid'] && $uid != $transaction['sell_uid']) {
          return returnJson(0, '非法操作');
        }

        $uploads = $this->uploads_img('img');
        if ($uploads !== 0) {
          if ($uploads['code'] == 0) {
            return returnJson(0, $uploads['err']);
          } else {
            $image = $uploads['img'];
          }
        }
        $this->chat->addMessage($transactionId, $uid, '', $image, 2);
        post('', $this->request->domain() . ":3000/refresh");
        UserActionLog::addActionLog(input('id'), "交易号{$post['transaction_id']}添加聊天信息");
        Db::commit();
      } catch (\Exception $e) {
        Db::rollback();
        throw $e;
        return returnJson(0, '提交失败');
      }

      return returnJson(1, '添加成功');
    }
  }

  // 上传图片
  public function uploads_img($attr)
  {
    $file = $this->request->file($attr);
    if ($file == null) {
      return 0;
    }
    $info = $file->validate(['size' => 1024 * 1024 * 20, 'ext' => 'jpg,png,gif'])->move(ROOT_PATH . 'public' . DS . 'uploads');
    if ($info) {
      return ['code' => 1, 'img' => $info->getSaveName()];
    } else {
      return ['code' => 0, 'err' => $file->getError()];
    }
  }

  //聊天记录
  public function chat_list()
  {
    $post = input('post.');
    $transactionId = $post['transaction_id'];
    $list = $this->chat->where('transaction_id', $transactionId)->where('type in (1,2)')->order('create_time asc')->select();
    foreach ($list as $k => $item) {
      $list[$k]['uid'] = $this->chat->getUserInfo($item['uid']);
      if ($item['type']['value'] == 2) {
        if (!$item['img']) {
          $list[$k]['img'] = null;
        } else {
          $list[$k]['img'] = $this->request->domain() . '/uploads/' . $item['img'];
        }
      }
    }
    return returnJson(1, '获取成功', $list);
  }

  //检测订单超时 返回bl
  public function order_timeout(OrderDemand $orderdemant)
  {
    $list = $this->orderTransaction->where('buy_did', $orderdemant['id'])->select();
    $int = System_config::get(['name' => 'hzjf_bl'])['value'];
    $config = System_config::get(['name' => 'pay_zc_time'])['value'];
    foreach ($list as $item) {
      $time = date_add(date_create($item['create_time']), date_interval_create_from_date_string("$config hours"));
      if (strtotime($item['pay_time']) > strtotime(date_format($time, "Y-m-d H:i:s"))) {
        $int = $int - System_config::get(['name' => 'cs_hzjf_bl'])['value'];
        break;
      }
    }
    return $int;
  }

}
