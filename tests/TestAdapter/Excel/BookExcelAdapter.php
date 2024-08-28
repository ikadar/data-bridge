<?php

namespace App\Tests\TestAdapter\Excel;

use App\Adapter\Excel\ExcelAdapter;

class BookExcelAdapter extends ExcelAdapter
{
    public function __construct(string $filePath)
    {
        $this->configurationFilePath = "./tests/Fixtures/Excel/configuration.yaml";
        parent::__construct($filePath, $this->configurationFilePath);
    }

    protected function splitName(string $name): array
    {
        $parts = explode(',', $name);
        return [
            'last_name' => trim($parts[0]),
            'first_name' => trim($parts[1] ?? '')
        ];
    }
}
