<?php
namespace Payum\Stripe\Action;

use Payum\Core\Action\ActionInterface;
use Payum\Core\Bridge\Spl\ArrayObject;
use Payum\Core\Exception\RequestNotSupportedException;
use Payum\Core\Request\GetStatusInterface;
use Payum\Stripe\Constants;

class StatusAction implements ActionInterface
{
    /**
     * {@inheritDoc}
     *
     * @param GetStatusInterface $request
     */
    public function execute($request)
    {
        RequestNotSupportedException::assertSupports($this, $request);

        $model = ArrayObject::ensureArrayObject($request->getModel());

        // model is a subscription
        if (isset($model['object']) && $model['object'] == Constants::OBJECT_SUBSCRIPTION) {
            if ($model['status'] == Constants::STATUS_ACTIVE) {
                if ($model['cancel_at_period_end']) {
                    $request->markSuspended();
                    return;
                }

                $request->markCaptured();
                return;
            }

            if ($model['status'] == Constants::STATUS_CANCELED) {
                $request->markCanceled();
                return;
            }

            $request->markUnknown();

            return;
        }

        if (is_null($model['status'])) {
            $request->markUnknown();
            return;
        }

        if (false == $model['status'] && false == $model['card']) {
            $request->markNew();

            return;
        }

        if (false == $model['status'] && $model['card']) {
            $request->markPending();

            return;
        }

        if (Constants::STATUS_FAILED == $model['status']) {
            $request->markFailed();

            return;
        }

        if ($model['refunded']) {
            $request->markRefunded();

            return;
        }

        if (Constants::STATUS_SUCCEEDED == $model['status'] && $model['captured'] && $model['paid']) {
            $request->markCaptured();

            return;
        }

        if (Constants::STATUS_PAID == $model['status'] && $model['captured'] && $model['paid']) {
            $request->markCaptured();

            return;
        }


        if (Constants::STATUS_SUCCEEDED == $model['status'] && false == $model['captured']) {
            $request->markAuthorized();

            return;
        }
        if (Constants::STATUS_PAID == $model['status'] && false == $model['captured']) {
            $request->markAuthorized();

            return;
        }

        $request->markUnknown();
    }

    /**
     * {@inheritDoc}
     */
    public function supports($request)
    {
        return
            $request instanceof GetStatusInterface &&
            $request->getModel() instanceof \ArrayAccess
        ;
    }
}
