<?php
namespace Phfoxer\ApiGenerate;
use DB;
use Illuminate\Support\Facades\Schema;
class DbSettings {

    protected $tables = [];
    protected $safeTables = [];
    protected $conn;
    protected $driverName;
    public function __construct($conn){
        $this->conn = $conn;
    }

    public function getDriver()
    {
        $this->driverName = DB::connection($this->conn)->getDriverName();
    }

    private function mysql(){
        $this->tables = DB::connection($this->conn)->select('SHOW TABLES');
    }
    
    private function pgsql(){
       $this->tables = DB::connection($this->conn)->select("SELECT table_schema || '.' || table_name FROM information_schema.tables WHERE table_type = 'BASE TABLE' AND table_schema NOT IN ('pg_catalog', 'information_schema');");
    }

    public function getTables(){
        $this->getDriver();
        switch ($this->driverName) {
            case 'mysql':
                $this->mysql();
            break;
            case 'pgsql':
                $this->pgsql();
            break;
        }
        return collect($this->tables)->map(function($item){
            $array = (collect((array) $item)->values());
            $table = ($array[0]);
            if(substr_count($table,'.')){
                $table = explode('.',$table)[1];
            }
           return trim($table);
        })->values();
    }

    public function addRelation($table,$module)
    {
        $root = app_path().DIRECTORY_SEPARATOR;
        $app = $root.'Modules'.DIRECTORY_SEPARATOR.$module.DIRECTORY_SEPARATOR;
        $package = ucfirst($table);
        if(substr_count($table, '_')){
            $split = explode('_',$table);
            $package = '';
            foreach ($split as $key => $value) {
                $package .= ucfirst($value);
            }
        }
        $mod = $app.$package;
		File::put($mod.DIRECTORY_SEPARATOR.'Models'.DIRECTORY_SEPARATOR.$package.'.php', $model);
    }
}
