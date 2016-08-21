<?php
namespace SimpleThings\FormSerializerBundle\Tests\Serializer\Fixture;

use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('username', TextType::class)
            ->add('email', EmailType::class)
            ->add('birthday', DateType::class, ['widget' => 'single_text'])
            ->add('country', CountryType::class)
            ->add('address', AddressType::class)
            ->add(
                'addresses',
                CollectionType::class,
                [
                    'entry_type' => AddressType::class,
                    'allow_add' => true,
                    'serialize_xml_inline' => false,
                    'serialize_xml_name' => 'address',
                ]
            );
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => __NAMESPACE__.'\\User',
                'serialize_xml_name' => 'user',
            ]
        );
    }

    public function getBlockPrefix()
    {
        return 'user';
    }
}

