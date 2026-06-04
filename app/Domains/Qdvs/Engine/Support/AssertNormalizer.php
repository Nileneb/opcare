<?php

namespace App\Domains\Qdvs\Engine\Support;

class AssertNormalizer
{
    /**
     * Bringt einen rohen DAS-assert_test in eine kanonische Form, gegen die die
     * Pattern-Matcher zuverlässig matchen können.
     *
     * WHY(DAS_REGELN): Die CSV transportiert < und > als XML-Entities (&lt;/&gt;),
     * sonst würde kein Matcher die Vergleichsoperatoren finden.
     */
    public function normalize(string $assertTest): string
    {
        $s = html_entity_decode($assertTest, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $s = preg_replace('/\s+/', ' ', $s);

        return trim($s);
    }
}
