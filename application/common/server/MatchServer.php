<?php

namespace app\common\server;

use app\common\model\OrderDemand;
use app\common\model\OrderTransaction;
use app\common\model\OrderTransactionMemory;
use app\common\model\TransactionPart;
use app\common\model\User;
use think\Db;

class MatchServer
{

  // 购买列表
  protected $buyList = [];
  // 出售列表
  protected $sellList = [];
  // 正在操作的购买需求
  protected $onBuyDemand = null;
  // 正在操作的出售需求
  protected $onSellDemand = null;
  // 已完成的购买需求
  protected $completeBuyList = [];
  // 已完成的出售需求
  protected $completeSellList = [];
  // 交易列表
  protected $transactionList = [];

  // 已匹配金额
  protected $onMatchAmount = 0;
  // 需匹配金额
  protected $matchAmount = 0;

  // 推送信息用户UID
  protected $pushUids = [];


  protected $orderDemand;
  protected $orderTransaction;
  protected $orderTransactionMemory;
  protected $transactionPart;

  protected $debug = false;


  public function __construct()
  {
    $this->orderDemand = new OrderDemand();
    $this->orderTransaction = new OrderTransaction();
    $this->orderTransactionMemory = new OrderTransactionMemory();
    $this->transactionPart=new TransactionPart();
  }

  public function loadAssignDemand($buyDid, $sellDid)
  {
    $order = OrderDemand::get($buyDid);
    if($order->getData('genre')==1){
      $user = User::get($order->getData('uid'));
      if ($user->getData('is_order_first')) {
        $order = $this->orderTransaction->where('buy_uid', $user->getData('id'))->whereTime('create_time', 'today')->find();
        if ($order) {

          if($buyDid!=$order->getData('buy_did')){
            return 1;
          }
        }
      }
    }


    $this->buyList = $this->orderDemand->alias('o')
        ->field('o.*')
        ->join('user u','u.id=o.uid')
        ->where('u.is_lock',2)
        ->where('o.type', 1)->where('o.amount != o.lock_amount + o.on_amount')->where('o.state', 2)->where('o.is_lock', 2)->where('o.id', $buyDid)->select()->toArray();

    $this->sellList = $this->orderDemand->alias('o')
        ->field('o.*')
        ->join('user u','u.id=o.uid')
        ->where('u.is_lock',2)
        ->where('o.type', 2)->where('o.amount != o.lock_amount + o.on_amount')->where('o.state', 2)->where('o.is_lock', 2)->where('o.id', $sellDid)->select()->toArray();
    return 0;
  }

  // 加载需求
  public function loadDemand()
  {
    $this->buyList = $this->getDemand(1);
    $this->sellList = $this->getDemand(2);
  }

  public function loadDemandPart()
  {
    $this->buyList = $this->getDemandPart(1);
    $this->sellList = $this->getDemandPart(2);
  }

  public function getDemandPart($type)
  {

    if($type==1){
      $demand = $this->transactionPart->alias('t')
          ->join('order_demand o','o.id=t.id')
          ->field('o.*')
          ->join('user u','u.id=o.uid')
          ->where('u.is_lock',2)
          ->where('o.type', $type)
          ->order('o.match_time asc , o.sort desc, o.id asc')
          ->where('o.amount != o.lock_amount + o.on_amount')->where('o.state', 2)->where('o.is_lock', 2)->group('uid')->select()->toArray();
    }else{
      $demand = $this->transactionPart->alias('t')
          ->join('order_demand o','o.id=t.id')
          ->field('o.*')
          ->join('user u','u.id=o.uid')
          ->where('u.is_lock',2)
          ->where('o.type', $type)
          ->order('o.match_time asc , o.sort desc,o.id asc')
          ->where('o.amount != o.lock_amount + o.on_amount')->where('o.state', 2)->where('o.is_lock', 2)->select()->toArray();
    }

    return $demand;
  }



  /**
   * [getDemand 获取需求]
   * @param  [type] $type [类型,1:购买,2:出售]
   * @return [array] $demand [数据集]
   */
  public function getDemand($type)
  {
    // ->where('match_time <= CURDATE() or match_state = 1')

    if($type==1){
      $demand = $this->orderDemand->alias('o')
          ->join('user u','u.id=o.uid')
          ->field('o.*')
          ->where('u.is_lock',2)
          ->where('o.type', $type)->where('o.amount != o.lock_amount + o.on_amount')->where('o.state', 2)->where('o.is_lock', 2)->group('uid')
          ->order('o.match_time asc,o.sort desc, o.id asc')->select()->toArray();
    }else{
      $demand = $this->orderDemand->alias('o')
          ->join('user u','u.id=o.uid')
          ->field('o.*')
          ->where('u.is_lock',2)
          ->where('o.type', $type)->where('o.amount != o.lock_amount + o.on_amount')->where('o.state', 2)->where('o.is_lock', 2)
          ->order('o.match_time asc,o.sort desc, o.id asc')->select()->toArray();
    }

    return $demand;
  }

  // 设置匹配数量
  public function setMatchAmount($amount)
  {
    $this->matchAmount = $amount;
  }

  // 开始匹配
  public function match()
  {

    if ($this->matchAmount != 0) {
      if ($this->onMatchAmount >= $this->matchAmount) {
        return true;
      }
    }

    // 设置一个购买需求
    $buyDemand = $this->setOneBuyDemand();
    if ($buyDemand == false) {
      return true;
    }

    // 设置一个出售需求
    $sellDemand = $this->setOneSellDemand();
    if ($sellDemand == false) {
      return true;
    }

    // 检查即将匹配的需求
    $judge = $this->judgeDemand();
    if ($judge == false) {
      $this->debug('检测匹配需求error');
      return false;
    }

    $buyAmount = $this->onBuyDemand['amount'] - $this->onBuyDemand['on_amount'] - $this->onBuyDemand['lock_amount'];

    $sellAmount = $this->onSellDemand['amount'] - $this->onSellDemand['on_amount'] - $this->onSellDemand['lock_amount'];

    $amount = 0;

    if ($sellAmount - $buyAmount < 0) {
      $amount = $sellAmount;
    }

    if ($sellAmount - $buyAmount >= 0) {
      $amount = $buyAmount;
    }

    if ($this->matchAmount != 0) {
      if ($amount > $this->matchAmount - $this->onMatchAmount) {
        $amount = $this->matchAmount - $this->onMatchAmount;
      }
    }

    if ($amount == 0) {
      return false;
    }

    $this->onBuyDemand['lock_amount'] += $amount;
    $this->onBuyDemand['order_num'] += 1;

    $this->onSellDemand['lock_amount'] += $amount;
    $this->onSellDemand['order_num'] += 1;

    $this->putTransaction($this->onBuyDemand['uid'], $this->onBuyDemand['id'], $this->onSellDemand['uid'], $this->onSellDemand['id'], $amount);

    if ($this->matchAmount != 0) {
      $this->onMatchAmount += $amount;
    }

    return $this->match();
  }

  // 获取一个购买需求
  public function setOneBuyDemand($type = 1, $uid = null)
  {
    if (sizeof($this->buyList) == 0 && $this->onBuyDemand['amount'] == $this->onBuyDemand['on_amount'] + $this->onBuyDemand['lock_amount']) {
      $this->debug('购买订单数据异常');
      return false;
    }


    if ($this->onBuyDemand != null && $type == 1) {
      // 判断需求是否完成
      if ($this->onBuyDemand['amount'] != $this->onBuyDemand['lock_amount'] + $this->onBuyDemand['on_amount']) {
        return $this->onBuyDemand;
      } else {
        array_push($this->completeBuyList, $this->onBuyDemand);
      }
    }

    switch ($type) {
      // 正常
      case 1:
        $this->onBuyDemand = array_shift($this->buyList);
        return $this->onBuyDemand;
        break;
      // 获取UID不为某数的数据
      case 2:
        if ($uid == null) {
          $this->debug('Sell UID Error');
        }
        // 将当前购买订单数据插入列表首位
        if ($this->onBuyDemand['lock_amount'] !== $this->onSellDemand['amount']) {
          array_unshift($this->buyList, $this->onSellDemand);
        }

        // 当前购买需求id
        $onBuyDemandId = $this->onBuyDemand['id'];

        // 获取uid 不为某值的数据
        foreach ($this->buyList as $key => $value) {
          if ($value['uid'] != $uid) {
            $this->onBuyDemand = array_splice($this->buyList, $key, 1)[0];
            break;
          }
        }

        if ($onBuyDemandId == $this->onBuyDemand['id']) {
          return false;
        } else {
          return $this->onBuyDemand;
        }

        # code...
        break;
      default:
        throw new \Exception('type error');
        break;
    }

  }

  /**
   * [setOneSellDemand 获取一个出售数据]
   * @param [type] $type [description]
   */
  public function setOneSellDemand($type = 1, $uid = null)
  {
    if (sizeof($this->sellList) == 0 && $this->onSellDemand['amount'] == $this->onSellDemand['on_amount'] + $this->onSellDemand['lock_amount']) {
      $this->debug('出售订单数据异常');

      return false;
    }

    if ($this->onSellDemand != null && $type == 1) {
      // 判断需求是否完成
      if ($this->onSellDemand['amount'] != $this->onSellDemand['lock_amount'] + $this->onSellDemand['on_amount']) {
        return $this->onSellDemand;
      } else {
        array_push($this->completeSellList, $this->onSellDemand);
      }
    }

    switch ($type) {
      // 正常获取
      case 1:
        $this->onSellDemand = array_shift($this->sellList);
        return $this->onSellDemand;
        break;
      // 获取UID不为某数的数据
      case 2:
        if ($uid == null) {
          $this->debug('Sell UID Error');
        }
        // 将当前出售订单数据插入列表首位
        if ($this->onSellDemand['lock_amount'] !== $this->onSellDemand['amount']) {
          array_unshift($this->sellList, $this->onSellDemand);
        }
        // 当前出售订单ID 
        $onSellDemandId = $this->onSellDemand['id'];

        // 获取uid 不为某值的数据
        foreach ($this->sellList as $key => $value) {
          if ($value['uid'] != $uid) {
            $this->onSellDemand = array_splice($this->sellList, $key, 1)[0];
            break;
          }
        }

        if ($onSellDemandId == $this->onSellDemand['id']) {
          return false;
        } else {
          return $this->onSellDemand;
        }
        break;
      default:
        throw new \Exception('type error');
        break;
    }

  }

  // 检测需求是否异常
  public function judgeDemand()
  {
    // 判断出售用户是否相等
    if ($this->onBuyDemand['uid'] == $this->onSellDemand['uid']) {
      // 重新获取出售订单
      $sellDemand = $this->setOneSellDemand(2, $this->onSellDemand['uid']);
      if ($sellDemand == false) {
        $buyDemand = $this->setOneBuyDemand(2, $this->onBuyDemand['uid']);
        if ($buyDemand == false) {
          $this->debug('购买需求设置失败');
          return false;
        }
      }
    }

    return true;
  }

  // 添加一条交易
  public function putTransaction($buyUid, $buyDemandId, $sellUid, $sellDemandId, $amount)
  {
    $transaction['transaction_id'] = getRandCode(3) . rand(1000, 9999);
    $transaction['buy_uid'] = $buyUid;
    $transaction['buy_did'] = $buyDemandId;
    $transaction['sell_uid'] = $sellUid;
    $transaction['sell_did'] = $sellDemandId;
    $transaction['amount'] = $amount;
    array_push($this->transactionList, $transaction);
  }

  // 写入临时表保存临时匹配数据
  public function writeMemory()
  {
    $this->orderTransactionMemory->query('truncate table tkf_order_transaction_memory');
    $this->orderTransactionMemory->insertAll($this->transactionList);
  }

  // 完成数据
  public function finishData()
  {
    Db::startTrans();
    try {
      $list = $this->orderTransactionMemory->select();

      foreach ($list as $key => $value) {
        $this->orderTransaction->insert($value->toArray());
        $this->orderDemand->where('id', $value['buy_did'])->setInc('order_num');
        $this->orderDemand->where('id', $value['buy_did'])->setInc('lock_amount', $value['amount']);
        $this->orderDemand->where('id', $value['sell_did'])->setInc('order_num');
        $this->orderDemand->where('id', $value['sell_did'])->setInc('lock_amount', $value['amount']);
        array_push($this->pushUids, $value['buy_uid']);
        array_push($this->pushUids, $value['sell_uid']);
      }

      $this->orderTransactionMemory->query('truncate table tkf_order_transaction_memory');
      Db::commit();
    } catch (\Exception $e) {
      // 回滚事务
      Db::rollback();
      return false;
    }
    return true;
  }


  public function getTransactionList()
  {
    return $this->transactionList;
  }

  public function getCompleteBuyList()
  {
    return $this->completeBuyList;
  }

  public function getCompleteSellList()
  {
    return $this->completeSellList;
  }

  public function getBuyList()
  {
    return $this->buyList;
  }

  public function getSellList()
  {
    return $this->sellList;
  }

  public function getPushUids()
  {
    return array_unique($this->pushUids);
  }

  public function debug($msg)
  {
    if ($this->debug) {
      throw new \Exception($msg);
    }
  }
}