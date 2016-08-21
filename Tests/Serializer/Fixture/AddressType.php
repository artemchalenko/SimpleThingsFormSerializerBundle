<?php
namespace SimpleThings\FormSerializerBundle\Tests\Serializer\Fixture;

use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class AddressType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('street', TextType::class, ['serialize_xml_attribute' => true])
            ->add('zipCode', TextType::class, ['serialize_xml_attribute' => true])
            ->add('city', TextType::class, ['serialize_xml_attribute' => true]);
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'data_class' => __NAMESPACE__.'\\Address',
            ]
        );
    }

    public function getBlockPrefix()
    {
        return 'address';
    }
}
