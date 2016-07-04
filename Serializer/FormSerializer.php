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

namespace SimpleThings\FormSerializerBundle\Serializer;

use Doctrine\Common\Inflector\Inflector;
use Nelmio\ApiDocBundle\Tests\Fixtures\Form\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormTypeInterface;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\Exception\AccessException;
use Symfony\Component\OptionsResolver\Exception\InvalidOptionsException;
use Symfony\Component\OptionsResolver\Exception\MissingOptionsException;
use Symfony\Component\OptionsResolver\Exception\NoSuchOptionException;
use Symfony\Component\OptionsResolver\Exception\OptionDefinitionException;
use Symfony\Component\OptionsResolver\Exception\UndefinedOptionsException;
use Symfony\Component\Serializer\Encoder\EncoderInterface;
use Symfony\Component\Form\Exception\UnexpectedTypeException;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class FormSerializer implements FormSerializerInterface
{
    /**
     * @var FormFactoryInterface
     */
    private $factory;

    /**
     * @var EncoderInterface
     */
    private $encoder;

    /**
     * @var SerializerOptions
     */
    private $options;

    /**
     * FormSerializer constructor.
     *
     * @param FormFactoryInterface   $factory
     * @param EncoderInterface       $encoder
     * @param SerializerOptions|null $options
     */
    public function __construct(
        FormFactoryInterface $factory,
        EncoderInterface $encoder,
        SerializerOptions $options = null
    ) {
        $this->factory = $factory;
        $this->encoder = $encoder;
        $this->options = $options ?: new SerializerOptions;
    }

    /**
     * @param array|\Traversable $list
     * @param FormTypeInterface  $type
     * @param string             $format
     * @param string             $xmlRootName
     *
     * @return string
     * @throws UndefinedOptionsException
     * @throws OptionDefinitionException
     * @throws NoSuchOptionException
     * @throws MissingOptionsException
     * @throws InvalidOptionsException
     * @throws AccessException
     * @throws UnexpectedTypeException
     */
    public function serializeList($list, $type, $format, $xmlRootName = 'entries')
    {
        if (!($type instanceof FormTypeInterface) && !is_string($type)) {
            throw new UnexpectedTypeException($type, 'string|FormTypeInterface');
        }

        $resolver = new OptionsResolver();
        $type->setDefaultOptions($resolver);
        $typeOptions = $resolver->resolve([]);

        $options = [];
        $options['entry_type'] = $type;
        $options['serialize_xml_inline'] = true;

        $formOptions = [];
        $formOptions['serialize_xml_name'] = $xmlRootName;

        $reflection = new \ReflectionClass($type);
        $name = isset($typeOptions['serialize_xml_name'])
            ? $typeOptions['serialize_xml_name']
            : Inflector::tableize($reflection->getShortName());
        $list = [$name => $list];

        $builder = $this->factory->createBuilder(FormType::class, $list, $formOptions);
        $builder->add($name, CollectionType::class, $options);

        return $this->serialize($list, $builder, $format);
    }

    /**
     * @param mixed                                                $object
     * @param FormBuilderInterface|FormInterface|FormTypeInterface $typeBuilder
     * @param string                                               $format
     *
     * @return string
     * @throws UnexpectedValueException
     * @throws InvalidOptionsException
     * @throws UnexpectedTypeException
     */
    public function serialize($object, $typeBuilder, $format)
    {
        if (($typeBuilder instanceof FormTypeInterface) || is_string($typeBuilder)) {
            $form = $this->factory->create($typeBuilder, $object);
        } else {
            if ($typeBuilder instanceof FormBuilderInterface) {
                $typeBuilder->setData($object);
                $form = $typeBuilder->getForm();
            } else {
                if ($typeBuilder instanceof FormInterface) {
                    $form = $typeBuilder;
                    if (!$form->isSubmitted()) {
                        $form->setData($object);
                    }
                } else {
                    throw new UnexpectedTypeException(
                        $typeBuilder,
                        'FormInterface|FormTypeInterface|FormBuilderInterface'
                    );
                }
            }
        }

        $options = $form->getConfig()->getOptions();
        $xmlName = isset($options['serialize_xml_name'])
            ? $options['serialize_xml_name']
            : 'entry';

        if ($form->isSubmitted() && !$form->isValid()) {
            $data = $this->serializeFormError($form);
            $xmlName = 'form';
        } else {
            $data = $this->serializeForm($form, $format === 'xml');
        }

        if ($format === 'json' && $this->options->getIncludeRootInJson()) {
            $data = [$xmlName => $data];
        }

        if ($format === 'xml') {
            $appXmlName = $this->options->getApplicationXmlRootName();

            if ($appXmlName && $appXmlName !== $xmlName) {
                $data = [$xmlName => $data];
                $xmlName = $appXmlName;
            }

            $this->encoder->getEncoder('xml')->setRootNodeName($xmlName);
        }

        return $this->encoder->encode($data, $format);
    }

    /**
     * @param FormInterface $form
     *
     * @return array
     */
    private function serializeFormError(FormInterface $form)
    {
        $result = [];

        foreach ($form->getErrors() as $error) {
            $result['error'][] = $error->getMessage();
        }

        foreach ($form->all() as $child) {
            $errors = $this->serializeFormError($child);

            if ($errors) {
                $result['children'][$child->getName()] = $errors;
            }
        }

        return $result;
    }

    /**
     * @param FormInterface $form
     * @param boolean       $isXml
     *
     * @return array|mixed
     */
    private function serializeForm(FormInterface $form, $isXml)
    {
        if (!$form->all()) {
            return $form->getViewData();
        }

        $data = [];
        $namingStrategy = $this->options->getNamingStrategy();

        foreach ($form->all() as $child) {
            $options = $child->getConfig()->getOptions();
            $name = $options['serialize_name'] ?: $namingStrategy->translateName($child);

            if ($isXml) {
                $name = (!$options['serialize_xml_value'])
                    ? ($options['serialize_xml_attribute'] ? '@'.$name : $name)
                    : '#';
            }

            if (!$options['serialize_xml_inline'] && $isXml) {
                $data[$name][$options['serialize_xml_name']] = $this->serializeForm($child, $isXml);
            } else {
                $data[$name] = $this->serializeForm($child, $isXml);
            }
        }

        return $data;
    }
}

