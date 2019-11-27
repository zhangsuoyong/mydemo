<?php


namespace app\common\model;


use think\Model;

class OrderTransactionMemory extends  Model
{

  public function buyUser() {
    return $this->hasOne('User', 'id', 'buy_uid');
  }

  public function sellUser() {
    return $this->hasOne('User', 'id', 'sell_uid');
  }

  public function getBuyUserNameAttr() {
    return $this->buyUser()->value('nickname');
  }

  public function getSellUserNameAttr() {
    return $this->sellUser()->value('nickname');
  }
}