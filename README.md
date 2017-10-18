It will add two new fields to Order items:
  - expiry, the date of the order items expiry.
  - expiry_original_item, a reference to original order item.
The logic behind this module is that when a order is completed (moves from validation -> completed so it's named 'validate' transition) a field expiry is defined via (ExpiryEventSubscriber) with order completedTime plus period(a field with period as Php intervaldatetime[ 1M, 1D, 1Y,...]).
When expiry time is near a mail is sent to customer to start renewal process, (done via cron each day), by default it alerts 15D, 7D, 1D before expires. If customer wants to renew it can be done in renewal form (/user/{uid}/ordered-items/{order_item_id}/renew).
When renew is clicked a clone of order_item to be renewed is done, populating fields:
 - and expiry_original_item: with reference to original item.
If cart is completed an event subscription ( ExpiryEventSubscriber ) subscribed to validate.post_transition will do some logics:
  - basically will fill the 
  - set renewed order item expiry field: with original item expiry field plus period.
  - set original order item order to state expired to disable notifications and
    allow renewals.

## templates
The mail alerts can be themed.

@todo it remains as a todo the creation of autorenew process, reusing payment captured data.
@todo the servicerecurringpaymentsmanager currently only adds mail functionality and expiring, so renew and autorenew and preparecart or duplicatecart is not implemented or bad designed.
@todo Replace renew_url to /user/{uid}/ordered-items/{order_item_id}/renew in RecurringPaymentsManager.php
@todo add translate enabled templates for not implemented renew autorenew bad-autorenew
@todo By now it relies in one item per order as:
  - if order item is renewed the whole original order state is marked as expired so if
    there is another order item there it will not be checked for expiry. As the module algorithm only looks for orders in state completed. The workaround is to force one order item per cart.  
    It's not clear the correct behavior, maybe:
      -if there are more than one order item check the dates and alter the treated order item expiry time in the long past and keep the order as completed.
      -if there are more than one order item  remove the treated item from the order and keep the order as completed.

## Dev notes
The production behavior is that via cron and once a day a check will be done looking for expiring soon or expired order items and appending to a working queue, after this check, a cron rerun will try to send the queued notifications to any customer, trying to send all mails it can for 10 seconds, the remaining ones will be sent in next cron runs. 
The behavior can be tested via hidden url: admin/commerce/recurring-payments-test: Submitting this form will process the test, creating order with concrete item and changing expiring dates and emulating cron routine. Normally it needs to create a dummy order, then change the expiry, and then run the add to queue as daily cron does, after that a cron job will be ran once again to send all mails it can for 10 seconds, the remaining ones will be sent in next cron runs. 
