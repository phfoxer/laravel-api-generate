<?php

namespace Phfoxer\ApiGenerate;

use Phfoxer\ApiGenerate\DbSettings;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;

class ApiGenerate extends Command
{
    protected $dbSettings;
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'generate:api {--table=0}  {--route=0}  {--module=0} {--con=0} {--relation=0}';

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
        $conn   = $this->option('con');
        $hasRelation = ($this->option('relation') != '0');

        if ($conn == '0') {
            $this->makeModule($table, $route, $module, false, $hasRelation);
        } else {
            $this->dbSettings = new DbSettings;

            $tables = $this->dbSettings->getTables();
            if (!empty($tables)) {
                foreach ($tables as $tableName) {
                    $this->makeModule($tableName, $route, $module, $conn, $hasRelation);
                }
            } else {
                $this->info('Empty connection ' . $conn . '!');
            }
        }
        /**
         * Execute the console command.
         *
         * @return mixed
         */
    }

    private function makeModule($table, $route = '0', $module, $con = false, $hasRelation = false)
    {

        $module = ($module == '0') ? 'RestAPI' : $module;
        $route  = ($route == '0') ? $table : $route;

        $root = app_path() . DIRECTORY_SEPARATOR;
        $app = $root . $module . DIRECTORY_SEPARATOR;

        if (!is_dir($root . $module)) {
            mkdir($root . $module, 0755);
        }

        if (empty($table)) {
            $this->info("Table name not found! use --table=table_name");
            die;
        }

        if (empty($route)) {
            $this->info("Route name not found! use --route=route-name");
            die;
        }

        $package = $this->setPackage($table);

        $packageLower = strtolower($package);
        $controller = '<?php
namespace App\\' . $module . '\\' . $package . '\Controllers;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\\' . $module . '\\' . $package . '\Repositories\\' . $package . 'Repository;

class ' . $package . 'Controller extends Controller
{
    private $' . $packageLower . 'Repository;

    function __construct(' . $package . 'Repository $' . $packageLower . 'Repository ){
        $this->' . $packageLower . 'Repository = $' . $packageLower . 'Repository;
    }

    public function index(Request $request){
        $data =  $this->' . $packageLower . 'Repository->index($request);
        return response()->json($data, 200);
    }

    public function show($id){
        $data = $this->' . $packageLower . 'Repository->show($id);
        return response()->json($data, 200);
    }

    public function store(Request $request){
        $data = $this->' . $packageLower . 'Repository->store($request);
        return response()->json($data, 200);
    }

    public function update(Request $request, $id){
        $data = $this->' . $packageLower . 'Repository->update($request, $id);
        return response()->json($data, 200);
    }

    public function destroy($id){
        $data = $this->' . $packageLower . 'Repository->destroy($id);
        return response()->json($data, 200);
    }

}';
        # get list of fields
        $columns = Schema::getColumnListing($table);
        $filtersFields = (array) $columns;
        // exclude laravel fields
        $filtersFields = array_diff($filtersFields, ["created_at", "updated_at"]);
        $fields = "";
        $fields = "'" . implode("','", $filtersFields) . "'";
        if (count($columns) == 0) {
            echo "The table " . $table . " not exists!";
            die;
        }
        $relations = '';
        $with = [];
        foreach ($filtersFields as $field) {
            if (substr($field, -3) == '_id') {
                $verify = $this->findModels($module, $field, (($con || $hasRelation) ? true : false));
                if ($verify) {
                    $relations .= $verify;
                    $with[] = str_replace('_id', '', $field);
                }
            }
        }
        $model = '<?php
namespace App\\' . $module . '\\' . $package . '\Models;
use Illuminate\Database\Eloquent\Model;

class ' . $package . ' extends Model
{
    protected $table    = "' . $table . '";
    protected $fillable = [' . $fields . '];
' . $relations . '
}';

        // Fields array
        $dbFieldsTxt = '$data = [';
        foreach ($filtersFields as $field) {
            if (!in_array($field, ['id'])) {
                $dbFieldsTxt .= '
            "' . $field . '"=>$request->' . $field . ',';
            }
        }
        $dbFieldsTxt .= '
            ];';

        $allRelations = '';
        if (!empty($with)) {
            $allRelations = '"' . implode('","', $with) . '"';
        }
        // Validator
        $this->dbSettings = new DbSettings();
        $tableProp = $this->dbSettings->getTableProp($table);
        $Validator = '$validator = Validator::make($request->all(), [';
        foreach ($tableProp as $prop) {
            if (!in_array($prop[0]['name'], ["id", "created_at", "updated_at"])) {
                $Validator .= '
            "' . $prop[0]['name'] . '"=>"' . (($prop[0]['nullable'] == 'no') ? 'required' : 'nullable') . '",';
            }
        }
        $Validator .= '
        ]);';

        $repository = '<?php
namespace App\\' . $module . '\\' . $package . '\Repositories;
use App\\' . $module . '\\' . $package . '\Models\\' . $package . ';
use App\\' . $module . '\\' . $package . '\Repositories\\' . $package . 'SearchRepository;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;

class ' . $package . 'Repository
{
    private $' . $packageLower . 'SearchRepository;
    function __construct(' . $package . 'SearchRepository $' . $packageLower . 'SearchRepository){
        $this->' . $packageLower . 'SearchRepository = $' . $packageLower . 'SearchRepository;
    }

    public function index(Request $request){
        return $this->' . $packageLower . 'SearchRepository->search(' . $package . '::with([' . $allRelations . ']), $request);
    }

    public function show($id){
    	return ' . $package . '::where(["id"=>$id])->first();
    }

    public function store($request){
        ' . $Validator . '
        if($validator->errors()->count()){
            throw new \Exception($validator->errors()->first(),400);
        } 
        
        if(!$validator->errors()->count()){
            ' . $dbFieldsTxt . '
            return ' . $package . '::create($data);
        }
    }

    public function update($request, $id){
        ' . $Validator . '
        if($validator->errors()->count()){
            throw new \Exception($validator->errors()->first(),400);
        } 

        if(!$validator->errors()->count()){
            ' . $dbFieldsTxt . '
            return ' . $package . '::where(["id"=>$id])->update($data);
        }
    }

    public function destroy($id){
    	return ' . $package . '::where(["id"=>$id])->delete();
    }

}';

        // 

        $filters = "";
        foreach ($filtersFields as $field) {
            if (!in_array($field, ['created_at', 'updated_at'])) {
                $filters .= '
    if ($request->' . $field . ') {
        $queryBuilder->where("' . $field . '","=",$request->' . $field . ');
    }
';
            }
        }

        $orderByField = (isset($filtersFields[0])) ? $filtersFields[0] : 'id';

        $repositorySearch = '<?php
namespace App\\' . $module . '\\' . $package . '\Repositories;

class ' . $package . 'SearchRepository
{
    public function search($queryBuilder, $request){
' . $filters . '

        if ($request->order) {
            $order = ($request->order == "asc") ? "asc" : "desc";
            $queryBuilder->orderBy("' . $orderByField . '", $order);
        }

        return $queryBuilder->paginate(($request->count) ? $request->count : 20);
    }
}';

        $mod = $app . $package;

        if (@mkdir($mod, 0755)) {
            // Directories: Models | Controlers | Repositories
            mkdir($mod . DIRECTORY_SEPARATOR . 'Models', 0755);
            mkdir($mod . DIRECTORY_SEPARATOR . 'Controllers', 0755);
            mkdir($mod . DIRECTORY_SEPARATOR . 'Repositories', 0755);
            // Archives: Models | Controlers | Repositories
            $model = str_replace('/', "\\", $model);
            $controller = str_replace('/', "\\", $controller);
            $repository = str_replace('/', "\\", $repository);
            $repositorySearch = str_replace('/', "\\", $repositorySearch);
            File::put($mod . DIRECTORY_SEPARATOR . 'Models' . DIRECTORY_SEPARATOR . $package . '.php', $model);
            File::put($mod . DIRECTORY_SEPARATOR . 'Controllers' . DIRECTORY_SEPARATOR . $package . 'Controller.php', $controller);
            File::put($mod . DIRECTORY_SEPARATOR . 'Repositories' . DIRECTORY_SEPARATOR . $package . 'Repository.php', $repository);
            File::put($mod . DIRECTORY_SEPARATOR . 'Repositories' . DIRECTORY_SEPARATOR . $package . 'SearchRepository.php', $repositorySearch);
            //
            $this->info('The module ' . $package . ' has created!');
            $this->info('check in ' . $mod);
        } else {
            $this->info('The package ' . $package . ' already exists!');
        }

        if ($route) {
            $base = '\App\\' . $module . '\\';
            $ctrl = $package . "Controller";
            // Criando rotas
            $path_route = base_path() . DIRECTORY_SEPARATOR . 'routes' . DIRECTORY_SEPARATOR . 'api.php';
            $base_package = str_replace('/', "\\", $base . $package);
            $routes =
                '
/**
* Module ' . $package . '
*/
Route::apiResource("' . $route . '","' . $base_package . '\Controllers\\' . $ctrl . '");';
            //
            File::append($path_route, $routes);
            $this->info('Routes created!');
        }
    }

    private function findModels($module, $field, $all = false)
    {
        if (!$all) {
            return false;
        }

        $sep = DIRECTORY_SEPARATOR;
        if (substr($field, -3) != '_id') {
            return false;
        }
        $table = str_replace('_id', '', $field);
        $root = app_path() . $sep;

        $words = str_replace(' ', '', ucwords(str_replace('_', ' ', $table)));
        $packagePath = $root . $module  . $sep . $words;
        if (is_dir($packagePath)) {
            return
                '
    public function ' . $table . '(){
        return $this->hasMany(' . $sep . 'App' . $sep . $module . $sep . $words . $sep . 'Models' . $sep . $words . '::class);
    }';
        }
        return false;
    }

    private function setPackage($table)
    {
        $package = ucfirst($table);
        if (substr_count($table, '_')) {
            $split = explode('_', $table);
            $package = '';
            foreach ($split as $key => $value) {
                $package .= ucfirst($value);
            }
        }
        return $package;
    }
}
