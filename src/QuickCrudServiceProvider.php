<?php

namespace Roc0611\QuickCrud;

use Illuminate\Support\ServiceProvider;
use Roc0611\QuickCrud\Commands\MakeCrudCommand;

class QuickCrudServiceProvider extends ServiceProvider
{
    public function boot()
    {
       if ($this->app->runningInConsole()) {
           $this->commands([
               MakeCrudCommand::class,
           ]);
       }
    }

    public function register()
    {
        //
    }
}