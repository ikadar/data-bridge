<?php

namespace App\Tests\TestAdapter\Excel;

use App\Adapter\Excel\ExcelAdapter;

class BookExcelAdapter extends ExcelAdapter
{
    protected function getStructure(): array
    {
        return [
            "entities" => [
                [
                    "name" => "author",
                    "attributes" => [
                        [
                            "name" => "first_name",
                            "type" => "string"
                        ],
                        [
                            "name" => "last_name",
                            "type" => "string"
                        ]
                    ]
                ],
                [
                    "name" => "book",
                    "attributes" => [
                        [
                            "name" => "title",
                            "type" => "string"
                        ],
                        [
                            "name" => "ID_author",
                            "type" => "reference",
                            "refersTo" => "author",
                            "refersBy" => "id"
                        ]
                    ]
                ]
            ]
        ];
    }

    protected function getMappings(): array
    {
        return [
            [
                "inputs" => [
                    [
                        "sheet" => "Books",
                        "column" => "title"
                    ]
                ],
                "outputs" => [
                    [
                        "entity" => "book",
                        "attribute" => "title",
                        "type" => "value"
                    ]
                ]
            ],
            [
                "inputs" => [
                    [
                        "sheet" => "Books",
                        "column" => "author"
                    ]
                ],
                "outputs" => [
                    [
                        "entity" => "author",
                        "attribute" => ["last_name", "first_name"],
                        "type" => "customLogic",
                        "method" => "splitName"
                    ],
                    [
                        "entity" => "book",
                        "attribute" => "ID_author",
                        "type" => "reference"
                    ]
                ]
            ]
        ];
    }

    protected function splitName(string $fullName): array
    {
        $parts = explode(',', $fullName);
        return [
            'last_name' => trim($parts[0]),
            'first_name' => trim($parts[1] ?? '')
        ];
    }
}
