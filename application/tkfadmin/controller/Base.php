<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/17
 * Time: 10:04
 */

namespace app\tkfadmin\controller;


use app\common\exception\AuthException;
use app\common\model\Node;
use app\common\model\NodeControl;
use app\common\model\RoleNode;
use think\Controller;

abstract class Base extends Controller
{
  protected $beforeActionList = [
      'isLogin',
      'isNode',
  ];

  protected function isLogin()
  {
    if (!session('admin_id')) {
      $this->redirect('login/index');
    }
  }

  protected function isNode($flag = false)
  {
    $request = request();
    //不是超级管理员
    $model = $request->module();
    if ($model != 'tkfadmin') {
      throw new AuthException('模块错误');
    }
    $controller = $request->controller();
    $action = $request->action();
    $admin = session('adminrole');
    if ($admin === 0) {
        return ;
    }

    //获取权限是否存在
    $cid=NodeControl::get(['control'=>$controller]);
    if(!$cid){
      throw new  AuthException('控制器请求错误');
    }
    $cid=NodeControl::get(['control'=>$controller])->getData('id');
    $nid=Node::get(['cid'=>$cid,'action'=>$action]);
    if(!$nid){
      return ;
    }
    $nid=Node::get(['cid'=>$cid,'action'=>$action])->getData('id');
    //获取用户权限
    $list = RoleNode::get(['rid' => $admin,'nid'=>$nid]);
    if(!$list){
      throw new AuthException('您没有相关权限！');
    }
  }

  function __destruct()
  {
    // 强制返回系统入口
    $action = $this->request->controller() . '/' . $this->request->action();
    if ($action != 'Index/index' && !$this->request->isPjax() && !$this->request->isPost() && $action != 'Node/test') {
      $this->redirect('index/index');
    }
  }

  function add()
  {
    return view('add');
  }

  function edit()
  {
    return view('edit');
  }

  function insert()
  {
  }

  function update()
  {

  }

  function del()
  {

  }


}