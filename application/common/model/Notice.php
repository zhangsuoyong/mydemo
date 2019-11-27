<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/3/24
 * Time: 18:13
 */

namespace app\common\model;

use think\Model;

class Notice extends Model
{

  public function getStateAttr($value)
  {

    $model = ['1' => '显示', '2' => '隐藏'];
    return ['value' => $value, 'msg' => $model[$value]];
  }

  public function getList($where = [], $query = [])
  {
    $list = $this->where($where)->order('create_time desc')
      ->paginate(15, false, ['query' => $query]);
    return $list;
  }

  public function updateState($id)
  {
    $model = $this->find(['id' => $id]);
    $data = [];
    if ($model['state']['value'] == 1) {
      $data = ['state' => 2];
    } else {
      $data = ['state' => 1];
    }
    $int = $model->save($data);
    if ($int) {
      return returnJson(1, '修改成功');
    }
    return returnJson(0, '修改失败，请稍后重试');
  }

}