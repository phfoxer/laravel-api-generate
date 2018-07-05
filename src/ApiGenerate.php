<?php

namespace Phfoxer\ApiGenerate;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ApiGenerate extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:api {--table=}  {--route=0}  {--module=0}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate api resource';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $table  = $this->option('table');
        $route  = $this->option('route');
        $module = $this->option('module');

        $module = ($module=='0')? 'General' : $module;
        $route  = ($route=='0')? $table : $route;

        $root = app_path().DIRECTORY_SEPARATOR;
        $app = $root.'Modules'.DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR;

        if(@mkdir($root.'Modules',0755)){
            mkdir($root.'Modules'.DIRECTORY_SEPARATOR.'General',0755);
        }

        if (strlen($table)==0) {
            $this->info("Table name not found! use --table=table_name");
            die;
        }
        if (!$route) {
            $this->info("Route name not found! use --route=route-name");
            die;
        }    
        if ($module) {
            $module = 'Modules'.DIRECTORY_SEPARATOR.$module;
        }else {
            $module = 'Modules';
        }
        $package = ucfirst($table);
        if(substr_count($table, '_')){
            $split = explode('_',$table);
            $package = '';
            foreach ($split as $key => $value) {
                $package .= ucfirst($value);
            }
        }
        $packageLower = strtolower($package);
$controller = '<?php
namespace App\\'.$module.'\\'.$package.'\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\\'.$module.'\\'.$package.'\Repositories\\'.$package.'Repository;

class '.$package.'Controller extends Controller
{
    private $'.$packageLower.'Repository;

    function __construct('.$package.'Repository $'.$packageLower.'Repository ){
        $this->'.$packageLower.'Repository = $'.$packageLower.'Repository;
    }

    public function index(Request $request){
        try {
            $data =  $this->'.$packageLower.'Repository->index($request);
            return response()->json($data, 200);
        } catch(\Exception $e){
            $data = [
                "message"=> "Error, try again!",
                "text"=>    $e->getMessage()
            ];
            return response()->json($data, 401);
        }
    }

    public function show($id){
        try {
            $data = $this->'.$packageLower.'Repository->show($id);
            return response()->json($data, 200);
        } catch(\Exception $e){
            $data = [
                "message"=> "Error, try again!",
                "text"=>    $e->getMessage()
            ];
            return response()->json($data, 400);
        }
    }

    public function store(Request $request){
        try {
            $data = $this->'.$packageLower.'Repository->store($request);
            return response()->json($data, 200);
        } catch(\Exception $e){
            $data = [
                "message"=> "Error, try again!",
                "text"=>    $e->getMessage()
            ];
            return response()->json($data, 400);
        }
    }

    public function update(Request $request, $id){
        try {
            $data = $this->'.$packageLower.'Repository->update($request, $id);
            return response()->json($data, 200);
        } catch(\Exception $e){
            $data = [
                "message"=> "Error, try again!",
                "text"=>    $e->getMessage()
            ];
            return response()->json($data, 400);
        }
    }

    public function destroy($id){
        try {
            $data = $this->'.$packageLower.'Repository->destroy($id);
            return response()->json($data, 200);
        } catch(\Exception $e){
            $data = [
                "message"=> "Error, try again!",
                "text"=>    $e->getMessage()
            ];
            return response()->json($data, 400);
        }
    }

}';

        /**
        *
        */
        $columns = Schema::getColumnListing($table);
        $filtersFields = (array) $columns;
        $fields = "";
        $fields = "'".implode("','", (array) $columns)."'";

        if (count($columns)==0) {
            echo "The table ".$table." not exists!";
            die;
        }

        $relations = '';
        $with = [];

        foreach ($filtersFields as $field) {
            $relations .= $this->findModels($module,$field);
            if (substr_count($field,'_id')) {
            $with[] = str_replace('_id','',$field);
            }
        }

        $model = '<?php
namespace App\\'.$module.'\\'.$package.'\Models;
use Illuminate\Database\Eloquent\Model;

class '.$package.' extends Model
{
    protected $table    = "'.$table.'";
    protected $fillable = ['.$fields.'];
'.$relations.'
}';

// campos validos
$dbFieldsTxt = '$data = [';
foreach ($filtersFields as $field) {
    if(!in_array($field,['id','created_at','updated_at'])){
        $dbFieldsTxt .= '
            "'.$field.'"=>$request->'.$field.',';
    }
}
$dbFieldsTxt .= '
        ];';

$repository = '<?php
namespace App\\'.$module.'\\'.$package.'\Repositories;
use App\\'.$module.'\\'.$package.'\Models\\'.$package.';
use App\\'.$module.'\\'.$package.'\Repositories\\'.$package.'SearchRepository;

use Illuminate\Http\Request;

class '.$package.'Repository
{
    private $'.$packageLower.'SearchRepository;
    function __construct('.$package.'SearchRepository $'.$packageLower.'SearchRepository){
        $this->'.$packageLower.'SearchRepository = $'.$packageLower.'SearchRepository;
    }

    public function index(Request $request){
        return $this->'.$packageLower.'SearchRepository->search('.$package.'::with(["'.implode('","',$with).'"]), $request);
    }

    public function show($id){
    	return '.$package.'::where(["id"=>$id])->first();
    }

    public function store($request){
        '.$dbFieldsTxt.'
    	return '.$package.'::create($data);
    }

    public function update($request, $id){
        '.$dbFieldsTxt.'
    	return '.$package.'::where(["id"=>$id])->update($data);
    }

    public function destroy($id){
    	return '.$package.'::where(["id"=>$id])->delete();
    }

}';

// 

$filters = "";
foreach ($filtersFields as $field) {
    if(!in_array($field,['created_at','updated_at'])){
        $filters .= '
    if ($request->'.$field.') {
        $queryBuilder->where("'.$field.'","=",$request->'.$field.');
    }
';
    }
}

$repositorySearch = '<?php
namespace App\\'.$module.'\\'.$package.'\Repositories;
use App\\'.$module.'\\'.$package.'\Models\\'.$package.';

use Illuminate\Http\Request;

class '.$package.'SearchRepository
{
    public function search($queryBuilder, $request){
'.$filters.'
        return $queryBuilder->paginate(($request->count) ? $request->count : 20);
    }
}';

    if (!is_dir($app)) {
        $this->info('The module '.$module.' not exists');
        die;
    }
    $mod = $app.$package;

	if(@mkdir($mod,0755)){
		// Directories: Models | Controlers | Repositories
		mkdir($mod.DIRECTORY_SEPARATOR.'Models',0755);
		mkdir($mod.DIRECTORY_SEPARATOR.'Controllers',0755);
		mkdir($mod.DIRECTORY_SEPARATOR.'Repositories',0755);
        // Archives: Models | Controlers | Repositories
        $model = str_replace('/',"\\",$model);
        $controller = str_replace('/',"\\",$controller);
        $repository = str_replace('/',"\\",$repository);
        $repositorySearch = str_replace('/',"\\",$repositorySearch);
		File::put($mod.DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR.$package.'.php', $model);
		File::put($mod.DIRECTORY_SEPARATOR.'Controllers'.DIRECTORY_SEPARATOR.$package.'Controller.php', $controller);
		File::put($mod.DIRECTORY_SEPARATOR.'Repositories'.DIRECTORY_SEPARATOR.$package.'Repository.php', $repository);
		File::put($mod.DIRECTORY_SEPARATOR.'Repositories'.DIRECTORY_SEPARATOR.$package.'SearchRepository.php', $repositorySearch);
		//
		$this->info('The module '.$package.' has created!');
		$this->info('check in '.$mod);
	} else {
		$this->info('The package '.$package.' already exists!');
	}

    if ($route) {
        $base = '\App\\'.$module.'\\';
        $ctrl = $package."Controller";
        // Criando rotas
        $path_route = base_path().DIRECTORY_SEPARATOR.'routes'.DIRECTORY_SEPARATOR.'api.php';
        $base_package = str_replace('/',"\\",$base.$package);
$routes =
'
/**
* Module '.$package.'
*/
Route::apiResource("'.$route.'","'.$base_package.'\Controllers\\'.$ctrl.'");';
//
        File::append($path_route, $routes);
        $this->info('Routes created!');
    }


    }

    private function findModels($module, $field)
    {
        if (substr_count($field,'_id')==0) {
            return false;
        }
        $table = str_replace('_id','',$field);
        $root = app_path().DIRECTORY_SEPARATOR;
        $app = $root.$module.DIRECTORY_SEPARATOR;
        $allModulesPath = $root.'Modules'.DIRECTORY_SEPARATOR."*";
        $allModules= glob($allModulesPath, GLOB_ONLYDIR);
        $allModulePath = [];
        $allPackagePath = [];
        foreach ($allModules as $modulesPath) {
            $allModulePath = glob($modulesPath.DIRECTORY_SEPARATOR."*", GLOB_ONLYDIR);
            foreach ($allModulePath as $packages) {
                $temp = glob($packages.DIRECTORY_SEPARATOR."*", GLOB_ONLYDIR);
                $allPackagePath = array_merge($allPackagePath,$temp);
                $allPackagePath = array_filter($allPackagePath,function($item){
                    return (substr_count($item,'Models'))? $item : false;
                });
            }
        }
        $model = '';
        $files = [];
        foreach ($allPackagePath as $models) {
            $files = glob($models.DIRECTORY_SEPARATOR."*.php");
                foreach ($files as $file) {
                $content = File::get($file);
                if (substr_count($content,'"'.$table.'"')) {
                    $className = explode('class ',$content);
                    $className = explode(' extends',$className[1]);
                    $model = $models.DIRECTORY_SEPARATOR.$className[0];
                    $model =  explode('app',$model);
                    $model =  'App'.$model[1];
                }
            }

        }
return '
    public function '.$table.'(){
        return $this->hasMany("'.$model.'","id","'.$field.'");
    }
';
    }
}
