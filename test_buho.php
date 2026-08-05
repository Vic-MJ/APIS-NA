<?php
require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$cedula = '12345678';
$urlBuho = "https://www.buholegal.com/{$cedula}/";
$respuesta = \Illuminate\Support\Facades\Http::withHeaders([
    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36',
    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,*/*;q=0.8',
    'Accept-Language' => 'es-ES,es;q=0.8,en-US;q=0.5,en;q=0.3',
])
->timeout(10)
->withoutVerifying()
->get($urlBuho);

if ($respuesta->successful()) {
    $html = $respuesta->body();
    file_put_contents(__DIR__ . '/buho.html', $html);
    echo "Response successful. HTML saved to buho.html\n";
} else {
    echo "Request failed with status " . $respuesta->status() . "\n";
}
