<?php

namespace App\Repositories\Base;

interface BaseRepositoryInterface
{
    public function items(array $search, array $relations);
    public function show(string $id, $with = []);
    public function update(array $params, string $id);
    public function create(array $params);
    public function updateOrCreate(array $references, array $params);
    public function firstOrCreate(array $references, array $params);
    public function secureDelete(string $id, array $relations);
}