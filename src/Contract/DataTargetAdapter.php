<?php

namespace App\Contract;

interface DataTargetAdapter
{
    public function store(iterable $data): void;
}
