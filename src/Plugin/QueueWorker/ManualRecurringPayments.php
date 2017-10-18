<?php

namespace Drupal\recurring_payments\Plugin\QueueWorker;

/**
 * A manual queue processor to check Recurring Payments of orders.
 *
 * @QueueWorker(
 *   id = "manual_recurring_payments",
 *   title = @Translation("Manual Recurring Payments"),
 * )
 */
class ManualRecurringPayments extends RecurringPaymentsBase {}
