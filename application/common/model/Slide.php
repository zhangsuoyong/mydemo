<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 22:06
 */

namespace app\common\model;


use app\common\exception\ServerException;
use think\Model;

class Slide extends Model
{
  public function getList($where = [], $query = [])
  {
    $list = $this->where($where)->order('create_time desc')
      ->paginate(15, false, ['query' => $query]);
    return $list;
  }

  public function getContentAttr($value, $data)
  {
    if ($this->getData('type') == 1) {
      $notice = Notice::get($value);
      if ($notice) {
        return ['value' => $value, 'msg' => $notice->getData('title')];
      }
    }
    return ['value' => $value, 'msg' => ''];
  }

  public function getStateAttr($value)
  {
    $model = ['1' => '显示', '2' => '隐藏'];
    return ['value' => $value, 'msg' => $model[$value]];
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

  public function getTypeAttr($value)
  {
    $model = ['1' => '公告', '2' => '链接',];
    return ['value' => $value, 'msg' => $model[$value]];
  }

  public function getPicnameAttr($value)
  {
  	if(!is_cli()){
		$serverName=$_SERVER['HTTP_HOST'];	
  	}else{
  		$serverName=config('domain');
  	}
  	
    return "http://".$serverName . $this->data['picpath'] . '/' . $value;
  }

}