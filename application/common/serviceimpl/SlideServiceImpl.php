<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 21:55
 */

namespace app\common\serviceimpl;


use app\common\model\Slide;
use app\common\server\SlideService;

class SlideServiceImpl implements SlideService
{

  private $model;

  public function __construct()
  {
    $this->model = new Slide();
  }

  function getList($where = [], $query = [])
  {
    $list = $this->model->getList($where, $query);
    return $list;
  }

  function slide_add($data = [])
  {
    $int = Slide::create($data);
    if ($int) {
      return returnJson(1, '添加成功');
    }
    return returnJson(0, '添加失败');
  }

  function slide_update($data = [])
  {
    $slide = Slide::get($data['id']);
    $int = $slide->save($data);
    if ($int) {
      return returnJson(1, '更改成功');
    }
    return returnJson(0, '更改失败');
  }

  function slide_state($id = '')
  {
    $res = $this->model->updateState($id);
    return $res;
  }
}