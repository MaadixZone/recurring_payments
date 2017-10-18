<?php

namespace Drupal\Tests\commerce\Unit;

use Drupal\Tests\UnitTestCase;

/**
 * CoversDefaultClass \Drupal\commerce\AvailabilityManager.
 *
 * @group commerce
 */
class RecurringPaymentsMailTest extends UnitTestCase {

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
    ];
  }

  /**
   * @covers ::aboutExpire
   * @dataProvider orderItemsDataProvider
   */
  public function testAboutExpire($expire_in, $order) {
    // ... more code.
  }

}
