<?php

namespace App\Domains\Qdvs\Engine;

use App\Domains\Qdvs\Engine\Data\RawRule;
use RuntimeException;

class QdvsRuleRepository
{
    /** @var array<int, RawRule>|null */
    private ?array $rules = null;

    public function __construct(private readonly string $path) {}

    /** @return array<int, RawRule> */
    public function all(): array
    {
        return $this->rules ??= $this->load();
    }

    /** @return array<int, RawRule> */
    private function load(): array
    {
        $handle = @fopen($this->path, 'r');
        if ($handle === false) {
            throw new RuntimeException("QDVS-Regeldatei nicht lesbar: {$this->path}");
        }

        try {
            $rules = [];
            $isHeader = true;
            while (($row = fgetcsv($handle, 0, ';', '"', '\\')) !== false) {
                if ($isHeader) {
                    $isHeader = false;

                    continue;
                }
                if ($row === [null] || count($row) < 5) {
                    continue;
                }

                $rules[] = new RawRule(
                    dataset: (string) $row[0],
                    ruleId: (string) $row[1],
                    assertTest: (string) $row[2],
                    ruleText: (string) $row[3],
                    ruleType: trim((string) $row[4]),
                );
            }

            return $rules;
        } finally {
            fclose($handle);
        }
    }
}
