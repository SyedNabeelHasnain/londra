<?php
declare(strict_types=1);
namespace App\Providers\Filament;
use App\Http\Middleware\EnglishAdmin;
use App\Filament\Pages\PortalSettings;
use App\Filament\Pages\Readiness;
use App\Filament\Widgets\InquiryStats;
use App\Filament\Widgets\InquiryChart;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\FontProviders\LocalFontProvider;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Enums\ThemeMode;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        $gold = [50=>'#F1ECD9',100=>'#F1ECD9',200=>'#F1ECD9',300=>'#BDA177',400=>'#BDA177',500=>'#BDA177',600=>'#BDA177',700=>'#BDA177',800=>'#BDA177',900=>'#BDA177',950=>'#BDA177'];
        return $panel->default()->id('admin')->path('admin')->login()->passwordReset()->profile()
            ->viteTheme('resources/css/admin.css')->brandName('Londra Transfers')->brandLogo(asset('assets/artwork/logo-master-black.png'))->darkModeBrandLogo(asset('assets/artwork/logo-master-gold.png'))->brandLogoHeight('2.6rem')
            ->font('Arial', url: asset('assets/admin-system-fonts.css'), provider: LocalFontProvider::class)
            ->defaultAvatarProvider(\App\Filament\AvatarProviders\LocalAvatarProvider::class)
            ->colors(['primary'=>$gold,'danger'=>$gold,'warning'=>$gold,'success'=>$gold,'info'=>$gold])
            ->defaultThemeMode(ThemeMode::Dark)->databaseTransactions()->unsavedChangesAlerts()->broadcasting(false)
            ->multiFactorAuthentication([AppAuthentication::make()->recoverable()->brandName('Londra Transfers')], isRequired: false)
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->pages([Dashboard::class, PortalSettings::class, Readiness::class, \App\Filament\Pages\InterfaceCopy::class, \App\Filament\Pages\PrivacySettings::class, \App\Filament\Pages\ReleaseUpdate::class, \App\Filament\Pages\LaunchSettings::class, \App\Filament\Pages\SmtpSettings::class, \App\Filament\Pages\ManagementCoverage::class])->widgets([InquiryStats::class, InquiryChart::class])
            ->middleware([EncryptCookies::class,AddQueuedCookiesToResponse::class,StartSession::class,AuthenticateSession::class,ShareErrorsFromSession::class,ValidateCsrfToken::class,SubstituteBindings::class,DisableBladeIconComponents::class,DispatchServingFilamentEvent::class,EnglishAdmin::class])
            ->authMiddleware([Authenticate::class]);
    }
}
