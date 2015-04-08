<?php
namespace Payum\Stripe\Action\Api;

use Payum\Core\Action\PaymentAwareAction;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Model\ArrayObject as ArrayObjectModel;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Stripe\Keys;
use Payum\Stripe\Request\Api\CancelSubscription;
use Payum\Stripe\Request\Api\ObtainToken;

class CancelSubscriptionAction extends PaymentAwareAction implements ApiAwareInterface
{
    /**
     * @var Keys
     */
    protected $keys;

    /**
     * {@inheritDoc}
     */
    public function setApi($api)
    {
        if (false == $api instanceof Keys) {
            throw new UnsupportedApiException('Not supported.');
        }

        $this->keys = $api;
    }

    /**
     * {@inheritDoc}
     */
    public function execute($request)
    {
        /** @var $request CancelSubscription */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        $model->validateNotEmpty(array('id', 'customer'));

        // set to true to suspend instead of cancelling, note that this only
        // delays the cancellation, stripe does not have a suspension concept
        // @see https://support.stripe.com/questions/how-can-i-resume-a-subscription-after-it-has-been-canceled
        $model['at_period_end'] = $model['at_period_end'] ?: false;

        try {
            \Stripe::setApiKey($this->keys->getSecretKey());

            $customer = \Stripe_Customer::retrieve(array('id' => $model['customer']));
            $subscription = $customer
                ->subscriptions
                ->retrieve($model['id'])
                ->cancel(array('at_period_end' => $model['at_period_end']));

            $model->replace($subscription->__toArray(true));
        } catch (\Stripe_Error $e) {
            $model->replace($e->getJsonBody());
        }
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof CancelSubscription &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
