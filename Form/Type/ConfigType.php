<?php
declare(strict_types=1);

namespace MauticPlugin\MauticMultidomainBundle\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ConfigType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add(
            'allowed_domains',
            TextareaType::class,
            [
                'label'      => 'mautic.multidomain.config.allowed_domains',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.multidomain.config.allowed_domains.tooltip',
                    'placeholder' => 'example.com, client.com',
                    'rows'    => 5,
                ],
                'required'   => false,
            ]
        );

        $builder->add(
            'domain_mailer_map',
            TextareaType::class,
            [
                'label'      => 'mautic.multidomain.config.domain_mailer_map',
                'label_attr' => ['class' => 'control-label'],
                'attr'       => [
                    'class'   => 'form-control',
                    'tooltip' => 'mautic.multidomain.config.domain_mailer_map.tooltip',
                    'placeholder' => "{\n  \"client1.com\": {\n    \"mailer_dsn\": \"smtp://user:pass@smtp.client1.com:587\",\n    \"from_email\": \"info@client1.com\",\n    \"from_name\": \"Client 1\",\n    \"reply_to\": \"reply@client1.com\",\n    \"return_path\": \"bounce@client1.com\",\n    \"headers\": {\n      \"X-Tenant\": \"client1\"\n    }\n  }\n}",
                    'rows'    => 12,
                ],
                'required'   => false,
            ]
        );
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'MauticMultidomainBundle',
        ]);
    }
}
