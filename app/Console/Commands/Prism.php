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
	protected $timestamps = false;
	protected $packages =  [
		'kitloong/laravel-migrations-generator',
		'orangehill/iseed',
		'krlove/eloquent-model-generator'
	];
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
		$this->generateCORSMiddleWare();
        $this->updateApiRoutes($tables,'v1');
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
		shell_exec('start cmd.exe @cmd /k "php artisan migrate:generate --squash --skip-log & exit"');
		echo PHP_EOL.'Done:Migration';
	}

	private function backupOrRestoreFile($filePath, $backupFilePath)
	{
		if (!File::exists($backupFilePath)) {
			$fileContents = File::get($filePath);
			File::put($backupFilePath, $fileContents);
			echo PHP_EOL.'Done: add'.$filePath.' to '.$backupFilePath;
		} else {
			File::delete($filePath);
			File::copy($backupFilePath, $filePath);
			echo PHP_EOL.'Done: restore'.$filePath.' to '.$backupFilePath;
		}


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
			$body .= ''.PHP_EOL.'    //create'.PHP_EOL.'    public function create()'.PHP_EOL.'    {'.PHP_EOL.'        '.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //store'.PHP_EOL.'    public function store(Request $request)'.PHP_EOL.'    {'.PHP_EOL.'        return '.$className.'::create($request->all());'.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //show Example : http://127.0.0.1:8000/api/{{PREFIX}}/{{API-ROUTE}}/{{ID}}?with={{WITH-FUNCTION(found in models)}}&'.PHP_EOL.'    //orderBy={{FIELD-NAME}:{{ASC or DESC}}&limit={{LIMIT}}&fields={{FIELD}}&filter={{FILTER-FIELD}}:{{VALUE}}'.PHP_EOL.'    public function show(Request $request, $id)'.PHP_EOL.'    {'.PHP_EOL.'        return response()->json($this->displayRequest($id,$request,new '.$className.'));'.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //edit'.PHP_EOL.'    public function edit($id)'.PHP_EOL.'    {'.PHP_EOL.'        '.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //update'.PHP_EOL.'    public function update(Request $request, $id)'.PHP_EOL.'    {'.PHP_EOL.'        '.PHP_EOL.'        $data = '.$className.'::findOrFail($id);'.PHP_EOL.'        $data->update($request->all());'.PHP_EOL.'        return $data;'.PHP_EOL.''.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //destroy'.PHP_EOL.'    public function destroy($id)'.PHP_EOL.'    {'.PHP_EOL.'        '.PHP_EOL.'        $data = '.$className.'::findOrFail($id);'.PHP_EOL.'        $data->delete();'.PHP_EOL.'        return $data;'.PHP_EOL.''.PHP_EOL.'    }'.PHP_EOL.PHP_EOL.'}';
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
		$routes_content = PHP_EOL.'Route::prefix("'.$prefix.'")->group(function(){';
		$routes_use_content = '';
		foreach($tables as $table)
		{
			if($table === 'migrations') continue;
			$className = Str::camel(Str::singular($table));
			$className = ucfirst($className);
			$apiFileContents = File::get(base_path('routes/api.php'));
			$routes_content .= PHP_EOL."    Route::Resource('".strtolower($className)."', ".$className."Controller::class);";
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
		foreach($tables as $table)
		{
			Artisan::call('iseed '.$table.' --classnameprefix='.$prefix);
			echo PHP_EOL.'Done: Seeding - '.$table;
		}
	}

	private function makeCoreRequest()
	{
		$strUpUse = 'use Illuminate\Routing\Controller as BaseController;';
		$coreCtrlContent = file_get_contents(base_path('public/backup/Controller.backup.php'));
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
			\$query->findOrFail(\$id);
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
		return response()->json(\$results);
	}";
		$content = str_replace('	','    ',$content);
		$coreCtrlContent = str_replace('}',$content.PHP_EOL."}", $coreCtrlContent);
		$coreCtrlContent = str_replace($strUpUse,$strUpUse.PHP_EOL."use Illuminate\Http\Request;", $coreCtrlContent);
		file_put_contents(base_path('app/Http/Controllers/Controller.php'), $coreCtrlContent);
		echo PHP_EOL.'Done: add function in Core core-controller';
	}

	private function generateCORSMiddleWare()
	{
		$valueString = '            \App\Http\Middleware\CORSMiddleware::class,';
		$replaceString = "'throttle:api',";
		$corsBasePath = base_path('app/Http/Middleware/CORSMiddleware.php');
		$kernelBasePath = base_path('app/Http/Kernel.php');
		shell_exec('rm -rf '.$corsBasePath);
		Artisan::call('make:middleware CORSMiddleware');


		$corsContent = file_get_contents($corsBasePath);
		$kernelContent = file_get_contents($kernelBasePath);
		$tempContent = "//Example : \$allowedMethods = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];
		".PHP_EOL."        \$allowedMethods = ['GET', 'HEAD', 'POST', 'PUT', 'PATCH', 'DELETE', 'OPTIONS'];

		if (in_array(\$request->method(), \$allowedMethods)) {
			\$response = \$next(\$request);
			\$response->header('Access-Control-Allow-Origin', '*');
			\$response->header('Access-Control-Allow-Methods', implode(',', \$allowedMethods));
			if (\$request->method() == 'OPTIONS') {
				\$response->header('Access-Control-Allow-Headers', 'Content-Type, Authorization');
			}
			return \$response;
		}

		return response()->json(['message' => 'Forbidden'], 403);";
		$corsContent = str_replace('return $next($request);',$tempContent,$corsContent);
		$corsContent = str_replace('	','    ',$corsContent);

		$kernelContent = (strpos($kernelContent,$valueString)) ? $kernelContent : str_replace($replaceString,$replaceString.PHP_EOL.$valueString,$kernelContent);
		file_put_contents($corsBasePath, $corsContent);
		file_put_contents($kernelBasePath, $kernelContent);
	}


	private function cleanFiles()
	{

		if(!is_dir(base_path('public/backup')))
		{
			mkdir(base_path('public/backup'));
			$this->runBackupAndRestore();
			echo PHP_EOL.'Done: Make Directory Backup';
		}else{
			mkdir(base_path('public/backup'));
			shell_exec('rm -rf app/models/* & rm -rf database/migrations/* & rm -rf app/Http/Controllers/*');
		}
		echo PHP_EOL.'Done: Clean Files';
	}

	public function runBackupAndRestore()
	{
		$this->backupOrRestoreFile(base_path('routes/api.php'), base_path('public/backup/api.backup.php'));
		$this->backupOrRestoreFile(base_path('app/Http/Controllers/Controller.php'), base_path('public/backup/Controller.backup.php'));
	}
}