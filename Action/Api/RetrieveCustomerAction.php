<?php
namespace Payum\Stripe\Action\Api;

use Payum\Core\Action\GatewayAwareAction;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Model\ArrayObject as ArrayObjectModel;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Stripe\Keys;
use Payum\Stripe\Request\Api\RetrieveCustomer;
use Payum\Stripe\Request\Api\ObtainToken;

class RetrieveCustomerAction extends GatewayAwareAction implements ApiAwareInterface
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
        /** @var $request RetrieveCustomer */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        $model->validateNotEmpty(array('id'));

        try {
            \Stripe::setApiKey($this->keys->getSecretKey());

            $customer = \Stripe_Customer::retrieve($model->toUnsafeArray());

            $model->replace($customer->__toArray(true));
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
            $request instanceof RetrieveCustomer &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
