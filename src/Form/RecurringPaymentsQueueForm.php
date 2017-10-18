<?php

namespace Drupal\recurring_payments\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Queue\QueueFactory;
use Drupal\Core\Queue\QueueWorkerManagerInterface;
use Drupal\Core\Queue\SuspendQueueException;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 *
 */
class RecurringPaymentsQueueForm extends FormBase {

  /**
   * @var \Drupal\Core\Queue\QueueFactory
   */
  protected $queueFactory;

  /**
   * @var \Drupal\Core\Queue\QueueWorkerManagerInterface
   */
  protected $queueManager;

  /**
   * {@inheritdoc}
   */
  public function __construct(QueueFactory $queue, QueueWorkerManagerInterface $queue_manager) {
    $this->queueFactory = $queue;
    $this->queueManager = $queue_manager;
  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('queue'),
      $container->get('plugin.manager.queue_worker')
    );
  }

  /**
   * {@inheritdoc}.
   */
  public function getFormId() {
    return 'recurring_payments_form';
  }

  /**
   * {@inheritdoc}.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $this->queueFactory->get('cron_recurring_payments');
    /*
    @todo create a table with pending items
    $header = array_keys($queue->claimItem(1)->data);
    $form['queued_items'] = [
    '#type' => 'table',
    '#header' => $header,
    '#rows' => $rows,
    ];
     */

    $form['help'] = [
      '#type' => 'markup',
      '#markup' => $this->t('Submitting this form will process the recurring payments Manual Queue which contains @number items.', ['@number' => $queue->numberOfItems()]),
    ];
    $form['actions']['#type'] = 'actions';
    $form['actions']['submit'] = [
      '#type' => 'submit',
      '#value' => $this->t('Process queue'),
      '#button_type' => 'primary',
    ];
    $form['actions']['delete'] = [
      '#type' => 'submit',
      '#value' => $this->t('Delete queue'),
      '#button_type' => 'secondary',
      '#submit' => ['::deleteQueue'],
      '#disabled' => $queue->numberOfItems() < 1,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function deleteQueue(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $this->queueFactory->get('cron_recurring_payments');
    $queue->deleteQueue();
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    /** @var \Drupal\Core\Queue\QueueInterface $queue */
    $queue = $this->queueFactory->get('cron_recurring_payments');
    /** @var \Drupal\Core\Queue\QueueWorkerInterface $queue_worker */
    $queue_worker = $this->queueManager->createInstance('cron_recurring_payments');

    while ($item = $queue->claimItem()) {
      try {
        $queue_worker->processItem($item->data);
        $queue->deleteItem($item);
      }
      catch (SuspendQueueException $e) {
        $queue->releaseItem($item);
        break;
      }
      catch (\Exception $e) {
        watchdog_exception('recurring_payments', $e);
      }
    }
  }

}
