<?php

namespace App\Tests\Unit\Adapter\Excel;

use App\Adapter\Neo4j\Neo4jAdapter;
use App\Entity\IntermediateFormat;
use App\Tests\TestAdapter\Excel\RedirectionExcelAdapter;
use PHPUnit\Framework\TestCase;

class RedirectionExcelAdapterTest extends TestCase {
    public function testFetch() {
        $adapter = new RedirectionExcelAdapter(
            "./tests/Fixtures/Excel/redirections.xlsx",
        );
        $intermediateStore = $adapter->fetch();
//        dump($intermediateStore->getData());

        $neo4jAdapter = new Neo4jAdapter($intermediateStore);
        $neo4jAdapter->persist();
    }
}
