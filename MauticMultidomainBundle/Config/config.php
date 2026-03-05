<?php
declare(strict_types=1);

return [
    'name'        => 'Mautic Multidomain Bundle',
    'description' => 'Enables tracking and serving content across multiple domains dynamically based on the request host.',
    'version'     => '1.0.0',
    'author'      => 'Antigravity',
    'services'    => [
        'events' => [
            'mautic.multidomain.subscriber.request' => [
                'class'     => \MauticPlugin\MauticMultidomainBundle\EventListener\RequestListener::class,
                'arguments' => [
                    'mautic.helper.core_parameters'
                ],
            ],
            'mautic.multidomain.subscriber.email' => [
                'class'     => \MauticPlugin\MauticMultidomainBundle\EventListener\EmailSubscriber::class,
                'arguments' => [
                    'mautic.helper.core_parameters'
                ],
            ],
            'mautic.multidomain.subscriber.tracking' => [
                'class'     => \MauticPlugin\MauticMultidomainBundle\EventListener\TrackingSubscriber::class,
                'arguments' => [
                    'mautic.helper.core_parameters'
                ],
            ],
            'mautic.multidomain.subscriber.config' => [
                'class'     => \MauticPlugin\MauticMultidomainBundle\EventListener\ConfigSubscriber::class,
            ],
        ],
        'forms' => [
            'mautic.multidomain.form.config' => [
                'class' => \MauticPlugin\MauticMultidomainBundle\Form\Type\ConfigType::class,
            ],
        ],
    ],
];
