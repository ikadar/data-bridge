<?php

namespace App\Tests\TestAdapter\Excel;

use App\Adapter\Excel\ExcelAdapter;

class RedirectionExcelAdapter extends ExcelAdapter
{
    public function __construct(string $filePath)
    {
        $this->configurationFilePath = "./tests/Fixtures/Excel/redirection.yaml";
        parent::__construct($filePath, $this->configurationFilePath);
    }

//    protected function getClientAndRedirectionId(?string $client, ?string $redirId): array
//    {
//        return [
//            'client' => serialize(["name" => $client]),
//            'client_name' => $client,
//            'redirectionid' => $redirId
//        ];
//    }
}
