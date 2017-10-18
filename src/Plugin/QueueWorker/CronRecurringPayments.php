<?php

namespace Drupal\recurring_payments\Plugin\QueueWorker;

/**
 * A Cron queue processor to check Recurring Payments of orders.
 *
 * @QueueWorker(
 *   id = "cron_recurring_payments",
 *   title = @Translation("Manual Recurring Payments"),
 *   cron = {"time" = 60}
 * )
 */
class CronRecurringPayments extends RecurringPaymentsBase {}
