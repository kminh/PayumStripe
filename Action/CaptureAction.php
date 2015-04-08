<?php
namespace Payum\Stripe\Action;

use Payum\Core\Action\PaymentAwareAction;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Model\ArrayObject as ArrayObjectModel;
use Payum\Core\Request\Capture;
use Payum\Stripe\Request\Api\CreateCharge;
use Payum\Stripe\Request\Api\ObtainToken;
use Payum\Stripe\Request\Api\CreateCustomer;
use Payum\Stripe\Request\Api\CreatePlan;

class CaptureAction extends PaymentAwareAction
{
    /**
     * {@inheritDoc}
     *
     * @param Capture $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        if (is_array($model['card'])) {
            return;
        }

        if (false == $model['card']) {
            $this->payment->execute(new ObtainToken($model));
        }

        // if plan is set we need to subscribe
        if (!empty($model['plan'])) {
            $this->payment->execute(new CreateCustomer($model));
            return;
        }

        $this->payment->execute(new CreateCharge($model));
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof Capture &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
