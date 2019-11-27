<?php


namespace app\common\serviceimpl;


use app\common\model\Order;
use app\common\model\OrderDemand;
use app\common\model\System_config;
use app\common\server\OrderService;

class OrderServiceImpl implements OrderService
{
  private $demand;
  private $order;

  public function __construct()
  {
    $this->demand = new OrderDemand();
    $this->order = new Order();
  }

  function demand_count($state)
  {
    return $this->demand->where(['state' => $state])->count();
  }

  function get_system_config($name)
  {
    return System_config::get(['name' => $name])['value'];
  }

  function set_system_config($value)
  {
    $int = System_config::update(['value' => $value], ['name' => 'robAmount']);
    if ($int) {
      return returnJson(1, '设置成功');
    } else {
      return returnJson(0, '设置失败');
    }
  }


  function demand_list($where, $query, $page)
  {
    return $this->demand->getList($where, $query, $page);
  }

  function transaction_list($where, $query)
  {
    // TODO: Implement transaction_list() method.
  }

  function order_list($where, $query)
  {
    // TODO: Implement order_list() method.
  }

  function demand_cancel($demand_id)
  {
    $demand = OrderDemand::get($demand_id);
    $int=$demand->save(['state'=>3]);
    if ($int) {
      return returnJson(1, '取消成功');
    } else {
      return returnJson(0, '取消失败');
    }
  }

  function ban_match($demand_id)
  {
    $demand = $this->demand->find($demand_id);
    if ($demand['is_lock']['value'] == 1) {
      $data['is_lock'] = 2;
    } else {
      $data['is_lock'] = 1;
    }
    $int = $demand->save($data);
    if ($int) {
      return returnJson(1, '修改成功');
    } else {
      return returnJson(0, '修改失败');
    }
  }

  function view_chat($demand_id)
  {
    // TODO: Implement view_chat() method.
  }

  function view_voucher($demand_id)
  {
    // TODO: Implement view_voucher() method.
  }

  function priority($demand_id, $sort)
  {
    // TODO: Implement priority() method.
  }

  function finish_match($buy_did, $sell_did)
  {
    // TODO: Implement finish_match() method.
  }

}