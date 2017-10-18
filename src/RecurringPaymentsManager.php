<?php

namespace Drupal\recurring_payments;

use Drupal\profile\Entity\Profile;
use Drupal\Core\Mail\MailManagerInterface;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\Renderer;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Language\LanguageManagerInterface;

/**
 * Class RecurringPaymentsManager.
 *
 * @package Drupal\recurring_payments
 */
class RecurringPaymentsManager implements RecurringPaymentsManagerInterface {

  use StringTranslationTrait;

  /**
   * Drupal\mailsystem\MailsystemManager definition.
   *
   * @var \Drupal\mailsystem\MailsystemManager
   */
  protected $pluginManagerMail;

  /**
   * The renderer.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected $renderer;

  /**
   * The language manager.
   *
   * @var \Drupal\Core\Language\LanguageManagerInterface
   */
  protected $languageManager;

  /**
   * Constructor.
   *
   * @param \Drupal\Core\Mail\MailManagerInterface $mail_manager
   *   The mail manager.
   * @param \Drupal\Core\Render\Renderer $renderer
   *   The renderer.
   * @param \Drupal\Core\Language\LanguageManagerInterface $language_manager
   *   The language manager.
   */
  public function __construct(MailManagerInterface $plugin_manager_mail, Renderer $renderer, LanguageManagerInterface $language_manager) {
    $this->pluginManagerMail = $plugin_manager_mail;
    $this->renderer = $renderer;
    $this->languageManager = $language_manager;
  }

  /**
   * {@inheritdoc}
   */
  public function expire(OrderInterface $order, $act_interval) {
    $order->state->value = 'expired';
    $order->save();
    $to = $order->getEmail();
    $params = [
      'headers' => [
        'Content-Type' => 'text/html',
        'bcc' => $order->getStore()->getEmail(),
      ],
      'from' => $order->getStore()->getEmail(),
      'subject' => $this->t('Item in order #@number has expired', ['@number' => $order->getOrderNumber()]),
    ];

    $items_expiring = $this->getExpiredItem($order, $act_interval);
    if (empty($items_expiring)) {
      return FALSE;
    }
    $body_data = [
      '#theme' => 'recurring_payments_expire',
      '#order_entity' => $order,
      '#user' => $order->getCustomer(),
      '#order_items' => $items_expiring,
      '#renew_url' => $order->getCustomer()->link($this->t('your profile'), 'canonical', ['absolute' => TRUE]),
    ];
    $params['body'] = $this->renderer->executeInRenderContext(new RenderContext(), function () use ($body_data) {
      return $this->renderer->render($body_data);
    });
    if ($customer = $order->getCustomer()) {
      $langcode = $customer->getPreferredLangcode();
    }
    else {
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
    }
    $this->pluginManagerMail->mail('recurring_payments', 'expire', $to, $langcode, $params);
    return TRUE;

  }

  /**
   * {@inheritdoc}
   */
  public function aboutExpire(OrderInterface $order, $remaining_days, $act_interval) {
    $to = $order->getEmail();
    $params = [
      'headers' => [
        'Content-Type' => 'text/html',
        'bcc' => $order->getStore()->getEmail(),
      ],
      'from' => $order->getStore()->getEmail(),
      'subject' => $this->t('Item in order #@number is about expire', ['@number' => $order->getOrderNumber()]),
    ];

    $items_about_expire = $this->getExpiredItem($order, $act_interval);
    if (empty($items_about_expire)) {
      return FALSE;
    }
    $body_data = [
      '#theme' => 'recurring_payments_about_expire',
      '#order_entity' => $order,
      '#user' => $order->getCustomer(),
      '#order_items' => $items_about_expire,
      '#remaining_days' => $remaining_days,
      '#renew_url' => $order->getCustomer()->link($this->t('your profile'), 'canonical', ['absolute' => TRUE]),
    ];
    $params['body'] = $this->renderer->executeInRenderContext(new RenderContext(), function () use ($body_data) {
      return $this->renderer->render($body_data);
    });
    if ($customer = $order->getCustomer()) {
      $langcode = $customer->getPreferredLangcode();
    }
    else {
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
    }
    $this->pluginManagerMail->mail('recurring_payments', 'aboutExpire', $to, $langcode, $params);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function prepareRenewCart(OrderInterface $order, OrderItemInterface $order_item) {
    $new_cart = $this->createOrder($order, [$order_item]);
    if (!$new_cart) {
      // @todo implement manual renew order
      $this->badAutoRenewal($order, $act_interval, $items_expiring);
      return FALSE;
    }
    return $new_cart;
    $to = $order->getEmail();
    $params = [
      'headers' => [
        'Content-Type' => 'text/html',
        'bcc' => $order->getStore()->getEmail(),
      ],
      'from' => $order->getStore()->getEmail(),
      'subject' => $this->t('Item in order #@number has been renewed', ['@number' => $order->getOrderNumber()]),
    ];

    $body_data = [
      '#theme' => 'recurring_payments_renew',
      '#order_entity' => $order,
      '#user' => $order->getCustomer(),
      '#order_items' => $items_expiring,
    ];
    $params['body'] = $this->renderer->executeInRenderContext(new RenderContext(), function () use ($body_data) {
      return $this->renderer->render($body_data);
    });
    if ($customer = $order->getCustomer()) {
      $langcode = $customer->getPreferredLangcode();
    }
    else {
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
    }
    $this->pluginManagerMail->mail('recurring_payments', 'renew', $to, $langcode, $params);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function autoRenew(OrderInterface $order, $act_interval) {
    $items_expiring = $this->getExpiredItem($order, $act_interval);
    if (empty($items_expiring)) {
      return FALSE;
    }
    if (!$this->createOrder($order, $items_expiring)) {
      // @todo Implement autorenew here
      $this->badAutoRenewal($order, $act_interval, $items_expiring);
      return FALSE;
    }
    $to = $order->getEmail();
    $params = [
      'headers' => [
        'Content-Type' => 'text/html',
        'bcc' => $order->getStore()->getEmail(),
      ],
      'from' => $order->getStore()->getEmail(),
      'subject' => $this->t('Item in order #@number has been autorenewed', ['@number' => $order->getOrderNumber()]),
    ];

    $body_data = [
      '#theme' => 'recurring_payments_autorenew',
      '#order_entity' => $order,
      '#user' => $order->getCustomer(),
      '#order_items' => $items_expiring,
    ];
    $params['body'] = $this->renderer->executeInRenderContext(new RenderContext(), function () use ($body_data) {
      return $this->renderer->render($body_data);
    });
    if ($customer = $order->getCustomer()) {
      $langcode = $customer->getPreferredLangcode();
    }
    else {
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
    }
    $this->pluginManagerMail->mail('recurring_payments', 'autorenew', $to, $langcode, $params);
    return TRUE;
  }

  /**
   * Notificates the customer about autorenew failure.
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order that fails to renew.
   * @param array $act_interval
   *   The interval [start, end] when the action must be triggered.
   * @param array $order_items
   *   The orderitems that cannot be renewed.
   */
  protected function badAutoRenew(OrderInterface $order, $act_interval, $order_items) {
    $to = $order->getEmail();
    $params = [
      'headers' => [
        'Content-Type' => 'text/html',
        'bcc' => $order->getStore()->getEmail(),
      ],
      'from' => $order->getStore()->getEmail(),
      'subject' => $this->t('Item in order #@number cannot be renewed', ['@number' => $order->getOrderNumber()]),
    ];

    $body_data = [
      '#theme' => 'recurring_payments_bad_renew',
      '#order_entity' => $order,
      '#user' => $order->getCustomer(),
      '#order_items' => $order_items,
      '#renew_url' => $order->getCustomer()->link($this->t('your profile'), 'canonical', ['absolute' => TRUE]),
    ];
    $params['body'] = $this->renderer->executeInRenderContext(new RenderContext(), function () use ($body_data) {
      return $this->renderer->render($body_data);
    });
    if ($customer = $order->getCustomer()) {
      $langcode = $customer->getPreferredLangcode();
    }
    else {
      $langcode = $this->languageManager->getDefaultLanguage()->getId();
    }
    $this->pluginManagerMail->mail('recurring_payments', 'bad_renew', $to, $langcode, $params);
    return TRUE;
  }

  /**
   * {@inheritdoc}
   */
  public function getExpiredItem(OrderInterface $order, $act_interval) {
    $order_items = $order->getItems();
    $expiring_items = [];
    foreach ($order_items as $key => $item) {
      $item_expiry = $item->get('expiry')->first()->value;
      if ($item_expiry >= $act_interval[0] && $item_expiry <= $act_interval[1]) {
        $expiring_items[] = $item;
      }
    }
    return $expiring_items;
  }

  /**
   * Creates an order copying existent.
   *
   * It will try to create an order copying the one with id $order_id
   * with the same order_items if no new ones are specified in the parameter
   * $order_item_ids.
   * If $order_id does not exist then it will create a new order using
   * the specified $order_item_ids in that situation $order_item_ids are
   * mandatory.
   * Example to create an order copying 20:
   *  recurring_payments_test_create_order(\Drupal\commerce_order\Entity\Order::load(20));
   *
   * @param \Drupal\commerce_order\Entity\OrderInterface $order
   *   The order to be copied.
   * @param \Drupal\commerce_order\Entity\OrderItemInterface[] $order_items
   *   Array with order items of order items.
   *
   * @return \Drupal\commerce_order\Entity\OrderInterface|null
   *   In case of succesful creation, null in other case.
   */
  protected function createOrder($order = NULL, $order_items = NULL) {
    if (!$order) {
      if (!$order_item_ids) {
        // Cannot create an order id without specifying order_items when empty
        // order.
        return NULL;
      }
      else {
        $order_items = entity_load_multiple('commerce_order_item', $order_item_ids);
      }
      $profile = Profile::create([
        'type' => 'customer',
        'uid' => 1,
      ]);
      $profile->save();
    }
    else {
      $profile = $order->getBillingProfile();
      if (!$order_items) {
        $order_items = $order->getItems();
      }
    }
    // Next, we create the order.
    $new_order = $order->createDuplicate();
    $new_order->set('state', 'draft');
    $new_order->save();
    // Save to acquire id.
    $new_order->setItems($order_items);
    $new_order->setOrderNumber($new_order->id());
    $new_order->save();
    return($new_order);
  }

  /*
  DEPRECATED, added from outside as its calculed .
  protected function getActInterval($remaining_days){
  $date = new \DateTime();
  $date->setTimestamp(REQUEST_TIME);
  $date->add(new \DateInterval('P' . $remaining_days . 'D');
  $start = $date->getTimestamp();
  $date->add(new \DateInterval('P1D'));
  $end = $date->getTimestamp();
  return [$start, $end];
  }*/
}
