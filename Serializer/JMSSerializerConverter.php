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

use Metadata\MetadataFactoryInterface;

/**
 * Converts JMSSerializer Metadata for classes into PHP code for forms.
 */
class JMSSerializerConverter
{
    /**
     * @var MetadataFactoryInterface
     */
    private $metadataFactory;

    /**
     * @var array
     */
    private $typeMap = [
        'string' => 'text',
        'boolean' => 'checkbox',
        'integer' => 'integer',
        'double' => 'number',
        'DateTime' => 'datetime',
        'datetime' => 'datetime',
    ];

    static private $template = <<<'PHP'
<?php

namespace {{namespace}};

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class {{class}}Type extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            {{build}}
        ;
    }

    public function configureOptions(OptionsResolver $resolver)
    {
        $resolver->setDefaults(array(
            {{defaults}}
        ));
    }

    public function getName()
    {
        return '{{name}}';
    }
}

PHP;

    /**
     * JMSSerializerConverter constructor.
     *
     * @param MetadataFactoryInterface $factory
     */
    public function __construct(MetadataFactoryInterface $factory)
    {
        $this->metadataFactory = $factory;
    }

    /**
     * @param string $type
     * @param bool   $recursive
     *
     * @return string
     */
    private function getType($type, $recursive = false)
    {
        if (isset($this->typeMap[$type])) {
            return $this->typeMap[$type];
        }

        if (strpos($type, 'array<') === 0) {
            if (!$recursive) {
                return 'collection';
            }

            if (false === $pos = strpos($type, ',', 6)) {
                $listType = substr($type, 6, -1);
            } else {
                $keyType = trim(substr($type, 6, $pos - 6));
                $listType = trim(substr($type, $pos + 1, -1));
            }

            return $this->getType($listType);
        }
        if (class_exists($type)) {
            $parts = explode("\\", $type);

            return 'new '.end($parts).'Type()';
        }

        return 'text';
    }

    /**
     * @param string $className
     *
     * @return mixed|string
     */
    public function generateFormPhpCode($className)
    {
        $metadata = $this->metadataFactory->getMetadataForClass($className);
        $lines = [];

        $defaults = [
            "'data_class' => '".$metadata->name."'",
        ];
        if ($metadata->xmlRootName) {
            $efaults[] = "'serialize_xml_name' => '".$metadata->xmlRootName."'";
        }

        $builder = [];
        foreach ($metadata->propertyMetadata as $property) {
            $options = [];
            $type = $this->getType($property->type);

            if ($property->xmlCollection || $type === 'collection') {
                $options[] = "'entry_type' => ".$this->getType($property->type, true);
                if (!$property->xmlCollectionInline) {
                    $options[] = "'serialize_xml_inline' => false";
                }

                if ($property->xmlEntryName) {
                    $options[] = "'serialize_xml_name' => '".$property->xmlEntryName."'";
                }
            }

            if ($property->xmlAttribute) {
                $options[] = "'serialize_xml_attribute' => true";
            }

            if ($property->xmlValue) {
                $options[] = "'serialize_xml_value' => true";
            }

            if ($property->readOnly) {
                $options[] = "'disabled' => true";
            }

            $options = $options ? ', array('.implode(", ", $options).')' : '';

            $type = (strpos($type, ' ') === false) ? "'".$type."'" : $type;

            $builder[] = "->add('".$property->name."', ".$type.$options.')';
        }

        // TODO: Replace
        $variables = [
            'name' => strtolower($metadata->reflection->getShortName()),
            'class' => $metadata->reflection->getShortName(),
            'namespace' => str_replace(['Entity', 'Document'], 'Form', $metadata->reflection->getNamespaceName()),
            'build' => implode("\n            ", $builder),
            'defaults' => implode("\n", $defaults),
        ];

        $code = self::$template;
        foreach ($variables as $placeholder => $variable) {
            $code = str_replace('{{'.$placeholder.'}}', $variable, $code);
        }

        return $code;
    }
}

