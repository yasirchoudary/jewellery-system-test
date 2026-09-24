<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;

$email = 'admin@localhost';
$pass = 'ADMINn11223$';

$user = User::where('email', $email)->orWhere('name', 'admin')->first();
if (! $user) {
    echo "NO_USER\n";
    exit(2);
}

if (Hash::check($pass, $user->password)) {
    echo "OK: id={$user->id} name={$user->name} email={$user->email}\n";
    exit(0);
}

echo "INVALID_PASSWORD\n";
exit(1);
