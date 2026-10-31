<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Something stopped the order that the shopper can fix. The message is already
 * translated, ready to show on the checkout page.
 */
class CheckoutFailed extends RuntimeException {}
