<?php
include ("../config.php");

echo "=============================================\n";
echo "SCRIPT PARA CREAR TABLAS GUARANI (VERSION CORREGIDA)\n";
echo "=============================================\n";
echo "\nIniciando procesamiento....\n";

$dsn = "pgsql:host={$config_tinkuy_nuevo['host']};port={$config_tinkuy_nuevo['port']};dbname={$config_tinkuy_nuevo['dbname']}";

try {
    $conn_tkn = new PDO($dsn, $config_tinkuy_nuevo['user'], $config_tinkuy_nuevo['password']);
    $conn_tkn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Conexión exitosa a PostgreSQL\n\n";

    // Iniciar transacción
    $conn_tkn->beginTransaction();

    // Tablas a eliminar primero (hijas primero)
    $tables = [
        "estudiantes_guarani",
        "elementos_guarani",
        "periodos_guarani",
        "anios_guarani",
        "comisiones_guarani",
        "propuestas_guarani",
        "departamentos_guarani"
    ];

    echo "🗑️ Eliminando tablas existentes (en cascada)...\n";
    foreach ($tables as $tbl) {
        $dropSQL = "DROP TABLE IF EXISTS {$tbl} CASCADE;";
        $conn_tkn->exec($dropSQL);
        echo "   - Tabla {$tbl} eliminada (si existía)\n";
    }

    // Validar que personas_mapuche exista
    $checkSQL = "SELECT to_regclass('public.personas_mapuche') as existe;";
    $exists = $conn_tkn->query($checkSQL)->fetch(PDO::FETCH_ASSOC);
    if (!$exists['existe']) {
        throw new Exception("❌ La tabla personas_mapuche no existe en esta base de datos.");
    }

    echo "\n⚙️ Creando tablas con relaciones correctas...\n";

    $createSQL = [
    // 1. TABLA DE DEPARTAMENTOS
    "CREATE TABLE departamentos_guarani (
        id_departamentos INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
        codigo_departamentos VARCHAR(20) UNIQUE,  -- ← NUEVO: código del departamento
        nombre_departamentos VARCHAR(100)
    );",

    // 2. TABLA DE PROPUESTAS (relacionada solo con responsabilidad)
    "CREATE TABLE propuestas_guarani (
        id_propuesta INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
        cod_prop VARCHAR(20),
        nombre_prop VARCHAR(100),
        id_departamentos INT REFERENCES departamentos_guarani(id_departamentos) ON DELETE CASCADE,
        id_persona INT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
    );",

    // 3. TABLA DE AÑOS ACADÉMICOS
    "CREATE TABLE anios_guarani (
        id_anio INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
        anio_academico INT,
        id_persona INT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
    );",

    // 4. TABLA DE PERIODOS (relacionada con año)
    "CREATE TABLE periodos_guarani (
        id_periodo INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
        nombre VARCHAR(100),
        id_anio INT REFERENCES anios_guarani(id_anio) ON DELETE CASCADE,
        id_persona INT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
    );",

    // 5. TABLA DE ELEMENTOS/MATERIAS (relacionada con propuesta)
    "CREATE TABLE elementos_guarani (
        id_elemento INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
        codigo_elemento VARCHAR(50),
        nombre VARCHAR(300),
        id_propuesta INT REFERENCES propuestas_guarani(id_propuesta) ON DELETE CASCADE,
        id_persona INT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
    );",

    // 6. TABLA DE COMISIONES (relacionada con periodo y elemento)
    "CREATE TABLE comisiones_guarani (
        id_comision INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
        nombre VARCHAR(100),
        id_periodo INT REFERENCES periodos_guarani(id_periodo) ON DELETE CASCADE,
        id_elemento INT REFERENCES elementos_guarani(id_elemento) ON DELETE CASCADE,
        id_persona INT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
    );",

    // 7. TABLA DE ESTUDIANTES (relacionada con comisión)
    "CREATE TABLE estudiantes_guarani (
        id_estudiante INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
        estudiantes INT,
        id_comision INT REFERENCES comisiones_guarani(id_comision) ON DELETE CASCADE,
        id_persona INT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
    );"
];

    foreach ($createSQL as $sql) {
        $conn_tkn->exec($sql);
        echo "   - Tabla creada exitosamente\n";
    }

    // Confirmar
    $conn_tkn->commit();

    echo "\n✅ Todas las tablas creadas con relaciones correctas\n\n";

} catch (Exception $e) {
    if ($conn_tkn->inTransaction()) {
        $conn_tkn->rollBack();
    }
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}

echo "\nProceso completado.\n";
echo "Fin del paso 1...\n";
?>