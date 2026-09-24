<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

// Create user if not exists
$email = 'local@localhost';
$exists = User::where('email', $email)->first();
if ($exists) {
    echo "EXISTS:" . $exists->id . "\n";
    exit(0);
}

$user = User::create([
    'name' => 'Local Admin',
    'email' => $email,
    'password' => Hash::make('Secret123!'),
]);

echo "CREATED:" . $user->id . "\n";
