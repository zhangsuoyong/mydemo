<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/3/24
 * Time: 19:55
 */

namespace app\tkfadmin\controller;

use app\common\exception\ServerException;
use app\common\model\AdminLog;
use  app\common\model\Notice as Model;
use think\Db;
use think\Exception;
use  think\Request;

class Notice extends Base
{
  protected $model;
  protected $log;

  public function __construct(Request $request = null, Model $model, AdminLog $log)
  {
    parent::__construct($request);
    $this->model = $model;
    $this->log = $log;
  }

  //列表
  public function index()
  {
    $where = [];
    $query = [];
    $keywords = '';
    //搜索条件
    if (input('?param.keyword')) {
      $keywords = input('param.keyword');
      $where['id|title'] = ['like', "%{$keywords}%"];
      $query = input('param.');
    }
    $list = $this->model->getList($where, $query);
    $page = $list->render();
    // 模板变量赋值
    $data = ['list' => $list, 'page' => $page, 'keyword' => $keywords];
    $this->log->add_log("查看公告列表");
    return view('index', $data);
  }

  //禁用启用
  public function state()
  {
    //判定
    if (!request()->isPost()) {
      $this->error("请求不合法");
    }
    if (input('?post.id')) {
      $id = input("post.id");
      $res = $this->model->updateState($id);
      $this->log->add_log("更改公告状态");
      return $res;
    }
  }

  public function add()
  {
    return view('add');
  }

  public function insert()
  {
    $post = input('post.');
    $int = Model::insert($post);
    if ($int) {
      $this->log->add_log("添加公告");
      return returnJson(1, '添加成功');
    } else {
      return returnJson(0, '添加失败');
    }

  }

  public function del()
  {
    if (input('?post.id')) {
      $id = input('post.id');
      $model = Model::get(['id' => $id]);
      if ($model->getData('is_home') == 1) {
        throw new ServerException('主页公告不能删除');
      }

      $int = $model->delete();
      if ($int) {
        $this->log->add_log("删除公告");
        return returnJson(1, '删除成功');
      } else {
        return returnJson(0, '删除失败,请稍后重试 ！');
      }
    }
  }

  public function edit()
  {
    if (input('param.id')) {
      $id = input('param.id');
      $model = Model::get(['id' => $id]);
      return view('edit', ['list' => $model]);
    }
  }

  public function update()
  {
    if (input('?post.id')) {
      $id = input('post.id');
      $model = Model::get(['id' => $id]);
      $post = input('post.');
      $int = $model->save($post);
      if ($int) {
        $this->log->add_log("修改公告");
        return returnJson(1, '更改成功');
      } else {
        return returnJson(0, '更改失败,请稍后重试 ！');
      }
    }
  }

  public function on_home()
  {
    $post = input('post.');
    if (!$post['id']) {
      throw  new ServerException('请选择公告');
    }
    $this->log->add_log('将公告设为主页');
    Db::startTrans();
    try {
      $old = \app\common\model\Notice::get(['is_home' => 1]);
      if ($old) {
        $old->save(['is_home' => 2]);
      }

      $new = \app\common\model\Notice::get($post['id']);
      $new->save(['is_home' => 1]);
      Db::commit();
      return returnJson(1, '设置成功');
    } catch (Exception $e) {
      Db::rollback();
      throw new ServerException('网络错误');
    }


  }
}