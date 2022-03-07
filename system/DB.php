<?php

class Connections{

    public static $instance;
    public static $error;
    public static $param = array();

    final public function __construct()
    {

    }

    public static function instance(): ?\PDO
    {
        try {
            self::$instance = new \PDO("mysql:host=".self::$param["host_db"].";dbname=".self::$param["schema_db"].";port=3306",self::$param["user_db"],self::$param["password_db"],
                [
                    \PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8",
                    \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                    \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_OBJ,
                    \PDO::ATTR_CASE => \PDO::CASE_NATURAL
                ]
            );
        } catch (\PDOException $e) {
            self::$error = $e;
            print_r($e);
        }

        return self::$instance;
    }

    public static function error(): ?\PDOException
    {
        return self::$error;
    }
}

class Operaction{

    public function __construct($db)
    {
        Connections::$param = $db;
    }

    public function find($sql){

        $connections = Connections::instance();

        try{
            $state = $connections->prepare($sql);
            $state->execute();

            if(!$state->rowCount()){
                return null;
            }

            return $state->fetchAll(\PDO::FETCH_ASSOC);
        }catch (\Exception $e){
            print_r($e);
            return $e;
        }
    }
}
?>