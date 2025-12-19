<?php

namespace Roc0611\QuickCrud\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class MakeCrudCommand extends Command
{
    protected $signature = 'quick:crud';
    protected $description = 'Create a new CRUD (Model, Migration, Controller, Views)';

    public function handle()
    {
        // Ask for the model name )
        $name = $this->ask('What is the Model name? (e.g., Product)');
        $name = ucfirst($name);

        // Define the paths
        $stubPath = __DIR__ . '/../Stubs/model.stub';
        
        // base_path() points to the root of the Laravel application where this package is installed
        $destinationPath = base_path("app/Models/{$name}.php");

        if (File::exists($destinationPath)) {
            $this->error("The model {$name} already exists!");
            return;
        }

        // Read the Stub file content
        $content = File::get($stubPath);
        $content = str_replace('{{modelName}}', $name, $content);
        File::put($destinationPath, $content);
        $this->info("Model {$name} successfully created in app/Models!");
    }
}