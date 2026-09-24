<?php
require __DIR__ . '/../vendor/autoload.php';
$app = require_once __DIR__ . '/../bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

$email = 'admin@localhost';
$name = 'admin';
$passwordPlain = 'ADMINn11223$';

$user = User::where('email', $email)->orWhere('name', $name)->first();
if ($user) {
    $user->name = $name;
    $user->email = $email;
    $user->password = Hash::make($passwordPlain);
    $user->role = 'admin';
    $user->remember_token = Str::random(60);
    $user->save();
    echo "UPDATED:" . $user->id . "\n";
    exit(0);
}

$user = User::create([
    'name' => $name,
    'email' => $email,
    'password' => Hash::make($passwordPlain),
    'role' => 'admin',
    'remember_token' => Str::random(60),
]);

echo "CREATED:" . $user->id . "\n";
