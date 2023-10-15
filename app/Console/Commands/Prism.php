<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class Prism extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'prism:init';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'initialize all task for Migration, Seeding, Creating models, Routes, and Controller based on the Database(.env)';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();


    }
    //this $includeTablesForSeeding : list of table include for seeding:
	protected $includeTablesForSeeding = [];

    //set the timestamp of model true : false
	protected $timestamps = true;

    //--squash  = migrate dataase in 1 single file
    protected $migrateOption = '--skip-log & exit';

    //list of packages
	protected $packages =  [
		'kitloong/laravel-migrations-generator',
		'orangehill/iseed',
		'krlove/eloquent-model-generator'
	];

    //list of needles
	protected $needles = [
		'table' => 'namespace App\Http\Controllers;',
	];

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
		$tables = $this->getDatabaseTables();
		$this->cleanFiles();
		$this->checkAndInstallPackage();
        $this->migrateDatabase();
		//$this->startSeeding($tables,env('DB_DATABASE'));
		$this->makeCoreRequest();
		$this->generateModelsAndControllers($tables);
        $this->updateApiRoutes($tables,'public');
		Artisan::call('route:clear');

    }
	private function getDatabaseTables()
	{
		$tables = DB::select('SHOW TABLES');
        $databaseName = config('database.connections.'.config('database.default').'.database');
        $tables = collect($tables);
        $tables = $tables->pluck('Tables_in_'.$databaseName);
		return $tables;
	}

	private function checkAndInstallPackage()
	{
		//check the packages if exist
		$composerLockFile = File::get(base_path('composer.lock'));
		foreach($this->packages as $package)
		{
			if (strpos($composerLockFile, $package) == false) {
				exec("composer require --dev $package");
			}
		}
	}

	private function migrateDatabase()
	{
		//Migrate Database using this packages : https://github.com/kitloong/laravel-migrations-generator
		shell_exec('start cmd.exe @cmd /k "php artisan migrate:generate '.$this->migrateOption);
		echo PHP_EOL.'Done:Migration';
	}


	private function generateModelsAndControllers($tables)
	{
		foreach($tables as $table)
        {
			//model and controller
            if($table === 'migrations') continue;
            $className = Str::camel(Str::singular($table));
            $className = ucfirst($className);
            Artisan::call('krlove:generate:model', ['class-name' => $className,  '--table-name' => $table, '--output-path'=>'Models','--namespace'=>'App\Models','--no-timestamps']);
			Artisan::call('make:controller '.$className.'Controller');
            Artisan::call('make:request '.$className.'Request');
			$noTimestamp = '    public $timestamps = '.(($this->timestamps) ? 'true' : 'false').';';

			$modelContent = file_get_contents(base_path('App\\Models\\'.$className.'.php'));
			$modelContent = str_replace('];', '];'.PHP_EOL.$noTimestamp, $modelContent);
			file_put_contents(base_path('App\\Models\\'.$className.'.php'), $modelContent);

			//edit content
			$body = '';
			$basePath = base_path('App\\Http\\Controllers\\'.$className.'Controller.php');
			$ctrlContent = file_get_contents($basePath);
			$ctrlContent = str_replace('}', '', $ctrlContent);
			$body .= '{'.PHP_EOL.'    //Index Example : http://127.0.0.1:8000/api/{{PREFIX}}/{{API-ROUTE}}/?with={{WITH-FUNCTION(found in models)}}&'.PHP_EOL.'    //orderBy={{FIELD-NAME}:{{ASC or DESC}}&limit={{LIMIT}}&fields={{FIELD}}&filter={{FILTER-FIELD}}:{{VALUE}}'.PHP_EOL.'    public function index(Request $request)'.PHP_EOL.'    {'.PHP_EOL.'        return response()->json($this->displayRequest(NULL,$request,new '.$className.'));'.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //create'.PHP_EOL.'    public function create()'.PHP_EOL.'    {}'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //store'.PHP_EOL.'    public function store(Request $request)'.PHP_EOL.'    {'.PHP_EOL.'        return '.$className.'::create($request->all());'.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //show Example : http://127.0.0.1:8000/api/{{PREFIX}}/{{API-ROUTE}}/{{ID}}?with={{WITH-FUNCTION(found in models)}}&'.PHP_EOL.'    //orderBy={{FIELD-NAME}:{{ASC or DESC}}&limit={{LIMIT}}&fields={{FIELD}}&filter={{FILTER-FIELD}}:{{VALUE}}'.PHP_EOL.'    public function show(Request $request, $id)'.PHP_EOL.'    {'.PHP_EOL.'        return response()->json($this->displayRequest($id,$request,new '.$className.'));'.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //edit'.PHP_EOL.'    public function edit($id)'.PHP_EOL.'    {}'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //update'.PHP_EOL.'    public function update(Request $request, $id)'.PHP_EOL.'    {'.PHP_EOL.'        '.PHP_EOL.'        $data = '.$className.'::find($id);'.PHP_EOL.'        $data->update($request->all());'.PHP_EOL.'        return response()->json(["message" => "", "data" => $data]);'.PHP_EOL.''.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //destroy'.PHP_EOL.'    public function destroy($id)'.PHP_EOL.'    {'.PHP_EOL.'        '.PHP_EOL.'        $data = '.$className.'::findOrFail($id);'.PHP_EOL.'        $data->delete();'.PHP_EOL.'        return response()->json(["message" => "", "data" => $data]);'.PHP_EOL.''.PHP_EOL.'    }'.PHP_EOL.PHP_EOL.'}';
			$ctrlContent = str_replace('{', $body, $ctrlContent);

			$ctrlContent = str_replace($body, $body, $ctrlContent);
			if (strpos($ctrlContent, $this->needles['table'].PHP_EOL.'use App\\Models\\'.$className.';') == false) {
				$ctrlContent = str_replace($this->needles['table'], $this->needles['table'].PHP_EOL.'use App\\Models\\'.$className.';', $ctrlContent);
			}
			file_put_contents($basePath, $ctrlContent);
			echo PHP_EOL.'Done:'.$className.'[model && controller]';

		}
		echo PHP_EOL.'Done: add Controller and Models';

	}

	private function updateApiRoutes($tables,$prefix)
	{
		$routes_content = '//for auth:sanctum you can add routes here'.PHP_EOL.'Route::prefix("YOUR_PREFIX")->middleware(\'auth:sanctum\')->group(function(){});'.PHP_EOL.PHP_EOL.'Route::prefix("'.$prefix.'")->group(function(){';
		$routes_use_content = '';
		foreach($tables as $table)
		{
			if($table === 'migrations') continue;
			$className = Str::camel(Str::singular($table));
			$className = ucfirst($className);
			$apiFileContents = File::get(base_path('routes/api.php'));
			$routes_content .= PHP_EOL."    Route::Resource('".strtolower(Str::plural($className))."', ".$className."Controller::class)->only(['index','show']);";
			$routes_use_content .= PHP_EOL.'use App\Http\Controllers\\'.$className.'Controller;';
		}
		$apiFileContents = str_replace('<?php','<?php'.PHP_EOL.$routes_use_content, $apiFileContents);
		echo PHP_EOL.'Done: Added apiFileContents -> routes use';
		$apiFileContents = str_replace('});','});'.PHP_EOL.PHP_EOL.$routes_content.PHP_EOL."});", $apiFileContents);
		echo PHP_EOL.'Done: Added apiFileContents -> routes content';

		file_put_contents(base_path('routes/api.php'), $apiFileContents);
		echo PHP_EOL.'Done: add API-Routes';

	}

	private function startSeeding($tables,$prefix)
	{
		if(count($this->includeTablesForSeeding) > 0)
		{
			foreach($tables as $table)
			{
				foreach($this->includeTablesForSeeding as $includeTable)
				{
					if($includeTable == $table)
					{   echo PHP_EOL.'Start: Seeding - '.$table.PHP_EOL;
						Artisan::call('iseed '.$table.' --classnameprefix='.$prefix);
						echo 'Done: Seeding - '.$table;
					}
				}
			}
		}else{
			foreach($tables as $table)
			{
				echo PHP_EOL.'Start: Seeding - '.$table.PHP_EOL;
				Artisan::call('iseed '.$table.' --classnameprefix='.$prefix);
				echo 'Done: Seeding - '.$table;
			}
		}

	}

	private function makeCoreRequest()
	{
		$strUpUse = 'use Illuminate\Routing\Controller as BaseController;';
		$coreCtrlContent = file_get_contents(base_path('app/Http/Controllers/Controller.php'));
		$content = "    public \$requestRules = [
		'with' => 'string',
		'orderBy' =>'string',
		'limit' => 'integer',
		'fields' => 'string',
		'filter' => 'string'
	];
	public function displayRequest(\$id='',Request \$request, \$model)
	{
		\$validatedData = \$request->validate(\$this->requestRules);
		\$query = \$model->query();
		if(\$id != ''){
			return  \$query->where('id',\$id)->get();
		}
		if (isset(\$validatedData['with'])) {
			\$query->with(explode(',', \$validatedData['with']));
		}
		if (isset(\$validatedData['orderBy'])) {
			\$orderByParams = explode(',', \$validatedData['orderBy']);
			foreach (\$orderByParams as \$orderByParam) {
				\$orderByArray = explode(':', \$orderByParam);
				\$query->orderBy(\$orderByArray[0], \$orderByArray[1] ?? 'asc');
			}
		}
		if (isset(\$validatedData['filter'])) {
			\$filterParams = explode(',', \$validatedData['filter']);
			foreach (\$filterParams as \$filterParam) {
				\$filterArray = explode(':', \$filterParam);
				\$column = \$filterArray[0];
				\$value = \$filterArray[1] ?? null;
				if (\$value !== null) {
					\$query->where(\$column, \$value);
				}
			}
		}
		if (isset(\$validatedData['limit'])) {
			\$query->limit(\$validatedData['limit']);
		}
		if (isset(\$validatedData['fields'])) {
			\$query->select(explode(',', \$validatedData['fields']));
		}
		\$results = \$query->get();
		return \$results;
	}";
		$content = str_replace('	','    ',$content);
		$coreCtrlContent = str_replace('}',$content.PHP_EOL."}", $coreCtrlContent);
		$coreCtrlContent = str_replace($strUpUse,$strUpUse.PHP_EOL."use Illuminate\Http\Request;", $coreCtrlContent);
		file_put_contents(base_path('app/Http/Controllers/Controller.php'), $coreCtrlContent);
		echo PHP_EOL.'Done: add function in Core core-controller';
	}


	private function cleanFiles()
	{
        if(!file_exists(base_path('public/backup')."/api.backup.php") &&
        !file_exists(base_path('public/backup')."/Controller.backup.php")){
            $apiFile = File::get(base_path('routes/api.php'));
            $controllerFile = File::get(base_path('app/Http/Controllers/Controller.php'));
        }else{
            $apiFile = File::get(base_path('public/backup')."/api.backup.php");
            $controllerFile = File::get(base_path('public/backup')."/Controller.backup.php");
        }
        shell_exec('rm -rf public/backup');
        shell_exec('rm -rf public/backup');
        shell_exec('rm -rf app/models/* & rm -rf database/migrations/* & rm -rf app/Http/Controllers/* & rm -rf app/Http/requests/*');

        //check if the database/migration folder is exist
        if(!is_dir(base_path('database/migrations'))) {
            //create folder "migrations"
            mkdir(base_path('database/migrations'));
        }

        //check if the public/backup folder is exist
		if(!is_dir(base_path('public/backup'))) {
            //create backup folder
			mkdir(base_path('public/backup'));
            $this->putFile('api', $apiFile, base_path('public/backup/api.backup.php'));
            $this->putFile('controller', $controllerFile, base_path('public/backup/Controller.backup.php'));
            $this->putFile('api', $apiFile, base_path('routes/api.php'));
            $this->putFile('controller', $controllerFile, base_path('app/Http/Controllers/Controller.php'));
        }

        echo PHP_EOL . 'Done: Clean Files'.PHP_EOL;
	}


	private function putFile($file, $fileContent, $backupFilePath)
	{
		file_put_contents($backupFilePath, $fileContent);
		echo PHP_EOL.'Done: restore '.$file.' to '.$backupFilePath;

	}
}
