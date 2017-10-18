<?php

namespace Drupal\Tests\commerce\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * Codi per testing:.
 *
 * Crear User
 * Crear Order amb expire a un mes
 * Pagar-lo via transferència
 * Posar-lo com a payment completed
 * Canviar expire a avui. 3 dies. 15 dies. ---> dataprovider.
 *
 * Fer correr la rutina de comprovació del cron,
 * CHANGE RECORDS: fer hook_cron amb argument per permetre fer override de REQUEST_TIME.
 *
 * Comprovar mail out! -> mirar unittest de modul amb mailing.
 *
 * per tirar-ho: ../../bin/phpunit ../modules/custom/recurring_payments/tests/src/Unit/RecurringPaymentsTodayTest.php
 *
 * @coversDefaultClass \Drupal\recurring_payments\RecurringPaymentsManager
 * @group commerce
 */
class RecurringPaymentsTodayTest extends UnitTestCase {

  /**
   * Data provider for testAboutExpire().
   *
   * @return array
   *   Nested arrays of values to check:
   *   - $expire_in
   *   - $order_id
   */
  public function orderItemsDataProvider() {
    return [
      [15 * 86400, 33],
      [18 * 86400, 23],
    ];
  }

  /**
   * ::covers aboutExpire.
   *
   * @dataProvider orderItemsDataProvider
   */
  public function testAboutExpire($expire_in, $order) {
    // ... more code
    // return "OK";.
    echo $expire_in . "\n";
    echo $order;
    // $condition->evaluate($order));
    $this->assertFalse(TRUE);
  }

  /**
   * Tests that an end date that is before the start date produces an exception.
   *
   * @expectedException Exception
   * @expectedExceptionMessage Start date must be before end date
   *
   * public function testAboutExpireInRangeException() {
   * // ... more code in here
   * return TRUE;
   * }*/
}
