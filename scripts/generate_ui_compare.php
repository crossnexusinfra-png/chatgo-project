<?php

$ja = include __DIR__ . '/../resources/lang/ja.php';
$en = include __DIR__ . '/../resources/lang/en.php';

$replaceMap = [
    'Login/Register' => 'Login/Sign up',
    'Phone' => 'Phone number',
    'Residence' => 'Location',
    'Language Setting' => 'Language settings',
];

$outPath = __DIR__ . '/../docs/ui-strings-ja-en-full-excluded.csv';
$fp = fopen($outPath, 'w');
if ($fp === false) {
    fwrite(STDERR, "Failed to open output file.\n");
    exit(1);
}

fwrite($fp, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($fp, ['key', 'ja', 'en']);

foreach ($ja as $key => $jaText) {
    if (!array_key_exists($key, $en)) {
        continue;
    }

    $jaValue = (string) $jaText;
    $enValue = (string) $en[$key];

    $exclude = false;

    // Exclude "タグ一覧"
    if ($jaValue === 'タグ一覧' || $enValue === 'Tags') {
        $exclude = true;
    }

    // Exclude auto-sent / template notification related strings.
    if (
        str_contains($jaValue, '自動送信') ||
        str_contains($jaValue, 'テンプレート') ||
        str_contains(strtolower($enValue), 'automated') ||
        str_contains(strtolower($enValue), 'auto-sent') ||
        str_contains(strtolower($enValue), 'template')
    ) {
        $exclude = true;
    }

    if ($exclude) {
        continue;
    }

    if (isset($replaceMap[$enValue])) {
        $enValue = $replaceMap[$enValue];
    }

    fputcsv($fp, [$key, $jaValue, $enValue]);
}

fclose($fp);
echo "Generated: docs/ui-strings-ja-en-full-excluded.csv\n";
