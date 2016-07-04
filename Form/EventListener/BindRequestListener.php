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

namespace SimpleThings\FormSerializerBundle\Form\EventListener;

use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\Encoder\DecoderInterface;

use SimpleThings\FormSerializerBundle\Serializer\EncoderRegistry;
use SimpleThings\FormSerializerBundle\Serializer\SerializerOptions;
use Symfony\Component\Serializer\Exception\UnexpectedValueException;

class BindRequestListener implements EventSubscriberInterface
{
    /**
     * @var DecoderInterface
     */
    private $decoder;

    /**
     * @var SerializerOptions
     */
    private $options;

    /**
     * BindRequestListener constructor.
     *
     * @param DecoderInterface       $decoder
     * @param SerializerOptions|null $options
     */
    public function __construct(DecoderInterface $decoder, SerializerOptions $options = null)
    {
        $this->decoder = $decoder;
        $this->options = $options ?: new SerializerOptions();
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        // High priority in order to supersede other listeners
        return array(FormEvents::PRE_SUBMIT => array('preBind', 129));
    }

    /**
     * @param FormEvent $event
     *
     * @throws UnexpectedValueException
     */
    public function preBind(FormEvent $event)
    {
        $form    = $event->getForm();
        $request = $event->getData();

        if ( ! $request instanceof Request) {
            return;
        }

        $format = $request->getContentType();

        if ( ! $this->decoder->supportsDecoding($format)) {
            return;
        }

        $content = $request->getContent();
        $options = $form->getConfig()->getOptions();
        $xmlName = !empty($options['serialize_xml_name']) ? $options['serialize_xml_name'] : 'entry';
        $data    = $this->decoder->decode($content, $format);

        if ( ($format === 'json' && $this->options->getIncludeRootInJson()) ||
             ($format === 'xml' && $this->options->getApplicationXmlRootName() && $this->options->getApplicationXmlRootName() !== $xmlName)) {
            $data = isset($data[$xmlName]) ? $data[$xmlName] : array();
        }

        $event->setData($this->unserializeForm($data, $form, $format === 'xml', $request->getMethod() === 'PATCH'));
    }

    /**
     * @param array         $data
     * @param FormInterface $form
     * @param boolean       $isXml
     * @param boolean       $isPatch
     *
     * @return array
     */
    private function unserializeForm($data, $form, $isXml, $isPatch)
    {
        if ($form->getConfig()->hasAttribute('serialize_collection_form')) {
            $form = $form->getConfig()->getAttribute('serialize_collection_form');
            $result = [];

            if (!isset($data[0])) {
                $data = [$data]; // XML special case
            }

            foreach ($data as $key => $child) {
                $result[$key] = $this->unserializeForm($child, $form, $isXml, $isPatch);
            }

            return $result;
        }
        if (!$form->all()) {
            return $data;
        }

        $result = [];
        $namingStrategy = $this->options->getNamingStrategy();

        foreach ($form->all() as $child) {
            $options     = $child->getConfig()->getOptions();

            if (isset($options['disabled']) && $options['disabled']) {
                continue;
            }

            $name        = $options['serialize_name'] ?: $namingStrategy->translateName($child);
            $isAttribute = isset($options['serialize_xml_attribute']) && $options['serialize_xml_attribute'];

            if ($options['serialize_xml_value'] && isset($data['#'])) {
                $value = $data['#'];
            } else if (! $options['serialize_xml_inline'] && $isXml) {
                $value = isset($data[$name][$options['serialize_xml_name']])
                    ? $data[$name][$options['serialize_xml_name']]
                    : null;
            } else {
                $value = isset($data['@' . $name])
                    ? $data['@' . $name]
                    : (isset($data[$name]) ? $data[$name] : null);
            }

            // If we are PATCHing then don't fill in missing attributes with null
            $childValue = $this->unserializeForm($value, $child, $isXml, $isPatch);
            if (!($isPatch && !$childValue)) {
                $result[$child->getName()] = $childValue;
            }
        }

        return $result;
    }
}

