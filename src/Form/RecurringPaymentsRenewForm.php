<?php

namespace Drupal\recurring_payments\Form;

use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\user\UserInterface;
use Drupal\commerce_order\Entity\OrderItemInterface;
use Drupal\recurring_payments\RecurringPaymentsManagerInterface;
use Drupal\Core\Entity\Query\QueryFactory;
use Drupal\Core\Link;

/**
 *
 */
class RecurringPaymentsRenewForm extends FormBase {
  /**
   * @var \Drupal\recurring_payments\RecurringPaymentsManagerInterface
   */
  protected $recurringManager;

  /**
   * @var \Drupal\commerce_order\Entity\OrderItemInterface
   */
  protected $itemToRenew;

  /**
   * Drupal\Core\Entity\Query\QueryFactory definition.
   *
   * @var Drupal\Core\Entity\Query\QueryFactory
   */
  protected $entityQuery;

  /**
   * {@inheritdoc}
   */
  public function __construct(RecurringPaymentsManagerInterface $recurring_manager, QueryFactory $entityQuery) {
    $this->recurringManager = $recurring_manager;
    $this->entityQuery = $entityQuery;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('recurring_payments.manager'),
      $container->get('entity.query')
    );
  }

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'recurring_payments_renew_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state, UserInterface $user = NULL, OrderItemInterface $commerce_order_item = NULL) {
    $this->itemToRenew = $commerce_order_item;
    $item_state = $this->itemToRenew->getOrder()->state->value;
    // Get the orderitem related product id to obtain all variations available.
    $product_id = $this->itemToRenew->getPurchasedEntity()->product_id->target_id;

    if ($this->itemToRenew->getOrder()->getBillingProfile()->uid->target_id != \Drupal::currentUser()->id()) {
      $access = FALSE;
    }
    if (\Drupal::currentUser()->hasPermission('administer orders')) {
      $access = TRUE;
    }
    $form['#access'] = $access;
    $form['help_uncomplete'] = [
      '#type' => 'markup',
      '#markup' => $this->t('The item #@item_id is in state: @state. No renewal action possible.', ['@item_id' => $commerce_order_item->id(), '@state' => $item_state]),
      '#access' => $item_state != 'completed',
    ];

    $form['help'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Submitting this form will add the order item to the cart to start the process to renew the order item #@item_id.', ['@item_id' => $commerce_order_item->id()]),
      '#access' => $item_state == 'completed',
    ];
    $form['cycle'] = [
      '#type' => 'select',
      '#title' => $this->t('Change cycle'),
      '#options' => $this->listVariationsFromProduct($product_id),
    ];

    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Renew'),
      '#button_type' => 'primary',
      '#access' => $item_state == 'completed',
    ];
    // @todo just to pass entity to form (and let be modified in the future...)
    /*$form['order_item'] = [
    '#type' => 'entity_autocomplete',
    '#target_type' => 'commerce_order_item',
    '#default_value' => $this->itemToRenew,
    '#attributes' => ['disabled' => 'disabled']
    ];*/
    // @todo Add modification of order item (period).
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $order_item = $this->itemToRenew;
    // @todo when modifying item is implemented then we need this instead:
    // $order_item = $form->getValue('order_item');
    $order_item_copy = $order_item->createDuplicate();
    $order_item_copy->expiry_original_item->entity = $order_item;

    $product_variation = ProductVariation::load($form_state->getValue('cycle'));
    if ($order_item_copy->getPurchasedEntity()->id() != $product_variation->id()) {
      $order_item_copy->set('purchased_entity', $product_variation);
      $order_item_copy->setUnitPrice($product_variation->getPrice());
    }

    $order_item_copy->save();
    $order = $order_item->getOrder();

    // @todo inject these services:
    $entity_manager = \Drupal::entityManager();
    $cart_manager = \Drupal::service('commerce_cart.cart_manager');
    $cart_provider = \Drupal::service('commerce_cart.cart_provider');

    $cart = $cart_provider->getCart($order->bundle(), $order->getStore());
    if (!$cart) {
      $cart = $cart_provider->createCart($order->bundle(), $order->getStore());
    }
    $cart_manager->emptyCart($cart);
    $cart_manager->addOrderItem($cart, $order_item_copy);

    /** @var \Drupal\commerce\PurchasableEntityInterface $purchased_entity */
    $purchased_entity = $order_item_copy->getPurchasedEntity();

    drupal_set_message($this->t('Renewed @entity added to @cart-link. Please continue here.', [
      '@entity' => $purchased_entity->label(),
      '@cart-link' => Link::createFromRoute($this->t('your cart', [], ['context' => 'cart link']), 'commerce_cart.page')->toString(),
    ]));

    $form_state->setRedirect('commerce_cart.page');

    return;
  }

  /**
   * List all the variations from a product.
   *
   * @var int $product_id
   *   The id of the product to obtain its variations
   */
  private function listVariationsFromProduct($product_id) {
    $query = $this->entityQuery->get('commerce_product_variation');
    $query->condition('product_id', $product_id);
    $entity_ids = $query->execute();
    $entity_named = [];
    foreach ($entity_ids as $i => $k) {
      $entity = ProductVariation::load($i);
      $entity_named[$i] = t($entity->getAttributeValue('attribute_period')->field_label->value);
      // $entity_named[$i] = $entity->label();
    }
    return $entity_named;
  }

}
