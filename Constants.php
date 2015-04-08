<?php
namespace Payum\Stripe;

class Constants
{
    const STATUS_SUCCEEDED = 'succeeded';

    const STATUS_PENDING = 'pending';

    const STATUS_PAID = 'paid';

    const STATUS_FAILED = 'failed';

    const STATUS_ACTIVE = 'active'; // used for subscription

    const STATUS_CANCELED = 'canceled';

    const OBJECT_SUBSCRIPTION = 'subscription';

    const EVENTTYPE_INVOICE_PAYMENT_SUCCEEDED = 'invoice.payment_succeeded';

    private function __construct()
    {
    }
}
