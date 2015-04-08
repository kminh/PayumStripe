<?php
namespace Payum\Stripe\Action\Api;

use Payum\Core\Action\PaymentAwareAction;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Model\ArrayObject as ArrayObjectModel;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Stripe\Keys;
use Payum\Stripe\Request\Api\ReactivateSubscription;
use Payum\Stripe\Request\Api\ObtainToken;

class ReactivateSubscriptionAction extends PaymentAwareAction implements ApiAwareInterface
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
        $model->validateNotEmpty(array('id', 'customer', 'plan'));

        try {
            \Stripe::setApiKey($this->keys->getSecretKey());

            $customer = \Stripe_Customer::retrieve(array('id' => $model['customer']));
            $subscription = $customer
                ->subscriptions
                ->retrieve($model['id']);
            $subscription->plan = $model['plan']['id'];
            $subscription->save();

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
            $request instanceof ReactivateSubscription &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
