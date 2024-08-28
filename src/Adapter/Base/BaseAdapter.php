<?php

namespace App\Adapter\Base;

use App\Contract\DataSourceAdapter;
use App\Entity\IntermediateFormat;

/**
 * The BaseAdapter class provides common functionality and structure
 * for all specific data source adapters. It implements the
 * DataSourceAdapter interface and serves as a base class
 * for more specific adapters like ExcelAdapter or MySQLAdapter.
 */
abstract class BaseAdapter implements DataSourceAdapter
{
    protected IntermediateFormat $intermediateData;
    protected array $dataUnit;

    /**
     * BaseAdapter constructor.
     *
     */
    public function __construct()
    {
        $this->dataUnit = [];
        $this->intermediateData = new IntermediateFormat([], $this->getStructure(), $this->getMappings());
    }

    /**
     * Generates a universally unique identifier (UUID).
     *
     * This method generates a random UUID using a combination of
     * random numbers and fixed values to conform to the UUID format.
     *
     * @return string A UUID in the format xxxxxxxx-xxxx-xxxx-xxxx-xxxxxxxxxxxx.
     */
    protected function generateUuid(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),    // Generates the first section
            mt_rand(0, 0xffff),                       // Generates the second section
            mt_rand(0, 0x0fff) | 0x4000,              // Generates the third section, with version 4 UUID format
            mt_rand(0, 0x3fff) | 0x8000,              // Generates the fourth section, with variant 1
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff) // Generates the final section
        );
    }

    /**
     * Gets the mappings required for data transformation.
     *
     * This method should be implemented by subclasses to provide
     * the specific mappings needed to transform data from the
     * source format to the intermediate format.
     *
     * @return array An array defining the mappings for data transformation.
     */
    protected abstract function getMappings(): array;

    /**
     * Gets the structure of the data entities being processed.
     *
     * Subclasses must implement this method to define the structure
     * of the data entities, such as their attributes and relationships,
     * to be used during the data transformation process.
     *
     * @return array An array defining the structure of the data entities.
     */
    protected abstract function getStructure(): array;
}
