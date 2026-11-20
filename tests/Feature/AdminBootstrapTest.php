<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminBootstrapTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_is_created_verified_and_keeps_its_password_on_reseed(): void
    {
        (new RoleSeeder)->run();
        config()->set('app.initial_admin.email', 'bootstrap-admin@example.com');
        config()->set('app.initial_admin.password', 'strong-bootstrap-password');

        (new UserSeeder)->run();

        $admin = User::where('email', 'bootstrap-admin@example.com')->firstOrFail();
        $this->assertTrue($admin->hasVerifiedEmail());
        $this->assertTrue($admin->hasRole('admin'));
        $this->assertTrue(Hash::check('strong-bootstrap-password', $admin->password));

        config()->set('app.initial_admin.password', 'different-password-value');
        (new UserSeeder)->run();

        $this->assertSame(1, User::where('email', 'bootstrap-admin@example.com')->count());
        $this->assertTrue(Hash::check('strong-bootstrap-password', $admin->fresh()->password));
    }
}
