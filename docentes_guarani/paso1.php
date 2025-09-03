<?php
include ("../config.php");

echo "=============================================\n";
echo "SCRIPT PARA CREAR TABLAS GUARANI\n";
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
        "propuestas_guarani"
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

    echo "\n⚙️ Creando tablas...\n";

    $createSQL = [
        "CREATE TABLE propuestas_guarani (
            id_propuesta INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            cod_prop VARCHAR(20),
            nombre_prop VARCHAR(100),
            id_persona INT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );",

        "CREATE TABLE comisiones_guarani (
            id_comision INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            nombre VARCHAR(100),
            id_persona INT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );",

        "CREATE TABLE anios_guarani (
            id_anio INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            anio_academico INT,
            id_persona INT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );",

        "CREATE TABLE periodos_guarani (
            id_periodo INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            nombre VARCHAR(100),
            id_persona INT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );",

        "CREATE TABLE elementos_guarani (
            id_elemento INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            codigo_elemento VARCHAR(50),
            nombre VARCHAR(300),
            id_persona INT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );",

        "CREATE TABLE estudiantes_guarani (
            id_estudiante INT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            estudiantes INT,
            id_comision INT REFERENCES comisiones_guarani(id_comision) ON DELETE CASCADE,
            id_persona INT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );"
    ];

    foreach ($createSQL as $index => $sql) {
        $conn_tkn->exec($sql);
        echo "   - Tabla {$tables[count($tables) - 1 - $index]} creada exitosamente\n";
    }

    // Confirmar
    $conn_tkn->commit();

    echo "\n✅ Todas las tablas creadas exitosamente\n\n";
    echo "\n🎉 Proceso completado exitosamente!\n";

} catch (Exception $e) {
    if ($conn_tkn->inTransaction()) {
        $conn_tkn->rollBack();
    }
    echo "\n❌ Error: " . $e->getMessage() . "\n";
}

echo "\nProceso completado.\n";

    echo "Fin del paso 1...\n";
?>
