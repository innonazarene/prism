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

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
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
        foreach($tables as $table)
        {
			//model and controller
            if($table === 'migrations') continue;
            $className = Str::camel(Str::singular($table));
            $className = ucfirst($className);
            Artisan::call('krlove:generate:model', ['class-name' => $className, '--table-name' => $table, '--output-path'=>'Models','--namespace'=>'App\Models']);
			Artisan::call('make:controller '.$className.'Controller --resource');
			echo 'Done:'.$className.', ';
		}


    }
}
