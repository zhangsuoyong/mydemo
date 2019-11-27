<?php


namespace app\common\server;


interface OrderService
{
  //需求列表
  function demand_list($where, $query,$page);

  //交易列表
  function transaction_list($where, $query);

  //订单列表
  function order_list($where, $query);

  //取消需求
  function demand_cancel($demand_id);

  //禁止匹配
  function ban_match($demand_id);

  //查看聊天记录
  function view_chat($demand_id);

  //查看凭证
  function view_voucher($demand_id);

  //提升优先级
  function priority($demand_id, $sort);

  //完成匹配
  function finish_match($buy_did, $sell_did);

  //需求数量统计
  function demand_count($state);

  //获取抢购额度，已抢购额度
  function get_system_config($name);

  //设置抢购额度
  function set_system_config($value);
}