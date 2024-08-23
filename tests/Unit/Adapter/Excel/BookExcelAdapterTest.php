<?php

namespace App\Tests\Unit\Adapter\Excel;

use App\Entity\IntermediateFormat;
use App\Tests\TestAdapter\Excel\BookExcelAdapter;
use PHPUnit\Framework\TestCase;

class BookExcelAdapterTest extends TestCase {
    public function testFetch() {
        $adapter = new BookExcelAdapter("./tests/Fixtures/Excel/books.xlsx");
        $result = $adapter->fetch();

        dump($result);

        $this->assertInstanceOf(IntermediateFormat::class, $result);
        // További asserciók az eredmény ellenőrzésére
    }
}
