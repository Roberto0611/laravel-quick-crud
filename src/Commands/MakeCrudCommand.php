<?php

namespace Roc0611\QuickCrud\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeCrudCommand extends Command
{
    protected $signature = 'quick:crud';
    protected $description = 'Create a new CRUD (Model, Migration, Controller, Views)';

    public function handle()
    {

        // --- MODEL GENERATION ---
        
        // Ask for the model name )
        $name = $this->ask('What is the Model name? (e.g., Product)');
        $name = ucfirst($name);

        $migrationSchema = $this->getFieldsInteraction(); // get fields for migration

        $stubPath = __DIR__ . '/../Stubs/model.stub';
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

        // --- MIGRATION GENERATION ---

        $tableName = Str::lower(Str::plural($name));
        $timestamp = date('Y_m_d_His');
        $migrationFileName = "{$timestamp}_create_{$tableName}_table.php";
        
        $migrationStubPath = __DIR__ . '/../Stubs/migration.stub';
        $migrationPath = base_path("database/migrations/{$migrationFileName}");

        // Read Stub and Replace
        $migrationContent = File::get($migrationStubPath);
        $migrationContent = str_replace('{{tableName}}', $tableName, $migrationContent);
        $migrationContent = str_replace('{{fields}}', $migrationSchema, $migrationContent);
        File::put($migrationPath, $migrationContent);

        $this->info("Migration {$migrationFileName} created successfully!");

        // --- CONTROLLER GENERATION ---

        // variables for controller stub
        $variablePlural = Str::camel(Str::plural($name)); 
        $variableSingular = Str::camel($name);
        $viewFolder = $tableName;

        $controllerStubPath = __DIR__ . '/../Stubs/controller.stub';
        $controllerPath = base_path("app/Http/Controllers/{$name}Controller.php");

        $controllerContent = File::get($controllerStubPath);

        $controllerContent = str_replace(
            [
                '{{modelName}}', 
                '{{variablePlural}}', 
                '{{variableSingular}}', 
                '{{viewFolder}}'
            ], 
            [
                $name, 
                $variablePlural, 
                $variableSingular, 
                $viewFolder
            ], 
            $controllerContent
        );

        File::put($controllerPath, $controllerContent);

        $this->info("Controller {$name}Controller created successfully!");

        // --- ROUTE GENERATION ---
        $routeLine = "\nRoute::resource('{$tableName}', \App\Http\Controllers\\{$name}Controller::class);";
        File::append(base_path('routes/web.php'), $routeLine);
        $this->info("Route for {$tableName} added to web.php!");
    }

    /**
     * Ask the user for fields and types interactively.
     * Returns a string formatted for the migration file.
     */

    private function getfieldsInteraction(): string{
        $fieldsString = "";
        
        $this->info("Let's define the fields for your table!");

        while (true) {
            $fieldName = $this->ask('Field name (leave empty to finish)');
            
            if (empty($fieldName)) {
                break;
            }

            //Ask for field type using a selector
            $type = $this->choice(
                "What is the type for '{$fieldName}'?", 
                ['string', 'integer', 'text', 'boolean', 'date', 'decimal'],
                0 // default to string
            );

            // Construct the Laravel migration syntax 
            // Example result: $table->string('title');
            $fieldsString .= "\$table->{$type}('{$fieldName}');\n            ";
        }

        return $fieldsString;
    }
}