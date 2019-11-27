<?php


namespace app\common\model;


use think\Model;

class OrderDemand extends Model
{
  protected $resultSetType = 'collection';

  public function getList($where = '', $query = [], $page = '')
  {
    $list = $this->alias('a')->field('a.*, b.id userid,b.user,b.nickname')
        ->join('user b', 'a.uid = b.id')->where($where)
        ->orderRaw('FIELD(`a`.`state`,2,1,3),update_time desc')
        ->paginate(20, false, ['query' => $query, 'var_page' => $page]);
    return $list;
  }

  /**
   * [addDemand 添加一个需求]
   * @param [type]  $uid    [用户ID]
   * @param [type]  $amount [数量]
   * @param [type]  $type   [类型][1:收购][2:出售]
   * @param [type]  $genre  [类型][1:正常][2:福利][3:抢购][4:首单]
   * @param integer $day [天数]
   * @return [array] $demand [订单数据]
   */
  public function addDemand($uid, $amount, $type, $genre, $day = 0,$is_auto=0)
  {
    $demand['demand_id'] = $this->randDemandId();
    $demand['uid'] = $uid;
    $demand['amount'] = $amount;
    $demand['lock_amount'] = 0;
    $demand['on_amount'] = 0;
    $demand['order_num'] = 0;
    $demand['is_lock'] = 2;

    $demand['sort'] = 0;
    // 首单提高优先级
    if ($genre == 4) {
      $demand['sort'] = 0;
    }
    $demand['type'] = $type;
    $demand['genre'] = $genre;
    $demand['state'] = 2;
    $demand['if_auto']=$is_auto;
    

    $demand['match_time'] = $this->randMatchTime($uid, $type, $day);
    $userAccount = $this->getUserAccount($uid);

    $demand['bank_name'] = $userAccount['bank_name'];
    $demand['real_name'] = User::get($uid)->getData('real_name');
    $demand['bank_num'] = $userAccount['bank_num'];
    $demand['alipay'] = $userAccount['alipay'];

    $demand['demand_num'] = $this->where('type', $type)->where('uid', $uid)->count() + 1;

    $id = $this->insertGetId($demand);
    $demand['id'] = $id;
    return $demand;
  }


  /**
   * [editDemandOnAmount 修改订单已完成的数量]
   * @param  [type]  $id     [交易订单ID]
   * @param  [type]  $amount [数量]
   * @param integer $type [类型;1:减少,2:增加]
   * @return [type]          [description]
   */
  public function editDemandOnAmount($id, $amount, $type = 1)
  {
    switch ($type) {
      case 1:
        $this->where('id', $id)->setDec('lock_amount', $amount);
        $this->where('id', $id)->setInc('on_amount', $amount);
        $this->where('id', $id)->setDec('order_num');

        $buyDemadnOnAmount = $this->where('id', $id)->value('on_amount');
        $buyDemandAmount = $this->where('id', $id)->value('amount');
        if ($buyDemadnOnAmount == $buyDemandAmount) {
          $this->where('id', $id)->update(['state' => 1]);
        }

        break;
      case 2:
        $order=$this->where('id',$id)->find();
        if($order->getData('type')==1){
          $this->where('id', $id)->setDec('lock_amount', $amount);
        }
        $this->where('id', $id)->setDec('order_num');
        break;
      default:
        throw new \Exception("Error Processing Request", 1);
        break;
    }
  }

  // 判断是否是第一次购买
  public function judgeIsFirstBuy($uid)
  {
    $demand = $this->where('uid', $uid)->where('type', 1)->where('genre', 4)->find();
    if ($demand) {
      return false;
    } else {
      return true;
    }
  }

  // 随机生成需求ID
  public function randDemandId()
  {
    return getRandCode(2) . rand(1000, 9999);
  }

  // 随机生成匹配时间
  public function randMatchTime($uid, $type, $day = 0)
  {
    switch ($type) {
      case 1:
        $date = date("Y-m-d", strtotime('+' . $day . 'days', time()));
        break;
      case 2:
        $date = date('Y-m-d');
        break;
    }
    return $date;
  }

  public function lockUserDemand($uid)
  {
    return $this->where('uid', $uid)->where('amount > lock_amount + on_amount')->where('state', 2)->setField('is_lock', 1);
  }

  // 获取用户账号信息
  public function getUserAccount($uid)
  {
    $userAccount = $this->userAccount()->where('uid', $uid)->find();
    return $userAccount;
  }

  public function user()
  {
    return $this->hasOne('User', 'id', 'uid');
  }

  // 关联用户账户表
  public function userAccount()
  {
    return $this->hasOne('UserAccount');
  }

  // 检查用户是否可出售
  public function judgeUserSellTrue($uid)
  {
    $find = $this->where('uid', $uid)->where('type', 2)->where('state', 2)->find();
    if ($find) {
      return false;
    } else {
      return true;
    }
  }

  public function getStateAttr($value)
  {
    $status = ['1' => '已完成', '2' => '进行中', '3' => '已取消'];
    return ['value' => $value, 'msg' => $status[$value]];
  }

  public function getTypeAttr($value)
  {
    $status = ['1' => '购买', '2' => '出售'];
    return ['value' => $value, 'msg' => $status[$value]];
  }

  public function getGenreAttr($value)
  {
    $status = ['1' => '正常', '2' => '福利', '3' => '抢购', '4' => '首单'];
    return ['value' => $value, 'msg' => $status[$value]];
  }

  public function getIsLockAttr($value)
  {
    $status = ['1' => '已锁定', '2' => '未锁定'];
    return ['value' => $value, 'msg' => $status[$value]];
  }

  public function getMatchStateAttr($value)
  {
    $status = ['1' => '是', '2' => '否'];
    return ['value' => $value, 'msg' => $status[$value]];
  }

  public function getUserLockAttr()
  {
    return $this->user()->value('is_lock');
  }

  public function getCreateTimeAttr($time)
  {
    return $time;
  }

  public function getUpdateTimeAttr($time)
  {
    return $time;
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

  public function getUserRandCodeAttr()
  {
    return $this->user()->value('rand_code');
  }
  
   public function getLastdemand($uid){
  	return $this->where('uid','=',$uid)->where('type','=',1)->order('create_time','desc')->find();
  }
  
  public function getMaxProvide($uid){

  	return $this->where (['uid'=>$uid,'type'=>1])->max('amount');
  }

}