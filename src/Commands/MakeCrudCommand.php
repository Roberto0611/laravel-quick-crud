<?php

namespace Roc0611\QuickCrud\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class MakeCrudCommand extends Command
{
    protected $signature = 'quick:crud';
    protected $description = 'Create a new CRUD (Model, Migration, Controller, Views)';
    protected $fields = []; 

    public function handle()
    {
        // --- MODEL GENERATION ---
        
        $name = $this->ask('What is the Model name? (e.g., Product)');
        $name = ucfirst($name);

        $migrationSchema = $this->getFieldsInteraction(); 

        $stubPath = __DIR__ . '/../Stubs/model.stub';
        $destinationPath = base_path("app/Models/{$name}.php");

        if (File::exists($destinationPath)) {
            $this->error("The model {$name} already exists!");
            return;
        }

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

        $migrationContent = File::get($migrationStubPath);
        $migrationContent = str_replace('{{tableName}}', $tableName, $migrationContent);
        $migrationContent = str_replace('{{fields}}', $migrationSchema, $migrationContent);
        File::put($migrationPath, $migrationContent);

        $this->info("Migration {$migrationFileName} created successfully!");

        // --- CONTROLLER GENERATION ---

        $variablePlural = Str::camel(Str::plural($name)); 
        $variableSingular = Str::camel($name);
        $viewFolder = $tableName;

        $controllerStubPath = __DIR__ . '/../Stubs/controller.stub';
        $controllerPath = base_path("app/Http/Controllers/{$name}Controller.php");

        $controllerContent = File::get($controllerStubPath);

        $controllerContent = str_replace(
            ['{{modelName}}', '{{variablePlural}}', '{{variableSingular}}', '{{viewFolder}}'], 
            [$name, $variablePlural, $variableSingular, $viewFolder], 
            $controllerContent
        );

        File::put($controllerPath, $controllerContent);

        $this->info("Controller {$name}Controller created successfully!");

        // --- ROUTE GENERATION ---

        $routeLine = "\nRoute::resource('{$tableName}', \App\Http\Controllers\\{$name}Controller::class);";
        File::append(base_path('routes/web.php'), $routeLine);
        $this->info("Route for {$tableName} added to web.php!");

        // --- VIEW GENERATION ---

        $this->generateViews($name); 
    }

    /**
     * Ask the user for fields interactively.
     */
    private function getFieldsInteraction(): string {
        $fieldsString = "";
        
        $this->info("Let's define the fields for your table!");

        while (true) {
            $fieldName = $this->ask('Field name (leave empty to finish)');
            
            if (empty($fieldName)) {
                break;
            }

            // check for duplicate field names
            $exists = false;
            foreach ($this->fields as $field) {
                if ($field['name'] === $fieldName) {
                    $exists = true;
                    break;
                }
            }

            if ($exists) {
                $this->error("The field '{$fieldName}' already exists! Try another name.");
                continue; // Reinicia el ciclo sin guardar nada
            }

            $type = $this->choice(
                "What is the type for '{$fieldName}'?", 
                ['string', 'integer', 'text', 'boolean', 'date', 'decimal'],
                0 
            );

            $this->fields[] = ['name' => $fieldName, 'type' => $type];

            $fieldsString .= "\$table->{$type}('{$fieldName}');\n            ";
        }

        return $fieldsString;
    }

    /**
     * Generate Blade Views with Tailwind CSS
     */
    private function generateViews($name)
    {
        $tableName = Str::lower(Str::plural($name)); 
        $viewFolder = $tableName;
        $variableSingular = Str::camel($name); 

        // 1. Create folder resources/views/name
        $path = base_path("resources/views/{$viewFolder}");
        if (!File::exists($path)) {
            File::makeDirectory($path, 0755, true);
        }

        // 2. Generate dynamic HTML based on $this->fields
        $tableHeaders = "";
        $tableBody = "";
        $formFields = "";
        $formFieldsEdit = "";

        foreach ($this->fields as $field) {
            $fn = $field['name'];
            $ucFn = ucfirst($fn);

            // For Index (Table)
            $tableHeaders .= "<th scope=\"col\" class=\"px-6 py-3\">{$ucFn}</th>\n                                    ";
            $tableBody .= "<td class=\"px-6 py-4 text-gray-900 dark:text-gray-100\">{{ \${$variableSingular}->{$fn} }}</td>\n                                    ";

            // For Forms (Create/Edit)
            $formFields .= $this->getTailwindInput($field['type'], $fn, $variableSingular, false);
            $formFieldsEdit .= $this->getTailwindInput($field['type'], $fn, $variableSingular, true);
        }

        // 3. Create physical files
        $views = ['index', 'create', 'edit'];
        foreach ($views as $view) {
            // Ensure these stubs exist in src/Stubs/views/
            $stubContent = File::get(__DIR__ . "/../Stubs/views/{$view}.stub");
            
            // Generic replacements
            $content = str_replace(
                ['{{modelName}}', '{{variablePlural}}', '{{variableSingular}}', '{{viewFolder}}'],
                [$name, $tableName, $variableSingular, $viewFolder],
                $stubContent
            );

            // Specific HTML replacements
            if ($view === 'index') {
                $content = str_replace(['{{tableHeaders}}', '{{tableBody}}'], [$tableHeaders, $tableBody], $content);
            } elseif ($view === 'create') {
                $content = str_replace('{{formFields}}', $formFields, $content);
            } elseif ($view === 'edit') {
                $content = str_replace('{{formFields}}', $formFieldsEdit, $content);
            }

            File::put("{$path}/{$view}.blade.php", $content);
        }

        $this->info("Views (Tailwind) generated in resources/views/{$viewFolder}");
    }

   /**
     * Helper to generate Tailwind HTML based on the data type.
     */
    private function getTailwindInput($type, $name, $variableSingular, $isEdit)
    {
        $label = ucfirst($name);
        
        // If it's Edit mode, use the model value; otherwise, use old() helper
        $valueAttr = $isEdit 
            ? "old('{$name}', \${$variableSingular}->{$name})" 
            : "old('{$name}')";

        $html = "
        <div>
            <label for=\"{$name}\" class=\"block font-medium text-sm text-gray-700 dark:text-gray-300\">{$label}</label>";

        if ($type === 'text') {
            // Textarea for long text fields
            $html .= "
            <textarea id=\"{$name}\" name=\"{$name}\" rows=\"4\" class=\"border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-500 focus:ring-blue-500 dark:focus:ring-blue-500 rounded-lg shadow-sm block mt-1 w-full\" required>{{ {$valueAttr} }}</textarea>";
        } else {
            // Standard Input for everything else (dates, strings, etc.)
            $inputType = ($type === 'date') ? 'date' : 'text';
            $html .= "
            <input id=\"{$name}\" type=\"{$inputType}\" name=\"{$name}\" value=\"{{ {$valueAttr} }}\" class=\"border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-gray-300 focus:border-blue-500 dark:focus:border-blue-500 focus:ring-blue-500 dark:focus:ring-blue-500 rounded-lg shadow-sm block mt-1 w-full\" required />";
        }

        $html .= "
            @error('{$name}')
                <p class=\"text-red-500 dark:text-red-400 text-xs mt-1\">{{ \$message }}</p>
            @enderror
        </div>";

        return $html;
    }
}