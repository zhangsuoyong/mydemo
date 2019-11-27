<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/20
 * Time: 14:15
 */

namespace app\common\model;


use think\Model;

class Feedback extends Model
{

  public function getList($where = [], $query = [])
  {
    $list = $this->where($where)->order('create_time desc')
      ->paginate(15, false, ['query' => $query]);
    return $list;
  }

  public function getUidAttr($value)
  {
    $user = User::get($value);
    return ['value' => $value, 'msg' => $user['nickname']];
  }

  public function getStateAttr($value)
  {
    $model = ['1' => '等待回复', '2' => '已回复',];
    return ['value' => $value, 'msg' => $model[$value]];
  }
}