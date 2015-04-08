<?php
namespace Payum\Stripe\Action\Api;

use Payum\Core\Action\PaymentAwareAction;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Model\ArrayObject as ArrayObjectModel;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Stripe\Keys;
use Payum\Stripe\Request\Api\CreateCustomer;
use Payum\Stripe\Request\Api\ObtainToken;

class CreateCustomerAction extends PaymentAwareAction implements ApiAwareInterface
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
        /** @var $request CreateCharge */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        $model->validateNotEmpty(array('plan'));

        if (false == $model['card']) {
            $this->payment->execute(new ObtainToken($model));
        }

        \Stripe::setApiKey($this->keys->getSecretKey());

        // remove unused data
        $customerDescription = $model['cus_description'];
        $email = $model['email'];
        unset($model['description']);
        unset($model['cus_description']);
        unset($model['email']);

        // if an id is set, we try to retrieve customer first
        if (!empty($model['id'])) {
            try {
                $customer = \Stripe_Customer::retrieve($model['id']);

                unset($model['id']);

                // create new subscription for this customer
                try {
                    $subscription = $customer->subscriptions->create($model->toUnsafeArray());
                    $model->replace($subscription->__toArray(true));
                } catch (\Stripe_Error $e) {
                    $model->replace($e->getJsonBody());
                }

                return;
            } catch (\Stripe_Error $e) {
                // probably customer does not exist yet
            }
        }

        // should have when creating new customer
        $model['email'] = $email;
        $model['description'] = $customerDescription;

        // new customer
        try {
            $customer = \Stripe_Customer::create($model->toUnsafeArray());
            $subscription = $customer->subscriptions->data[0];

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
            $request instanceof CreateCustomer &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
