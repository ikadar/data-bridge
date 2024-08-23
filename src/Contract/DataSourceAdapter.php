<?php

namespace App\Contract;

use App\Entity\IntermediateFormat;

interface DataSourceAdapter
{
    public function fetch(): IntermediateFormat;
}
