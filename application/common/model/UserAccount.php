<?php


namespace app\common\model;


use think\Model;

class UserAccount extends Model
{
  // 判断用户是否设置账户
  public function judgeSetAccountInfo($uid)
  {
    $info = $this->where('uid', $uid)->find();
    if (!$info['bank_name'] || !$info['bank_num']) {
      return true;
    } else {
      return false;
    }
  }
}