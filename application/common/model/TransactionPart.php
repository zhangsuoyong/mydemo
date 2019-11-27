<?php


namespace app\common\model;


use think\Model;

class TransactionPart extends Model
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
}