<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 21:53
 */

namespace app\common\server;


interface SlideService extends BaseService
{
  //新增轮播图
  function slide_add();

  //修改轮播图
  function slide_update();

  //更改状态
  function slide_state($id);
}