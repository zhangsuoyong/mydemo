<?php

namespace app\common\model;

use think\Model;

//订单
class Order extends Model
{

  public function getList($where = '', $query = [])
  {
    $list = $this->alias('a')
        ->field('a.*, b.nickname user_name')
        ->join('user b', 'a.uid = b.id')
        ->where($where)->orderRaw('FIELD(a.state,2,1,3,4,5),id asc')->paginate(20, false, ['query' => $query]);
    return $list;
  }

  // 添加订单
  public function addOrder($uid, $amount, $orderNum, $int,$day=1)
  {
    $order['uid'] = $uid;
    $order['amount'] = $amount;
    $order['profit_ratio'] = $int;
    $order['profit'] = $int * $amount;
    $order['point_ratio'] = 0;
    $order['day'] =$day;
    $order['state'] = 1;
    $order['order_num'] = $orderNum;
    $config = intval(System_config::get(['name'=>'frozen'])['value'])-1;
    $order['next_gain_time'] = date("Y-m-d", strtotime(" $config days"));
    $account = Account::get($uid);
    $account->setInc('pow', ($order['amount'] + $order['profit']));
    $this->insert($order);
    return $order;
  }

  // 关联用户表
  public function user()
  {
    return $this->hasOne('User', 'id', 'uid');
  }

  public function getUserNameAttr()
  {
    return $this->user()->value('nickname');
  }
  public function getUserUserAttr()
  {
    return $this->user()->value('user');
  }
  public function getUserRealNameAttr()
  {
    return $this->user()->value('real_name');
  }

  public function getStateAttr($value)
  {
    $status = ['1' => '进行中', '2' => '已完成','3'=>'已解冻','4'=>'已提本金','5'=>'已取消'];
    return ['value' => $value, 'msg' => $status[$value]];
  }

  public function getCreateTimeAttr($time)
  {
    return $time;
  }

  public function getUpdateTimeAttr($time)
  {
    return $time;
  }

  public function getUserRandCodeAttr()
  {
    return $this->user()->value('rand_code');
  }



}
