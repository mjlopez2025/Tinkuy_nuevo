<?php
// Configuración para mostrar todos los errores
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Configuración de conexión al servidor
$config_tinkuy_nuevo = [
    'host'     => 'localhost',
    'port'     => '5432',
    'dbname'   => 'tinkuy_des',
    'user'     => 'postgres',
    'password' => '13082019'
]; 

// Crear string de conexión DSN
$dsn = "pgsql:host={$config_tinkuy_nuevo['host']};port={$config_tinkuy_nuevo['port']};dbname={$config_tinkuy_nuevo['dbname']}";

// Establecer conexión
    $conn_tkn = new PDO($dsn, $config_tinkuy_nuevo['user'], $config_tinkuy_nuevo['password']);
    $conn_tkn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);



//Configuración de conexión a Mapuche
$config_mapuche = [
    'host'     => 'localhost',
    'port'     => '5432',
    'dbname'   => 'mapuche',
    'user'     => 'postgres',
    'password' => '13082019'
]; 

// Crear string de conexión DSN
$dsn = "pgsql:host={$config_mapuche['host']};port={$config_mapuche['port']};dbname={$config_mapuche['dbname']}";

// Establecer conexión
    $conn_m = new PDO($dsn, $config_mapuche['user'], $config_mapuche['password']);
    $conn_m->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);



// Configuración de la conexión a la base de datos Wichi (solo para consulta)
//$config_wichi = [
//    'host'     => '172.16.1.61',
//    'port'     => '5432',
//    'dbname'   => 'siu_wichi',
//    'user'     => 'postgres',
//    'password' => 'postgres',
//    'esquemas' => [
//        'principal' => 'mapuche',  // Para este script
//        'default'   => 'public'    // Para otros scripts
//    ]
//];

//$conn_wichi = new PDO(
//        "pgsql:host={$config_wichi['host']};port={$config_wichi['port']};dbname={$config_wichi['dbname']}",
//        $config_wichi['user'],
//        $config_wichi['password']
//    );



    //Produccion: http://172.16.1.58/Tinkuy/consultas/consultas.php
    // Desarrollo: http://localhost:8000/Tinkuy/consultas/consultas.php

define('BASE_URL', 'http://localhost:8000/consultas/consultas.php');

