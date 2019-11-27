<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/20
 * Time: 15:27
 */

namespace app\tkfadmin\controller;


use app\common\exception\ServerException;
use app\common\serviceimpl\FeedbackServiceImpl;
use think\Request;

class Feedback extends Base
{

  private $service;

  public function __construct(Request $request = null, FeedbackServiceImpl $service)
  {
    parent::__construct($request);
    $this->service = $service;
  }

  public function index()
  {
    $data = [];
    $where = [];
    $where['uid'] = ['>', 0];
    if (input('?keyword')) {
      $where['uid'] = input('keyword');
      $data['keyword'] = input('keyword');
    }
    $query = input('param.');
    $list = $this->service->getList($where, $query);
    $data['list'] = $list;
    return view('index', $data);
  }

  public function reply()
  {
    $id = input('id');
    return view('reply', ['id' => $id]);
  }

  public function reply_add()
  {
    if (!input('?id')) {
      throw new ServerException('请输入id');
    }
    $feedback = \app\common\model\Feedback::get(input('id'));
    if (!$feedback) {
      throw new ServerException('该反馈不存在');
    }
    if (!input('?content')) {
      throw new ServerException('请输入内容');
    }
    $post = input('post.');
    return $this->service->reply($post);
  }
}