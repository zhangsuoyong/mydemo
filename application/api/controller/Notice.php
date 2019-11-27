<?php


namespace app\api\controller;

use app\common\exception\ServerException;
use app\common\model\Information;

class Notice extends UserBase
{
  public function index()
  {
    $information = new Information();
    $page = input("page") ? input("page") : 1;
    $pageSize = 10;
    $p = ($page - 1) * $pageSize . "," . $pageSize;
    $list = $information->field('id,title,create_time,picname')->where('state', 1)->limit($p)->order('create_time desc')->select();
    $count = ceil($information->where('state', 1)->count() / 10);
    return returnJson(1, '查询成功', ['list' => $list, 'count' => $count]);
  }

  public function detail()
  {
    if (!input('notice_id')) {
      throw new ServerException('notice_id error');
    }
    $information = Information::get(function ($query) {
      $query->field('id,title,content,create_time')->where('id', input('notice_id'));
    });
    $information['content'] = str_replace("/uploads/information", request()->domain()."/uploads/information", $information['content']);
    return returnJson(1, '查询成功', $information);
  }

}