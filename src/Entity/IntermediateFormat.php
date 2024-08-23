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
}
