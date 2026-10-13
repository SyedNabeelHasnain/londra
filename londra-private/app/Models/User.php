<?php
declare(strict_types=1);
namespace App\Models;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthentication;
use Filament\Auth\MultiFactor\App\Concerns\InteractsWithAppAuthenticationRecovery;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Validation\ValidationException;
final class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    use Notifiable, InteractsWithAppAuthentication, InteractsWithAppAuthenticationRecovery;
    protected $fillable = ['name', 'email', 'password'];
    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];
    protected function casts(): array { return ['password' => 'hashed', 'email_verified_at' => 'datetime']; }
    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (static::query()->exists()) { throw ValidationException::withMessages(['email' => 'This portal supports one administrator.']); }
            $user->administrator_slot = 1;
        });
    }
    public function canAccessPanel(Panel $panel): bool { return $panel->getId() === 'admin' && (int) $this->administrator_slot === 1; }
}
