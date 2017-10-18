<?php

namespace Drupal\recurring_payments\Form;

use Drupal\profile\Entity\Profile;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\commerce_order\Entity\Order;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\PrivateTempStoreFactory;

/**
 *
 */
class RecurringPaymentsTestForm extends FormBase {

  /**
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * Drupal\Core\Entity\Query\QueryFactory definition.
   *
   * @var Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * @var \Drupal\commerce_order\Entity\OrderInterface
   */
  protected $order;

  /**
   * @var \Drupal\commerce_order\Entity\OrderItemInterface
   */
  protected $orderItem;

  /**
   * @var \Drupal\user\PrivateTempStore
   */
  protected $uStore;


  /**
   * @var \Drupal\user\PrivateTempStoreFactory
   */
  protected $tempStoreFactory;

  /**
   * {@inheritdoc}
   */
  public function __construct(QueueFactory $queue, QueueWorkerManagerInterface $queue_manager, QueryFactory $entityQuery, PrivateTempStoreFactory $temp_store_factory) {
    $this->queueFactory = $queue;
    $this->queueManager = $queue_manager;
    $this->entityQuery = $entityQuery;
    $this->tempStoreFactory = $temp_store_factory;
    $this->uStore = $this->tempStoreFactory->get('multistep_data');
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker'),
      $container->get('entity.query'),
      $container->get('user.private_tempstore')
    );
  }

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'recurring_payments_test_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    // Get the current selected order items expiry to append to information.
    $expirylist = [];
    if ($this->uStore->get('order')) {
      $order = $this->uStore->get('order') ? Order::load($this->uStore->get('order')) : NULL;
      foreach ($order->getItems() as $key => $order_item) {
        $expirylist[] = $order_item->get('expiry')->value ? format_date($order_item->get('expiry')->value) : NULL;
      }
    }
    $selected_order_itemexpiry = implode(",", $expirylist);

    $form['help'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Submitting this form will process the test, creating order with concrete item and changing expiring dates and emulating cron routine. Normally it needs to create a dummy order, then change the expiry, and then run the add to queue as daily cron does, after that a cron job must be ran once again by user to send all mails it can for 60 seconds, the remaining ones will be sent in next cron runs.<br/>'),
    ];
    $form['currentexpiry'] = [
      '#type' => 'markup',
      '#markup' => $this->uStore->get('order') ? $this->t('The expiry of selected order is: %expiry', ['%expiry' => $selected_order_itemexpiry]) : "",
    ];

    $form['order'] = [
      '#type'  => 'entity_autocomplete',
      '#target_type' => 'commerce_order',
      '#default_value' => $order,
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Create dummy order'),
      '#button_type' => 'primary',
    ];
    $form['actions']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete specified order'),
      '#button_type' => 'secondary',
      '#submit' => ['::deleteQueue'],
      '#states' => [
        'disabled' => [
          ':input[name="order"]' => ['value' => ""],
        ],
      ],

    ];
    $form['days'] = [
      '#type' => 'select',
      '#title' => $this->t('Select days remaining'),
      '#options' => [
        '15' => 15,
        '7' => 7,
        '1' => 1,
        '0' => 0,
        '-1' => -1,
      ],
    ];
    $form['plans'] = [
      '#type' => 'select',
      '#title' => $this->t('Select new plan'),
      '#options' => $this->listVariationsWithName('commerce_product_variation'),

    ];

    $form['actions']['setExpiry'] = [
      '#type'  => 'submit',
      '#value' => $this->t('Set expiry date to days specified'),
      '#button_type' => 'secondary',
      '#submit' => ['::setExpiry'],
      '#states' => [
        'disabled' => [
          ':input[name="order"]' => ['value' => ""],
        ],
      ],
    ];
    $form['actions']['append_notify'] = [
      '#type' => 'submit',
      '#value' => $this->t('Append notifications to queue and start processing it'),
      '#button_type' => 'secondary',
      '#submit' => ['::appendNotify'],
      '#states' => [
        'disabled' => [
          ':input[name="order"]' => ['value' => ""],
        ],
      ],

    ];
    $form['actions']['change_plan'] = [
      '#type' => 'submit',
      '#value' => $this->t('Change plan'),
      '#button_type' => 'secondary',
      '#submit' => ['::changePlan'],
      '#states' => [
        'disabled' => [
          ':input[name="order"]' => ['value' => ""],
        ],
      ],

    ];
    $this->deleteStore();
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function setExpiry(array &$form, FormStateInterface $form_state) {
    // To prepopulate form with  the same order as we apply the expiry time.
    $this->uStore->set('order', $form_state->getValue('order'));
    $this->order = Order::load($form_state->getValue('order'));

    // TODO aplicar rutina cron.
    kint("will set order expiry to " . $form_state->getValues()['days'] . " days.");
    foreach ($this->order->getItems() as $key => $order_item) {
      $order_item->set('expiry', REQUEST_TIME + (86400 * $form_state->getValues()['days']));
    }
    $order_item->save();

  }

  /**
   *
   */
  public function changePlan(array &$form, FormStateInterface $form_state) {
    // To prepopulate form with  the same order as we apply the expiry time.
    $this->uStore->set('order', $form_state->getValue('order'));
    $variations = $this->listEntities('commerce_product_variation');
    $product_variation = ProductVariation::load($form_state->getValue('plans'));
    if (!isset($product_variation)) {
      kint("could not find product variations, create it first");
    }

    $this->order = Order::load($form_state->getValue('order'));

    // $order = \Drupal\commerce_order\Entity\Order::load(246);.
    // @todo will select only last order item.
    $order_item_original = NULL;
    foreach ($this->order->getItems() as $key => $oi) {
      $order_item_original = $oi;
    }

    $this->orderItem = OrderItem::create([
      'type' => 'default',
      'purchased_entity' => $product_variation,
      'quantity' => 1,
      'field_user_name_vm' => $order_item_original->field_user_name_vm->value,
      'machine_name_vm' => $order_item_original->machine_name_vm->value,
      'unit_price' => $product_variation->getPrice(),
      // 'expiry' => //@todo mark back order as validation state to autofill expire???
    ]);
    $this->orderItem->save();
    $this->order->setItems([$this->orderItem]);
    $this->order->save();

  }

  /**
   *
   */
  public function appendNotify(array &$form, FormStateInterface $form_state) {
    // To prepopulate form with  the same order as we apply the expiry time.
    $this->uStore->set('order', $form_state->getValue('order'));

    recurring_payments_cron(TRUE);
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    // $queue = $this->queueFactory->get('manual_recurring_payments');
    // $queue->deleteQueue();
    $order = Order::load($this->getValue('order'));
    foreach ($order->getItems() as $key => $item) {
      $item->delete();
    }
    $order->delete();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $this->testRoutine();
  }

  /**
   * Helper method that removes all the keys from the store collection used for
   * the multistep form.
   */
  protected function deleteStore() {
    $keys = ['order', 'order_item'];
    foreach ($keys as $key) {
      $this->uStore->delete($key);
    }
  }

  /**
   *
   */
  public function testRoutine() {
    $this->createOrder();
    // Transitioning the order out of the draft state should set the timestamps.
    $this->applyOrderTransition('place');
    // Transitioning the order to completed will set the expire date timestamp.
    $this->applyOrderTransition('validate');
  }

  /**
   * Copiar rutina de .module.
   */
  public function createOrder() {
    $variations = $this->listEntities('commerce_product_variation');
    $product_variation_id = array_values($variations)[0];
    $product_variation = ProductVariation::load($product_variation_id);
    if (!isset($product_variation)) {
      kint("could not find product variations, create it first");
    }

    $user = user_load(1);
    // Create the billing profile as ADMIN.
    $profiles = $this->listEntitiesByUid('profile', $user->id());
    $profile_id = array_values($profiles)[0];
    $profile = Profile::load($profile_id);

    // $order = \Drupal\commerce_order\Entity\Order::load(246);
    // $order_item = \Drupal\commerce_order\Entity\OrderItem::load(362);.
    $this->orderItem = OrderItem::create([
      'type' => 'default',
    // @TODO SEGUIR AQUI!!!
      'purchased_entity' => $product_variation,
      'quantity' => 1,
      'field_user_name_vm' => $user->label(),
      'machine_name_vm' => base64_encode(random_bytes(10)),
      'unit_price' => $product_variation->getPrice(),
      // 'expiry' =>.
    ]);
    $this->orderItem->save();
    // Next, we create the order.
    $this->order = Order::create([
      'type' => 'default',
      'state' => 'draft',
      'mail' => $user->getEmail(),
      'uid' => $user->id(),
      'ip_address' => '127.0.0.1',
      'billing_profile' => $profile,
      'store_id' => 1,
      'order_items' => [$this->orderItem],
      'placed' => time(),
    ]);
    $this->order->save();

    \Drupal::logger('recurring_payments')->notice("Created order: " . $this->order->id() . " and order_item: " . $this->orderItem->id());
    $this->uStore->set('order', $this->order->id());
    return;
    $this->recurring_payments_test_create_order(20);
  }

  /**
   *
   */
  private function deleteOrder() {
    // Delete the Order and the Order Item.
    $this->order->delete();
    $this->orderItem->delete();
  }

  /**
   *
   */
  private function listEntities($entity_type) {
    $query = $this->entityQuery->get($entity_type);
    // $query->condition('field', $id);.
    $entity_ids = $query->execute();
    return $entity_ids;
  }

  /**
   *
   */
  private function listEntitiesByUid($entity_type, $uid) {
    $query = $this->entityQuery->get($entity_type);
    $query->condition('uid', $uid);
    $entity_ids = $query->execute();
    return $entity_ids;
  }

  /**
   *
   */
  private function listVariationsWithName($entity_type) {
    $query = $this->entityQuery->get($entity_type);
    // $query->condition('field', $id);.
    $entity_ids = $query->execute();
    $entity_named = [];
    foreach ($entity_ids as $i => $k) {
      $entity = ProductVariation::load($i);
      $entity_named[$i] = $entity->label();
    }
    return $entity_named;
  }

  /**
   *
   */
  private function applyOrderTransition($transition_name) {
    $transition = $this->order->getState()->getWorkflow()->getTransition($transition_name);
    $this->order->getState()->applyTransition($transition);
    $this->order->save();
  }

}
