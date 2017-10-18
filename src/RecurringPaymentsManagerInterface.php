<?php

namespace Drupal\recurring_payments;

use Drupal\commerce_order\Entity\OrderInterface;

/**
 * Interface RecurringPaymentsManagerInterface.
 *
 * @package Drupal\recurring_payments
 */
interface RecurringPaymentsManagerInterface {

  /**
   * Expires an order.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The original order.
   * @param array $act_interval
   *   The interval when this action needs to be done defined as:
   *     - 0 => start.
   *     - 1 => end.
   */
  public function expire(OrderInterface $order, $act_interval);

  /**
   * Renews an order copying from existing to a new one to keep items.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The original order.
   * @param array $act_interval
   *   The interval when this action needs to be done defined as:
   *      - 0 => start.
   *      - 1 => end.
   */
  public function autoRenew(OrderInterface $order, $act_interval);

  /**
   * Prepare a cart for renewal.
   *
   * It copies an order from an existing one to a new one keeping items.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The original order.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface $order_item
   *   The order items to include in the cart.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface
   *   The new order(cart).
   */
  public function prepareRenewCart(OrderInterface $order, OrderItemInterface $order_item);

  /**
   * The order items are about to expires.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The original order.
   * @param int $days
   *   The days to expire.
   * @param array $act_interval
   *   The interval when this action needs to be done defined as:
   *     - 0 => start.
   *     - 1 => end.
   */
  public function aboutExpire(OrderInterface $order, $days, $act_interval);

}
