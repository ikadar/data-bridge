<?php

namespace App\Entity;

class IntermediateFormat {
    private array $data = [];
    private array $structure = [];
    private array $mappings = [];

    public function __construct(array $data = [], array $structure = [], array $mappings = []) {
        $this->data = $data;
        $this->structure = $structure;
        $this->mappings = $mappings;
    }

    public function getData(): array {
        return $this->data;
    }

    public function getStructure(): array {
        return $this->structure;
    }

    public function getMappings(): array {
        return $this->mappings;
    }

    public function addDataUnit($dataUnit) {
        foreach ($dataUnit as $entityType => $entities) {
            foreach ($entities as $key => $entity) {
                foreach ($entity as $attributeName => $value) {
                    $this->data[$entityType][$key][$attributeName] = $value;
                }
            }
        }
    }

    public function getEntityConfiguration($type) {
//        $entityConfigurations = array_filter($this->structure["entities"], function($item) use ($type) {
        $entityConfigurations = array_filter($this->structure, function($item) use ($type) {
            return $item["type"] == $type;
        });
        return reset($entityConfigurations);
    }

    public function getEntityKeyAttributes($type) {

        $entityConfiguration = $this->getEntityConfiguration($type);
        return $entityConfiguration["key"];
    }

    public function setAttribute($entityType, $key, $attributeName, $value)
    {
        $this->data[$entityType][$key][$attributeName] = $value;
    }

    public function getEntity($entityType, $serializedKey)
    {
        return $this->getData()[$entityType][$serializedKey];
    }
}
