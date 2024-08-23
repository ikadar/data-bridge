<?php

namespace App\Adapter\Excel;

use App\Adapter\Base\BaseAdapter;
use App\Entity\IntermediateFormat;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;

abstract class ExcelAdapter extends BaseAdapter
{
    protected string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    public function fetch(): IntermediateFormat
    {
        $reader = new Xlsx();
        $spreadsheet = $reader->load($this->filePath);
        $data = $this->extractDataFromSheets($spreadsheet);

        return $this->transformToIntermediateFormat($data);
    }

    protected function extractDataFromSheets($spreadsheet): array
    {
        $data = [];
        foreach ($this->getMappings() as $mapping) {
            foreach ($spreadsheet->getSheetNames() as $sheetName) {
                if ($sheetName === $mapping['inputs'][0]['sheet']) {
                    $worksheet = $spreadsheet->getSheetByName($sheetName);
                    $sheetData = [];

                    foreach ($worksheet->getRowIterator() as $rowIndex => $row) {
                        $cellIterator = $row->getCellIterator();
                        $cellIterator->setIterateOnlyExistingCells(false);
                        $rowData = [];

                        foreach ($cellIterator as $cell) {
                            $rowData[] = $cell->getValue();
                        }

                        if ($rowIndex > 1) {
                            $sheetData[] = $rowData;
                        }
                    }

                    $data[$sheetName] = $sheetData;
                }
            }
        }
        return $data;
    }

    protected function transformToIntermediateFormat(array $data): IntermediateFormat
    {
        $processedData = [];
        $references = [];

        foreach ($this->getMappings() as $mapping) {
            foreach ($data as $sheetName => $rows) {
                foreach ($rows as $row) {
                    $sourceValues = [];
                    foreach ($mapping['inputs'] as $input) {
                        if ($input['sheet'] === $sheetName) {
                            $sourceValues[] = $row[array_search($input['column'], array_keys($row))];
                        }
                    }

                    foreach ($mapping['outputs'] as $output) {
                        if ($output['type'] === 'value') {
                            $processedData[] = [
                                "entity" => $output['entity'],
                                $output['attribute'] => $sourceValues[0]
                            ];
                        } elseif ($output['type'] === 'reference') {
                            $refKey = $output['entity'] . ':' . $sourceValues[0];
                            if (!isset($references[$refKey])) {
                                $refId = $this->generateUuid();
                                $references[$refKey] = $refId;
                                $processedData[] = [
                                    "entity" => $output['entity'],
                                    "id" => $refId,
                                    $output['attribute'] => $sourceValues[0]
                                ];
                            }
                            $processedData[] = [
                                "entity" => $mapping['outputs'][0]['entity'],
                                $output['attribute'] => $references[$refKey]
                            ];
                        } elseif ($output['type'] === 'closure') {
                            $method = $output['method'];
                            if (is_callable([$this, $method])) {
                                $result = $this->$method($sourceValues[0]);
                                foreach ($result as $attr => $value) {
                                    $processedData[] = [
                                        "entity" => $output['entity'],
                                        $attr => $value
                                    ];
                                }
                            } else {
                                throw new \Exception("Method $method is not callable.");
                            }
                        }
                    }
                }
            }
        }

        return new IntermediateFormat($processedData, $this->getStructure(), $this->getMappings());
    }
}
