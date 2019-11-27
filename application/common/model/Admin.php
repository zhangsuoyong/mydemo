<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/17
 * Time: 11:00
 */

namespace app\common\model;


use think\Model;

class Admin extends Model
{
  public function getList($where = [], $query = [])
  {
    $list = $this->where($where)->order('create_time desc')->select();
    return $list;
  }

  public function admin_add($data)
  {
    $this->save($data);
  }

  public function getCatenameAttr()
  {
    return '1';
  }
}