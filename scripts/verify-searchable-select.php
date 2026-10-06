<?php

require __DIR__ . '/../vendor/autoload.php';

$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$admin = \App\Models\User::where('email', 'admin@example.test')->first();
if (!$admin) {
    echo "Admin demo not found.\n";
    exit(1);
}

auth()->login($admin);

$checks = [
    '/people' => [
        'id="add-person-branch"',
        'id="add-person-position"',
        'id="filter-branch"',
        'id="filter-position"',
        'data-searchable-select',
    ],
    '/shifts' => [
        'id="shift-user"',
        'id="shift-branch"',
        'id="filter-shift-branch"',
        'data-searchable-select',
    ],
    '/payroll' => [
        'id="payroll-branch-select"',
        'id="adjustment-user"',
        'data-searchable-select',
    ],
    '/leave' => [
        'id="leave-user"',
        'data-searchable-select',
    ],
    '/attendance-review' => [
        'id="filter-attendance-branch"',
        'data-searchable-select',
    ],
];

echo "========================================================\n";
echo "VERIFIKASI INTEGRASI SEARCHABLE SELECT DI SELURUH MODUL\n";
echo "========================================================\n\n";

$allPassed = true;

foreach ($checks as $uri => $expectedStrings) {
    $request = \Illuminate\Http\Request::create($uri, 'GET');
    $response = $app->handle($request);
    $html = $response->getContent();

    echo "Testing: [{$uri}] ... ";
    $missing = [];
    foreach ($expectedStrings as $str) {
        if (!str_contains($html, $str)) {
            $missing[] = $str;
        }
    }

    if (empty($missing)) {
        echo "PASSED (Semua searchable select terpasang)\n";
    } else {
        echo "FAILED (Hilang: " . implode(', ', $missing) . ")\n";
        $allPassed = false;
    }
}

echo "\n========================================================\n";
if ($allPassed) {
    echo "🎉 SELURUH SEARCHABLE SELECT TERPASANG SEMPURNA (100% OK)!\n";
    exit(0);
} else {
    echo "❌ ADA SEARCHABLE SELECT YANG BELUM TERDETEKSI!\n";
    exit(1);
}
