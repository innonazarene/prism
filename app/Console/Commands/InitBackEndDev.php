<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class InitBackEndDev extends Command
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


	protected $path = '';
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

		$routes_content = '';
		$routes_use_content = '';

		//check the packages if exist
		$composerLockFile = File::get(base_path('composer.lock'));
		foreach($this->packages as $package)
		{

			if (strpos($composerLockFile, $package) == false) {
				exec("composer require --dev $package");
			}
		}
		//Migrate Database using this packages : https://github.com/kitloong/laravel-migrations-generator
		shell_exec('rm -rf database/migrations/*');
		shell_exec('start cmd.exe @cmd /k "php artisan migrate:generate --squash --skip-log  & exit"');


		$tables = DB::select('SHOW TABLES');
        $databaseName = config('database.connections.'.config('database.default').'.database');
        $tables = collect($tables);
        $tables = $tables->pluck('Tables_in_'.$databaseName);


		// Check if the web.backup.php file exists
		if (!File::exists(base_path('routes/web.backup.php'))) {
			// Get the contents of the web.php file
			$webFileContents = File::get(base_path('routes/web.php'));

			// Create a backup of the web.php file
			File::put(base_path('routes/web.backup.php'), $webFileContents);
		}else{
			// Delete the web.php file
			File::delete(base_path('routes/web.php'));

			// Copy the web.backup.php file to web.php
			File::copy(base_path('routes/web.backup.php'), base_path('routes/web.php'));
		}


        foreach($tables as $table)
        {
			$webFileContents = File::get(base_path('routes/web.php'));

			//model and controller
            if($table === 'migrations') continue;
            $className = Str::camel(Str::singular($table));
            $className = ucfirst($className);
            Artisan::call('krlove:generate:model', ['class-name' => $className, '--table-name' => $table, '--output-path'=>'Models','--namespace'=>'App\Models']);
			Artisan::call('make:controller '.$className.'Controller');

			//edit content
			$body = '';
			$basePath = base_path('App\\Http\\Controllers\\'.$className.'Controller.php');
			$haystack = file_get_contents($basePath);
			$haystack = str_replace('}', '', $haystack);
			$body .= '{'.PHP_EOL.'    //Index'.PHP_EOL.'    public function index()'.PHP_EOL.'    {'.PHP_EOL.'        return '.$className.'::with([])->get()->limit(1000);'.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //create'.PHP_EOL.'    public function create()'.PHP_EOL.'    {'.PHP_EOL.'        '.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //store'.PHP_EOL.'    public function store(Request $request)'.PHP_EOL.'    {'.PHP_EOL.'        return '.$className.'::create($request->all());'.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //show'.PHP_EOL.'    public function show($id)'.PHP_EOL.'    {'.PHP_EOL.'        return '.$className.'::findOrFail($id);'.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //edit'.PHP_EOL.'    public function edit($id)'.PHP_EOL.'    {'.PHP_EOL.'        '.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //update'.PHP_EOL.'    public function update(Request $request, $id)'.PHP_EOL.'    {'.PHP_EOL.'        '.PHP_EOL.'        $data = '.$className.'::findOrFail($id);'.PHP_EOL.'        $data->update($request->all());'.PHP_EOL.'        return $data;'.PHP_EOL.''.PHP_EOL.'    }'.PHP_EOL;
			$body .= ''.PHP_EOL.'    //destroy'.PHP_EOL.'    public function destroy($id)'.PHP_EOL.'    {'.PHP_EOL.'        '.PHP_EOL.'        $data = '.$className.'::findOrFail($id);'.PHP_EOL.'        $data->delete();'.PHP_EOL.'        return $data;'.PHP_EOL.''.PHP_EOL.'    }'.PHP_EOL.PHP_EOL.'}';
			$haystack = str_replace('{', $body, $haystack);

			$haystack = str_replace($body, $body, $haystack);
			if (strpos($haystack, $this->needles['table'].PHP_EOL.'use App\\Models\\'.$className.';') == false) {
				$haystack = str_replace($this->needles['table'], $this->needles['table'].PHP_EOL.'use App\\Models\\'.$className.';', $haystack);
			}

			// file_put_contents($basePath, $haystack);
			$routes_content .= PHP_EOL."Route::Resource('".strtolower($className)."', ".$className."Controller::class);";
			$routes_use_content .= PHP_EOL.'use App\Http\Controllers\\'.$className.'Controller;';

			echo 'Done:'.$className.', ';
		}
		$webFileContents = str_replace('<?php','<?php'.PHP_EOL.$routes_use_content, $webFileContents);
		echo 'Done: Added WebFileContents -> routes use';
		$webFileContents = str_replace('});','});'.PHP_EOL.PHP_EOL.$routes_content, $webFileContents);
		echo 'Done: Added WebFileContents -> routes content';

		file_put_contents(base_path('routes/web.php'), $webFileContents);

		Artisan::call('route:clear');

    }
}
