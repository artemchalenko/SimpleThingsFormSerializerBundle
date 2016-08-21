<?php
/**
 * SimpleThings FormSerializerBundle
 *
 * LICENSE
 *
 * This source file is subject to the MIT license that is bundled
 * with this package in the file LICENSE.txt.
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to kontakt@beberlei.de so I can send you a copy immediately.
 */

namespace SimpleThings\FormSerializerBundle\Form\Extension;

use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

use SimpleThings\FormSerializerBundle\Form\EventListener\BindRequestListener;
use SimpleThings\FormSerializerBundle\Serializer\SerializerOptions;

class SerializerTypeExtension extends AbstractTypeExtension
{
    /**
     * @var DecoderInterface
     */
    private $encoderRegistry;

    /**
     * @var SerializerOptions
     */
    private $options;

    /**
     * SerializerTypeExtension constructor.
     *
     * @param DecoderInterface       $encoderRegistry
     * @param SerializerOptions|null $options
     */
    public function __construct(DecoderInterface $encoderRegistry, SerializerOptions $options = null)
    {
        $this->encoderRegistry = $encoderRegistry;
        $this->options = $options ?: new SerializerOptions();
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array                $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder->addEventSubscriber(new BindRequestListener($this->encoderRegistry, $this->options));
    }

    /**
     * @param FormView      $view
     * @param FormInterface $form
     * @param array         $options
     */
    public function finishView(FormView $view, FormInterface $form, array $options)
    {
        foreach ($form->all() as $identifier => $child) {
            if (false === $child->getConfig()->getOption('serialize_only')) {
                continue;
            }

            unset($view->children[$identifier]);
        }
    }

    /**
     * @param OptionsResolver $resolver
     *
     * @throws AccessException
     */
    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(
            [
                'serialize_name' => false,
                'serialize_xml_name' => 'entry',
                'serialize_xml_value' => false,
                'serialize_xml_attribute' => false,
                'serialize_xml_inline' => true,
                'serialize_only' => false,
            ]
        );
    }

    /**
     * @return string
     */
    public function getExtendedType()
    {
        return FormType::class;
    }
}

