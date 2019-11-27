<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/19
 * Time: 21:56
 */

namespace app\tkfadmin\controller;


use app\common\exception\ServerException;
use app\common\model\AdminLog;
use app\common\serviceimpl\SlideServiceImpl;
use think\Request;

class Slide extends Base
{
  private $service;

  public function __construct(Request $request, SlideServiceImpl $service)
  {
    parent::__construct($request);
    $this->service = $service;
  }


  public function index()
  {
    $where = [];
    $data = [];
    $query = input('param.');
    if (input('id')) {
      $where['id'] = input('id');
      $data['id'] = input('id');
    }
    if (input('type')) {
      $where['type'] = input('type');
    }
    $data['type'] = input('type');
    if (input('content')) {
      $where['content'] = input('content');
      $data['content'] = input('content');
    }
    $list = $this->service->getList($where, $query);
    $data['list'] = $list;
    AdminLog::add_log('查看轮播图列表');
    return view('index', $data);
  }

  public function insert()
  {
    if (!request()->isPost()) {
      return returnJson(0, '网络错误');
    }
    $post = input('post.');
    if (!input('?post.type')) {
      throw  new ServerException('请输入类型');
    }
    if (!input('?post.content')) {
      throw new ServerException('请输入对应id或链接地址');
    }
    if (input('post.type') == 1) {
      $notice = \app\common\model\Notice::get(input('post.content'));
      if (!$notice) {
        throw new ServerException('请输入正确的公告id');
      }
    }
    if (!input('?post.state')) {
      throw new ServerException('请选择状态');
    }
    if (!input('?file.pic')) {
      throw new ServerException('请上传图片');
    }

    $data = up('pic', 'slide');
    if (!$data['code']) {
      throw new ServerException($data['msg']);
    }
    $post['picname'] = $data['msg'];
    AdminLog::add_log('添加轮播图');
    return $this->service->slide_add($post);
  }

  //禁用启用
  public function state()
  {
    //判定
    if (!request()->isPost()) {
      $this->error("请求不合法");
    }
    if (!input('post.id')) {
      throw new ServerException('请传入id');
    }
    AdminLog::add_log('更改轮播图状态');
    return $this->service->slide_state(input('post.id'));
  }

  public function edit()
  {
    if (!input('?id')) {
      throw new ServerException('请传入id');
    }
    $slide = \app\common\model\Slide::get(input('id'));
    if (!$slide) {
      throw  new ServerException('该轮播图不存在');
    }

    return view('edit', ['info' => $slide]);
  }

  public function update()
  {
    if (!request()->isPost()) {
      return returnJson(0, '网络错误');
    }
    $post = input('post.');
    if (!input('?post.id')) {
      throw  new ServerException('请输入id');
    }
    $slide = \app\common\model\Slide::get(input('id'));
    if (!$slide) {
      throw new ServerException('该轮播图不存在');
    }

    if (!input('?post.type')) {
      throw  new ServerException('请输入类型');
    }
    if (!input('?post.content')) {
      throw new ServerException('请输入对应id或链接地址');
    }
    if (input('post.type') == 1) {
      $notice = \app\common\model\Notice::get(input('post.content'));
      if (!$notice) {
        throw new ServerException('请输入正确的公告id');
      }
    }
    if (input('?file.pic')) {
      $data = up('pic', 'slide');
      if (!$data['code']) {
        throw new ServerException($data['msg']);
      }
      $post['picname'] = $data['msg'];
    } else {
      unset($post['pic']);
    }
    AdminLog::add_log('修改轮播图');
    return $this->service->slide_update($post);

  }

  public function del()
  {
    if (!input('?post.id')) {
      throw new ServerException('请输入id');
    }
    $slide = \app\common\model\Slide::get(input('id'));
    if (!$slide) {
      throw new ServerException('该轮播图不存在');
    }
    $int = $slide->delete();
    AdminLog::add_log('删除轮播图');
    if ($int) {
      return returnJson(1, '删除成功');
    }
    return returnJson(0, '删除失败');
  }

}