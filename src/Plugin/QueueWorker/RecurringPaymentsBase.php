<?php

namespace Drupal\recurring_payments\Plugin\QueueWorker;

use Drupal\Core\Entity\EntityStorageInterface;
use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\commerce_order\Entity\OrderInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\recurring_payments\RecurringPaymentsManagerInterface;

/**
 * Provides base functionality for the Recurring Payments Queue Workers.
 */
abstract class RecurringPaymentsBase extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  /**
   * The order storage.
   *
   * @var \Drupal\Core\Entity\EntityStorageInterface
   */
  protected $orderStorage;

  /**
   * The recurring manager.
   *
   * @var \Drupal\recurring_payments\RecurringPaymentsManagerInterface
   */
  protected $recurringPayments;

  /**
   * Creates a new RecurringPaymentsBase object.
   *
   * @param \Drupal\Core\Entity\EntityStorageInterface $order_storage
   *   The order storage.
   */
  public function __construct(EntityStorageInterface $order_storage, RecurringPaymentsManagerInterface $recurring_payments) {
    $this->orderStorage = $order_storage;
    $this->recurringPayments = $recurring_payments;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container, array $configuration, $plugin_id, $plugin_definition) {
    return new static(
      $container->get('entity.manager')->getStorage('commerce_order'),
      $container->get('recurring_payments.manager')
    );
  }

  /**
   * Triages the action depending on remaining_days on an order/order_item.
   *
   * @param array $data
   *   Array containing the data from a createItem:
   *     - order.
   *     - remaining_days.
   *     - act_interval.
   */
  protected function checkExpiring($data) {
    // @todo Renew checkbox not implemented by now.
    // $renewable = $data['order']->get('renew')->first()->value;
    $renewable = FALSE;
    switch ($data['remaining_days']) {
      case '+15':
      case '+7':
      case '+1':
      case '+0':
        if (!$renewable) {
          return $this->recurringPayments->aboutExpire($data['order'], $data['remaining_days'], $data['act_interval']);
        }
        break;

      case '-1':
        if ($renewable) {
          $this->recurringPayments->renew($data['order'], $data['act_interval']);
        }
        else {
          $this->recurringPayments->expire($data['order'], $data['act_interval']);
        }
        break;

      default:
        if (!$renewable) {
          $this->recurringPayments->aboutExpire($data['order'], $data['remaining_days'], $data['act_interval']);
        }
    }
    // Return $order->save();
  }

  /**
   * {@inheritdoc}
   */
  public function processItem($data) {
    /** @var \Drupal\commerce_order\Entity\OrderInterface $order */
    // $order = $this->orderStorage->load($data['order']->id);.
    $order = $data['order'];
    if ((string) $order->getState()->value == "completed" && $order instanceof OrderInterface) {
      // Add more conditions, to avoid checking the expirance.
      \Drupal::logger('recurring_payments')->notice("processItem: " . print_r($order->id(), TRUE));
      $this->checkExpiring($data);
    }
  }

}
