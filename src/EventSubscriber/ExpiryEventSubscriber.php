<?php

namespace Drupal\recurring_payments\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Drupal\state_machine\Event\WorkflowTransitionEvent;

/**
 *
 */
class ExpiryEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents() {
    // The format for adding a state machine event to subscribe to is:
    // {group}.{transition key}.pre_transition or
    // {group}.{transition key}.post_transition
    // depending on when you want to react.
    $events = ['commerce_order.validate.pre_transition' => 'setOrderItemExpiry'];
    // If set post_transition we cannot get expiry date in invoice...
    return $events;
  }

  /**
   * Sets the order item expiry timestamp.
   *
   * @param \Drupal\state_machine\Event\WorkflowTransitionEvent $event
   *   The transition event.
   */
  public function setOrderItemExpiry(WorkflowTransitionEvent $event) {
    // Code that will run when the subscribed event fires.
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    $order = $event->getEntity();

    $order_items = $order->getItems();

    foreach ($order_items as $key => $order_item) {
      $period = $order_item->getPurchasedEntity()->getAttributeValue('attribute_period')->getName();
      $base_time = ($order_item->expiry->value)?:$order->getCompletedTime();

      // Flag to do update original item after saving new item to prevent expiry
      // without new item saved.
      // @todo if not working check if asserting $order_item->expiry_original_item defined
      $update_original_item = !empty($order_item->expiry->value);
      $expiry_date = $this->calcExpiryValue($base_time, $period);
      $order_item->set('expiry', $expiry_date);
      // @todo dispatch event to alert that expired order item has been renewed
      // based on the condition $order_item->expiry->value defined.
      $order_item->save();
      if ($update_original_item) {
        $expiry_item = $order_item->expiry_original_item->entity;
        $expiry_order = $expiry_item->getOrder();
        $expiry_order->state->value = 'replaced';
        // At this point mark invoice autofill to zero to prevent increasing this value
        /*if(isset($expiry_order->invoice_number)){
          $expiry_order->invoice_number->autofill = 0;
        }
        // Done at field level to provide this behavior everytime is saved
         */
        $expiry_order->save();
      }
    }

  }

  /**
   * Returns the expiry valye given completed time and order period.
   *
   * It will be useful when completed_time is known , the process is :
   *  $completed_time + $period.
   *
   * @param int $completed_time
   *   The time when the order was completed.
   * @param string $period
   *   The period interval to be charged.
   *
   * @return int
   *   The expiry time.
   */
  protected function calcExpiryValue($completed_time, $period) {
    $date = new \DateTime();
    $date->setTimestamp($completed_time);
    try {
      $date->add(new \DateInterval('P' . $period));
    }
    catch (Exception $e) {
      return NULL;
    }
    return $date->getTimestamp();
  }

}
