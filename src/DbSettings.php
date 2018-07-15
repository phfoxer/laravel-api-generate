<?php
namespace Phfoxer\ApiGenerate;
use DB;
use Illuminate\Support\Facades\Schema;
class DbSettings {

    protected $tables = [];
    protected $safeTables = [];
    public $conn;
    public $table;
    protected $driverName;

    public function getDriver()
    {
        if($this->conn){
            $this->driverName = DB::connection($this->conn)->getDriverName();
        } else {
            $this->driverName = DB::getDriverName();
        }
    }
    // get all tables
    private function mysql(){
        $this->tables = DB::connection($this->conn)->select('SHOW TABLES');
    }
    // get all tables
    private function pgsql(){
       $this->tables = DB::connection($this->conn)->select("SELECT table_schema || '.' || table_name FROM information_schema.tables WHERE table_type = 'BASE TABLE' AND table_schema NOT IN ('pg_catalog', 'information_schema');");
    }
    // get all fields properties
    private function mysqlTable($table){
        $this->table = DB::select("SELECT column_name as name, lower(is_nullable) as nullable  from information_schema.columns where table_name = '".$table."'");
    }
    // get all fields properties
    private function pgsqlTable($table){
        $this->table = DB::select("SELECT column_name as name, lower(is_nullable) as nullable FROM information_schema.columns WHERE table_name='{$table}'");
    }
    // get all fields properties in table
    public function getTableProp($table){
        $this->getDriver();
        $tableData = [];
        switch ($this->driverName) {
            case 'mysql':
               $this->mysqlTable($table);
            break;
            case 'pgsql':
               $this->pgsqlTable($table);
            break;
        }
        $collection = collect($this->table)->map(function($item){
            return (array) $item;
        })->values();

        $grouped = $collection->groupBy('name');

        return $grouped->toArray();

    }
    // get all table in database
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

}
