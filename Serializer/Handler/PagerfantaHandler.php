<?php

namespace FSC\HateoasBundle\Serializer\Handler;

use JMS\SerializerBundle\Serializer\Handler\SubscribingHandlerInterface;
use JMS\SerializerBundle\Serializer\EventDispatcher\Event;
use JMS\SerializerBundle\Serializer\GraphNavigator;
use JMS\SerializerBundle\Serializer\XmlSerializationVisitor;
use JMS\SerializerBundle\Serializer\GenericSerializationVisitor;
use Metadata\MetadataFactoryInterface;
use Pagerfanta\Pagerfanta;

use FSC\HateoasBundle\Serializer\EventSubscriber\EmbedderEventSubscriber;
use FSC\HateoasBundle\Serializer\EventSubscriber\LinkEventSubscriber;

class PagerfantaHandler implements SubscribingHandlerInterface
{
    public static function getSubscribingMethods()
    {
        $methods = array();
        foreach (array('json', 'xml', 'yml') as $format) {
            $methods[] = array(
                'direction' => GraphNavigator::DIRECTION_SERIALIZATION,
                'format' => $format,
                'type' => 'Pagerfanta\Pagerfanta',
                'method' => 'serializeTo'.('xml' == $format ? 'XML' : 'Array'),
            );
        }

        return $methods;
    }

    protected $serializerMetadataFactory;
    protected $embedderEventSubscriber;
    protected $linkEventSubscriber;
    protected $xmlElementsNamesUseSerializerMetadata;

    public function __construct(MetadataFactoryInterface $serializerMetadataFactory,
        EmbedderEventSubscriber $embedderEventSubscriber, LinkEventSubscriber $linkEventSubscriber,
        $xmlElementsNamesUseSerializerMetadata = true)
    {
        $this->serializerMetadataFactory = $serializerMetadataFactory;
        $this->embedderEventSubscriber = $embedderEventSubscriber;
        $this->linkEventSubscriber = $linkEventSubscriber;
        $this->xmlElementsNamesUseSerializerMetadata = $xmlElementsNamesUseSerializerMetadata;
    }

    public function serializeToXML(XmlSerializationVisitor $visitor, Pagerfanta $pager, array $type)
    {
        if (null === $visitor->document) {
            $visitor->document = $visitor->createDocument(null, null, true);
        }

        $this->embedderEventSubscriber->onPostSerializeXML(new Event($visitor, $pager, $type));
        $this->linkEventSubscriber->onPostSerializeXML(new Event($visitor, $pager, $type));

        $currentNode = $visitor->getCurrentNode(); /** @var $currentNode \DOMElement */
        $currentNode->setAttribute('page', $pager->getCurrentPage());
        $currentNode->setAttribute('limit', $pager->getMaxPerPage());
        $currentNode->setAttribute('total', $pager->getNbResults());

        $resultsType = isset($type['params'][0]) ? $type['params'][0] : null;

        if (!$this->xmlElementsNamesUseSerializerMetadata) {
            return $visitor->getNavigator()->accept($pager->getCurrentPageResults(), $resultsType, $visitor);
        }

        foreach ($pager->getCurrentPageResults() as $result) {
            $elementName = 'entry';
            if (is_object($result) && null !== ($resultMetadata = $this->serializerMetadataFactory->getMetadataForClass(get_class($result)))) {
                $elementName = $resultMetadata->xmlRootName ?: $elementName;
            }

            $entryNode = $visitor->getDocument()->createElement($elementName);
            $visitor->getCurrentNode()->appendChild($entryNode);
            $visitor->setCurrentNode($entryNode);

            if (null !== $node = $visitor->getNavigator()->accept($result, $resultsType, $visitor)) {
                $visitor->getCurrentNode()->appendChild($node);
            }

            $visitor->revertCurrentNode();
        }
    }

    public function serializeToArray(GenericSerializationVisitor $visitor, Pagerfanta $pager, array $type)
    {
        $resultsType = isset($type['params'][0]) ? $type['params'][0] : null;

        $data = array(
            'page' => $pager->getCurrentPage(),
            'limit' => $pager->getMaxPerPage(),
            'total' => $pager->getNbResults(),
            'results' => $visitor->getNavigator()->accept($pager->getCurrentPageResults(), $resultsType, $visitor),
        );

        if (null !== ($links = $this->linkEventSubscriber->getOnPostSerializeData(new Event($visitor, $pager, $type)))) {
            $data['links'] = $links;
        }

        if (null !== ($relations = $this->embedderEventSubscriber->getOnPostSerializeData(new Event($visitor, $pager, $type)))) {
            $data['relations'] = $relations;
        }

        return $data;
    }
}
