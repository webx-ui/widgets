<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Route;
use WebxUi\Widgets\Forms\CsrfController;
use WebxUi\Widgets\Forms\ShowcaseController;

/*
 * The routes of the forms (spec §17.3). The `web` group: the token route has to start the session
 * and set the XSRF-TOKEN cookie, the showcase has to answer a form without JavaScript through the
 * session as any form of the site does.
 */
Route::middleware('web')
    ->prefix(trim((string) config('webx-widgets.path', 'webx/widgets'), '/'))
    ->name('webx-widgets.')
    ->group(static function (): void {
        Route::get('csrf', CsrfController::class)->name('csrf');
        Route::post('showcase', ShowcaseController::class)->name('showcase');
        Route::post('showcase/validation', [ShowcaseController::class, 'validation'])->name('showcase.validation');
        Route::get('showcase/suggest', [ShowcaseController::class, 'suggest'])->name('showcase.suggest');
    });
