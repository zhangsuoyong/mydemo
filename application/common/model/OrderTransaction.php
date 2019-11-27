<?php 
namespace app\common\model;
use think\Model;


class OrderTransaction extends Model {
  protected $resultSetType = 'collection';

  public function getList($where = '',$query = []){   
    $list = $this->alias('a')
      ->field('a.*,b.nickname buy_user_name,c.nickname sell_user_name')
      ->join('user b', 'a.buy_uid = b.id')
      ->join('user c', 'a.sell_uid = c.id')
      ->where($where)->orderRaw('a.is_lock ,FIELD(`a`.`state`,2,3,1,4),a.id asc')
      ->paginate(20,false,['query'=> $query]);    
    return $list;
  }

  public function getStateAttr($value){
    $status = ['1' => '已完成', '2' => '待放行', '3' => '待付款', '4' => '已取消'];
    return ['value' => $value, 'msg' => $status[$value]];
  }

  // 关联购买用户
  public function buyUser() {
    return $this->hasOne('User', 'id', 'buy_uid');
  }

  // 关联出售用户
  public function sellUser() {
    return $this->hasOne('User', 'id', 'sell_uid');
  }

  // 自动获取购买用户名称
  public function getBuyUserNameAttr() {
    return $this->buyUser()->value('real_name');
  }


  // 自动获取购买用户名称
  public function getBuyUserUserAttr() {
    return $this->buyUser()->value('user');
  }


  // 自动获取购买用户名称
  public function getBuyUserRealNameAttr() {
    return $this->buyUser()->value('real_name');
  }

  // 自动获取出售用户名称
  public function getSellUserNameAttr() {
    return $this->sellUser()->value('nickname');
  }

  // 自动获取出售用户真实姓名
  public function getSellUserRealNameAttr() {
    return  $this->sellDemand()->value('real_name');
  }

  // 自动获取出售用户账号
  public function getSellUserUserAttr() {
    return $this->sellUser()->value('user');
  }

  public function getBuyUserCodeAttr() {
    return $this->buyUser()->value('rand_code');
  }

  public function getSellUserCodeAttr() {
    return $this->sellUser()->value('rand_code');
  }

  public function getIsLockAttr($value) {
    $status = ['1' => '已锁定', '2' => '未锁定'];
    return ['value' => $value, 'msg' => $status[$value]];
  }

  public function getCreateTimeAttr($time) {
    return $time;
  }

  public function getUpdateTimeAttr($time) {
    return $time;
  }

  // 关联购买需求订单
  public function buyDemand() {
    return $this->hasOne('Order_demand', 'id', 'buy_did');
  }

  // 关联出售需求订单
  public function sellDemand() {
    return $this->hasOne('Order_demand', 'id', 'sell_did');
  }

  // 
  public function getBuyRandCodeAttr() {
    return $this->buyUser()->value('rand_code');
  }

  // 
  public function getSellRandCodeAttr() {
    return $this->sellUser()->value('rand_code');
  }

  public function getBuyAccountAttr() {
    $account = $this->buyDemand()->field('bank_name, bank_num, alipay')->find();
    $info['bank_name'] = $account['bank_name'];
    $info['bank_num'] = $account['bank_num'];
    $info['alipay'] = $account['alipay'];
    return $info;
  }

  public function getSellAccountAttr() {
    $account = $this->sellDemand()->field('bank_name, bank_num, alipay')->find();
    $info['bank_name'] = $account['bank_name'];
    $info['bank_num'] = $account['bank_num'];
    $info['alipay'] = $account['alipay'];
    return $info;
  }

}
