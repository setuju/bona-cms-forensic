<?php
declare(strict_types=1);
namespace SatuRumah\Forensic\Checkers;

use SatuRumah\Forensic\Core\Config;

abstract class AbstractChecker implements CheckerInterface {
    protected Config $config;
    protected array $results = [];

    public function __construct(Config $config) {
        $this->config = $config;
    }

    protected function addResult(string $status, string $message, array $details = []): void {
        $this->results[] = ['status' => $status, 'message' => $message, 'details' => $details];
    }
}