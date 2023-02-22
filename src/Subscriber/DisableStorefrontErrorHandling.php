<?php

namespace Frosh\DevelopmentHelper\Subscriber;

use Shopware\Core\PlatformRequest;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DisableStorefrontErrorHandling implements EventSubscriberInterface
{
    public function __construct(private readonly ContainerBagInterface $containerBag)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['disableFrontendErrorHandling', -95]
        ];
    }

    public function disableFrontendErrorHandling(ExceptionEvent $event): void
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
