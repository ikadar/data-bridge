<?php

namespace App\Adapter\Excel;

use App\Adapter\Base\BaseAdapter;
use App\Entity\IntermediateFormat;
use App\Exception\WorksheetNotExistsException;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Worksheet\RowIterator;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use Symfony\Component\Yaml\Yaml;

/**
 * The ExcelAdapter class is responsible for reading and transforming
 * data from Excel files into a standardized IntermediateFormat.
 * It extends the BaseAdapter class and implements the DataSourceAdapter interface.
 */
abstract class ExcelAdapter extends BaseAdapter
{
    /**
     * @var string The path to the Excel file to be processed.
     */
    protected string $filePath;
    protected Xlsx $reader;
    protected Spreadsheet $spreadsheet;
    protected Worksheet $worksheet;
    protected string $sheetName;
    protected RowIterator $rowIterator;
    protected int $columnOffset;
    protected int $headerRowIndex;
    protected int $dataRowOffset;
    protected array $columnNames;
    protected array $transformation;
    protected array $intermediateDataUnit;
    protected string $configurationFilePath;
    protected array $configuration;

    /**
     * ExcelAdapter constructor.
     *
     * @param string $filePath The path to the Excel file to be processed.
     */
    public function __construct(string $filePath, $configurationFilePath)
    {
        $this->configurationFilePath = $configurationFilePath;
        $this->configuration = Yaml::parseFile($this->configurationFilePath);

        parent::__construct();

        $this->filePath = $filePath;

        // Create a new Xlsx reader instance from PhpSpreadsheet
        $this->reader = new Xlsx();

        // Load the spreadsheet from the provided file path
        $this->spreadsheet = $this->reader->load($this->filePath);
    }

    protected function getStructure(): array
    {
        return $this->configuration["entities"];
    }

    protected function getMappings(): array
    {
        return $this->configuration["mappings"];
    }

    /**
     * Fetches data from the Excel file and converts it into an IntermediateFormat.
     *
     * @return IntermediateFormat The standardized format containing the processed data.
     */
    public function fetch(): IntermediateFormat
    {
        foreach ($this->getMappings() as $sheetName => $mapping) {
            $this->initializeSheet($sheetName);

            foreach ($mapping["iterations"] as $iteration => $transformations) {

                $this->rowIterator->rewind();
                // Extract data from the loaded spreadsheet
                while ($dataUnit = $this->readRow()) {

                    $this->setCurrentDataUnit($dataUnit);

                    $this->intermediateDataUnit = [];

                    foreach ($transformations as $this->transformation) {
                        $this->transform();
                    }

                    foreach ($transformations as $this->transformation) {
                        $this->mergeReturnValues();
                    }

                    $this->intermediateData->addDataUnit($this->intermediateDataUnit);
                    $this->rowIterator->next();
                }
            }
        }

//        dump("DATA UNIT", $this->dataUnit);
//        dump("INTERMEDIATE", $this->intermediateData->getData());

        return $this->intermediateData;
    }

    /**
     * Extracts data from the sheets of the Excel file based on the mappings provided.
     *
     * @return array An associative array of extracted data indexed by sheet names.
     */
    protected function readRow(): ?array
    {
        $data = [];

        $row = $this->rowIterator->current();

        if ($row->isEmpty()) {
            return null;
        }

        foreach ($this->columnNames as $columnCoordinate => $columnName) {
            $coordinate = sprintf("%s%d", $columnCoordinate, $row->getRowIndex());
            $cell = $this->worksheet->getCell($coordinate);
            $value = $cell->getValue();
            $data[$columnName] = $value;
        }

        return $data;
    }

    protected function getColumnNames(): array
    {
        $columnIndex = 1;
        $columnNames = [];

        while (true) {
            $coordinate = $this->getCoordinate($columnIndex, $this->headerRowIndex);
            $cell = $this->worksheet->getCell($coordinate);
            $value = $cell->getValue();
            if ($value === null) {
                break;
            }
            $columnNames[Coordinate::stringFromColumnIndex($columnIndex)] = $cell->getValueString();
            $columnIndex++;
        }

        return $columnNames;
    }

    protected function initializeSheet($sheetName)
    {
        $this->sheetName = $sheetName;
        $sheetConfiguration = $this->getMappings()[$sheetName]["configuration"];

        $this->columnOffset = $sheetConfiguration["columnOffset"];
        $this->headerRowIndex = $sheetConfiguration["headerRowIndex"];
        $this->dataRowOffset = $sheetConfiguration["dataRowOffset"];

        try {
            $this->worksheet = $this->spreadsheet->getSheetByName($this->sheetName);
        } catch (\TypeError $e) {
            throw new WorksheetNotExistsException($this->sheetName);
        };
        $this->rowIterator = $this->worksheet->getRowIterator($this->headerRowIndex + $this->dataRowOffset);

        $this->columnNames = $this->getColumnNames();
        $this->dataUnit[$sheetName] = [];

    }

    public function getCoordinate($columnIndex, $rowIndex): string
    {
        return sprintf(
            "%s%d",
            Coordinate::stringFromColumnIndex($columnIndex + $this->columnOffset),
            $rowIndex
        );
    }

    public function calculateKey($entityType, $data)
    {
        $keyArray = [];
        foreach ($this->intermediateData->getEntityKeyAttributes($entityType) as $entityKeyAttribute) {
            $keyArray[$entityKeyAttribute] = $data[$entityKeyAttribute];
        }
        return serialize($keyArray);
    }

    public function setValue($value)
    {
        return $value;
    }

    public function setReference($value)
    {
        $keyAttributes = $this->intermediateData->getEntityKeyAttributes($this->transformation["entityType"]);
        return $this->calculateKey($this->transformation["entityType"], [$keyAttributes[0] => $value]);
    }

    protected function transform()
    {
        $parameters = [];
        foreach ($this->transformation["parameters"] as $parameterName => $parameterValue) {
            $parameters[$parameterName] = $this->getCurrentDataUnit()[$parameterValue];
        }

        $returnedValues = call_user_func_array([$this, $this->transformation["method"]], $parameters);
        if (!is_array($returnedValues)) {
            $returnedValues = [$returnedValues];
        }

        foreach ($this->transformation["returns"] as $return) {
            foreach ($return["attribute"] as $attributeIndex => $attributeName) {
                $this->setCurrentDataUnitValue($attributeName, array_values($returnedValues)[$attributeIndex]);
            }
        }
    }

    protected function mergeReturnValues()
    {
        foreach ($this->transformation["returns"] as $return) {

            // search the entity by key to add or create
            $key = $this->calculateKey($return["entity"], $this->getCurrentDataUnit());

            foreach ($return["attribute"] as $attribute) {
                $value = ($this->cast(
                    $this->getCurrentDataUnit()[$attribute],
                    $this->getAttributeConfiguration($return["entity"], $attribute)["type"]
                ));
                $this->intermediateDataUnit[$return["entity"]][$key][$attribute] = $value;
            }
        }
    }

    protected function cast($value, $type)
    {
        return match ($type) {
            "string" => (string)$value,
            "integer" => (int)$value,
            "float" => (float)$value,
            "reference" => $value,
            default => $value,
        };
    }

    protected function getAttributeConfiguration($entityType, $attributeName)
    {
        $attributes = $this->intermediateData->getEntityConfiguration($entityType)["attributes"];
        $attribute = array_filter($attributes, function ($item) use ($attributeName) {
            return $item["name"] == $attributeName;
        });
        return reset($attribute);
    }

    protected function getCurrentDataUnitKey()
    {
        return serialize([
            "sheet" => $this->sheetName,
            "row" => $this->rowIterator->key()
        ]);
    }

    protected function getCurrentDataUnit()
    {
        return $this->dataUnit[$this->getCurrentDataUnitKey()];
    }

    protected function setCurrentDataUnit($data)
    {
        if ($this->getCurrentDataUnit() === null) {
            $this->dataUnit[$this->getCurrentDataUnitKey()] = [];
        }

        $this->dataUnit[$this->getCurrentDataUnitKey()] =
            array_merge($this->getCurrentDataUnit(), $data);
    }

    protected function setCurrentDataUnitValue($attributeName, $value)
    {
        $this->dataUnit[$this->getCurrentDataUnitKey()][$attributeName] = $value;
    }
}
