<?php

namespace Frosh\DevelopmentHelper\Subscriber;

use Shopware\Core\PlatformRequest;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DisableStorefrontErrorHandling implements EventSubscriberInterface
{
    /** @var ContainerBagInterface */
    private $containerBag;

    public function __construct(ContainerBagInterface $containerBag)
    {
        $this->containerBag = $containerBag;
    }

    public static function getSubscribedEvents()
    {
        return [
            KernelEvents::EXCEPTION => ['disableFrontendErrorHandling', -95]
        ];
    }

    public function disableFrontendErrorHandling(ExceptionEvent $event)
    {
        //if we are in dev mode, we will see exceptions
        if ($this->containerBag->all()['kernel.debug']) {
            return;
        }

        if ($event->getRequest()->attributes->has(PlatformRequest::ATTRIBUTE_SALES_CHANNEL_CONTEXT_OBJECT)) {
            $event->stopPropagation();
        }
    }
}
