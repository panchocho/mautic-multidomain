<?php
declare(strict_types=1);

namespace MauticPlugin\MauticMultidomainBundle\EventListener;

use Mautic\ConfigBundle\ConfigEvents;
use Mautic\ConfigBundle\Event\ConfigBuilderEvent;
use MauticPlugin\MauticMultidomainBundle\Form\Type\ConfigType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class ConfigSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            ConfigEvents::CONFIG_ON_GENERATE => ['onConfigGenerate', 0],
        ];
    }

    public function onConfigGenerate(ConfigBuilderEvent $event): void
    {
        $event->addForm([
            'bundle'     => 'MauticMultidomainBundle',
            'formAlias'  => 'mautic_multidomain_config',
            'formType'   => ConfigType::class,
            'formTheme'  => 'MauticMultidomainBundle:FormTheme\Config',
            'parameters' => $event->getParametersFromConfig('MauticMultidomainBundle'),
        ]);
    }
}
