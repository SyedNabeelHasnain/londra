<?php
use App\Http\Controllers\{DiscoveryController,InquiryController,LocaleController,MediaController,PublicPageController};
use Illuminate\Support\Facades\Route;
Route::get('/', [LocaleController::class, 'entry']);
Route::get('/language/{locale}/{key}', [LocaleController::class, 'choose'])->whereIn('locale', ['en','it','es'])->where('key','[a-z0-9-]+')->name('language.choose');
Route::get('/media/{path}', [MediaController::class, 'show'])->where('path','[a-f0-9-]{36}\.(png|jpg|jpeg|webp)')->name('media');
Route::get('/robots.txt', [DiscoveryController::class, 'robots']);
Route::get('/sitemap.xml', [DiscoveryController::class, 'sitemap']);
Route::get('/llms.txt', [DiscoveryController::class, 'llms']);
Route::get('/llms-full.txt', [DiscoveryController::class, 'llmsFull']);
Route::prefix('{locale}')->where(['locale'=>'en|it|es'])->middleware('locale.public')->group(function (): void {
    Route::get('/form-tokens', [InquiryController::class, 'refresh'])->middleware('throttle:form-tokens')->name('forms.refresh');
    Route::post('/privacy-requests', [\App\Http\Controllers\DataRequestController::class, 'store'])->middleware('throttle:privacy-requests')->name('privacy.store');
    Route::get('/privacy-request/verify/{token}', [\App\Http\Controllers\DataRequestController::class, 'confirm'])->where('token', '[a-f0-9]{64}')->middleware('throttle:privacy-requests');
    Route::post('/privacy-request/verify/{token}', [\App\Http\Controllers\DataRequestController::class, 'verify'])->where('token', '[a-f0-9]{64}')->middleware('throttle:privacy-requests');
    Route::post('/inquiries', [InquiryController::class, 'store'])->middleware('throttle:inquiries')->name('inquiries.store');
    Route::get('/llms.txt', [DiscoveryController::class, 'llms']);
    Route::get('/{slug?}', [PublicPageController::class, 'show'])->where('slug', '[a-z0-9]+(?:[-/][a-z0-9]+)*')->name('public.page');
});
