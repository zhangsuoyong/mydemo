<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/20
 * Time: 15:21
 */

namespace app\common\serviceimpl;


use app\common\model\Feedback;
use app\common\server\FeedbackService;
use think\Db;
use think\Exception;

class FeedbackServiceImpl implements FeedbackService
{
  private $model;

  public function __construct()
  {
    $this->model = new Feedback();
  }

  function getList($where, $query)
  {
    return $this->model->getList($where, $query);
  }

  function reply($post = [])
  {
    Db::startTrans();
    try {
      $feedback = Feedback::get($post['id']);
      $feedback->save(['state' => 2]);
      Feedback::create(['tid' => $feedback->getData('uid'),
        'content' => $post['content'], 'state' => 1,
        'relation_id' => $feedback['id'],'title'=>'意见反馈回复']);
      Db::commit();
      return returnJson(1, '回复成功');
    } catch (Exception $e) {
      Db::rollback();
      return returnJson(0, '网络错误');
    }


  }
}