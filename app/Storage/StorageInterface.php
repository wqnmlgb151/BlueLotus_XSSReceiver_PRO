<?php
namespace App\Storage;

interface StorageInterface
{
    public function save(array $record, string $id): bool;
    public function create(array $record, string $id): bool;
    public function load(string $id): ?array;
    public function delete(string $id): bool;
    public function clearAll(): bool;
    public function listIds(): array;
    public function listAll(): array;
}
