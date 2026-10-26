<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

interface CheckerInterface {
    public function getName(): string;
    public function run(): array; // Harus mengembalikan array result
}