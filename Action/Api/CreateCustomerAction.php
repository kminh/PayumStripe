<?php
namespace Payum\Stripe\Action\Api;

use Payum\Core\Action\GatewayAwareAction;
use Payum\Core\ApiAwareInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Model\ArrayObject as ArrayObjectModel;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Exception\UnsupportedApiException;
use Payum\Stripe\Keys;
use Payum\Stripe\Request\Api\CreateCustomer;
use Payum\Stripe\Request\Api\ObtainToken;

class CreateCustomerAction extends GatewayAwareAction implements ApiAwareInterface
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
        /** @var $request CreateCustomer */
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());
        $model->validateNotEmpty(array('plan', 'description', 'cus_description', 'email', 'metadata'));

        if (false == $model['card']) {
            $this->gateway->execute(new ObtainToken($model));
        }

        \Stripe::setApiKey($this->keys->getSecretKey());

        // backup and remove unused data
        $customerDescription = $model['cus_description'];
        $email               = $model['email'];
        $metaData            = $model['metadata'];

        unset($model['description']);
        unset($model['cus_description']);
        unset($model['email']);
        unset($model['metadata']);

        // if an id is set, we try to retrieve customer first
        if (!empty($model['id'])) {
            $id = $model['id'];
            unset($model['id']);

            try {
                $customer = \Stripe_Customer::retrieve(array('id' => $id, 'expand' => array('default_source')));

                // create new subscription for this customer
                try {
                    // in case this customer exists but has no subscription we
                    // can't create new one, must create new customer
                    if (!is_null($customer->subscriptions)) {
                        // use existing card if it's the same one being submitted
                        // otherwise Stripe will remove default card and
                        // replace with this one
                        if (isset($model['card']) && isset($customer->default_source)) {
                            // retrieve currently submitted card from token to compare
                            try {
                                $submittedCard = \Stripe_Token::retrieve($model['card']);

                                // use currently registered card instead
                                if ($submittedCard->card->fingerprint == $customer->default_source->fingerprint) {
                                    unset($model['card']);
                                }
                            } catch (\Stripe_Error $e) {
                                $model->replace($e->getJsonBody());
                                return;
                            }
                        }

                        $subscription = $customer->subscriptions->create($model->toUnsafeArray());
                        $model->replace($subscription->__toArray(true));

                        return;
                    }
                } catch (\Stripe_Error $e) {
                    $model->replace($e->getJsonBody());
                    return;
                }
            } catch (\Stripe_InvalidRequestError $e) {
                // probably customer does not exist yet
            } catch (\Stripe_Error $e) {
                // an unrecoverable error
                $model->replace($e->getJsonBody());
                return;
            }
        }

        // should have when creating new customer
        $model['email']       = $email;
        $model['description'] = $customerDescription;
        $model['metadata']    = $metaData;

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
