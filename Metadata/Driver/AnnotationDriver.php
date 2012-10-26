<?php

namespace FSC\HateoasBundle\Metadata\Driver;

use Metadata\Driver\DriverInterface;
use Doctrine\Common\Annotations\Reader;

use FSC\HateoasBundle\Annotation;
use FSC\HateoasBundle\Metadata\ClassMetadata;
use FSC\HateoasBundle\Metadata\RelationMetadata;
use FSC\HateoasBundle\Metadata\RelationContentMetadata;

class AnnotationDriver implements DriverInterface
{
    private $reader;

    public function __construct(Reader $reader)
    {
        $this->reader = $reader;
    }

    /**
     * {@inheritdoc}
     */
    public function loadMetadataForClass(\ReflectionClass $class)
    {
        $annotations = $this->reader->getClassAnnotations($class);

        if (0 == count($annotations)) {
            return null;
        }

        $classMetadata = new ClassMetadata($name = $class->getName());
        $classMetadata->fileResources[] = $class->getFilename();

        foreach ($annotations as $annotation) {
            if ($annotation instanceof Annotation\Relation) {
                $relationMetadata = new RelationMetadata($annotation->rel, $annotation->href->value);
                if (!empty($annotation->href->parameters)) {
                    $relationMetadata->setParams($annotation->href->parameters);
                }

                if (null !== $annotation->embed && $annotation->embed instanceof Annotation\Content) {
                    if (2 !== count($annotation->embed->provider)) {
                        throw new \RuntimeException('The @Content provider paremeters should be an array of 2 values, a service id and a method.');
                    }

                    $relationContentMetadata = new RelationContentMetadata($annotation->embed->provider[0], $annotation->embed->provider[1]);
                    $relationMetadata->setContent($relationContentMetadata);

                    $relationContentMetadata->setProviderArguments($annotation->embed->providerArguments ?: array());
                    $relationContentMetadata->setSerializerType($annotation->embed->serializerType);
                    $relationContentMetadata->setSerializerXmlElementName($annotation->embed->serializerXmlElementName);
                    $relationContentMetadata->setSerializerXmlElementRootName($annotation->embed->serializerXmlElementNameRootMetadata);
                }

                $classMetadata->addRelation($relationMetadata);
            }
        }

        return $classMetadata;
    }
}
