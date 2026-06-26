<?php

namespace App\Providers;

use App\Constants\ExtensionConst;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Validator;
use App\Providers\Admin\ExtensionProvider;

ini_set('memory_limit','-1');
ini_set('serialize_precision','-1');

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Paginator::useBootstrapFive();
        if($this->app->environment('production'))
        {
            \URL::forceScheme('https');
        }
        $this->extendValidationRule();
    }

    /**
     * extend laravel validation rules
     */
    public function extendValidationRule()
    {

        // validated
        Validator::extend('g_recaptcha_verify', function ($attribute, $value, $parameters, $validator) {
            $extension = ExtensionProvider::get()->where('slug', ExtensionConst::GOOGLE_RECAPTCHA_SLUG)->first();
            if (! $extension) {
                return false;
            }

            $secret_key = $extension->shortcode->secret_key->value ?? '';

            $response = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                'secret' => $secret_key,
                'response' => $value,
            ])->json();

            if (isset($response['success']) && $response['success'] == false) {
                logger('google recaptcha verification failed!', [$response]);

                return false;
            }

            return true;

        }, __('Recaptcha verification failed! Please try again.'));

    }
}
