<?php
/**
 * Created by PhpStorm.
 * User: stepfensl
 * Date: 2019/5/20
 * Time: 15:17
 */

namespace app\common\server;


interface FeedbackService extends BaseService
{
  //回复
  function reply();

}