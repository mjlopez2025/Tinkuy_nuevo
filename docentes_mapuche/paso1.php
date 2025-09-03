<?php
include_once("../config.php");

$dsn = "pgsql:host={$config_tinkuy_nuevo['host']};port={$config_tinkuy_nuevo['port']};dbname={$config_tinkuy_nuevo['dbname']}";

try {
    // Establecer conexión
    $conn_tkn = new PDO($dsn, $config_tinkuy_nuevo['user'], $config_tinkuy_nuevo['password']);
    $conn_tkn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "✅ Conexión exitosa a PostgreSQL\n\n";

    // Iniciar transacción
    $conn_tkn->beginTransaction();

    // Lista de tablas en orden para borrar primero dependientes y al final personas_mapuche
    $tables = [
        "unidad_acad_mapuche",
        "norma_mapuche", 
        "licencias_mapuche",
        "dedicacion_mapuche",
        "categoria_mapuche",
        "cargo_mapuche",
        "caracter_mapuche",
        "personas_mapuche"
    ];

    echo "🗑️ Eliminando tablas existentes (en cascada)...\n";
    foreach ($tables as $tbl) {
        try {
            $dropSQL = "DROP TABLE IF EXISTS {$tbl} CASCADE;";
            $conn_tkn->exec($dropSQL);
            echo "   - Tabla {$tbl} eliminada (si existía)\n";
        } catch (PDOException $e) {
            echo "   ⚠️  Advertencia al eliminar {$tbl}: " . $e->getMessage() . "\n";
        }
    }

    echo "\n⚙️ Creando tablas...\n";

    $createSQL = [
        // personas_mapuche (tabla principal)
        "CREATE TABLE personas_mapuche (
            id_persona BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            apellido_nombre TEXT NOT NULL,
            tipo_documento TEXT,
            nro_documento BIGINT,
            nro_legajo BIGINT,
            nro_cuil VARCHAR(20),
            telefono_celular TEXT,
            correo_electronico TEXT,
            fecha_alta DATE,
            fecha_baja DATE
        );",

        // unidad_acad_mapuche
        "CREATE TABLE unidad_acad_mapuche (
            id_uacad BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            cod_auacad TEXT,
            desc_uacad TEXT,
            id_persona BIGINT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );",

        // norma_mapuche
        "CREATE TABLE norma_mapuche (
            id_norma BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            tipo_norma TEXT,
            tipo_emite TEXT,
            fecha_norma DATE,
            nro_norma TEXT,
            id_persona BIGINT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );",

        // licencias_mapuche
        "CREATE TABLE licencias_mapuche (
            id_licen BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            desc_licen TEXT,
            fecha_desde DATE,
            fecha_hasta DATE,
            id_persona BIGINT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );",

        // dedicacion_mapuche
        "CREATE TABLE dedicacion_mapuche (
            id_dedicacion BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            cod_dedicacion TEXT,
            desc_dedicacion TEXT,
            id_persona BIGINT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );",

        // categoria_mapuche
        "CREATE TABLE categoria_mapuche (
            id_categ BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            cod_categ TEXT,
            desc_categ TEXT,
            id_persona BIGINT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );",

        // cargo_mapuche
        "CREATE TABLE cargo_mapuche (
            id_cargo BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            nro_cargo BIGINT,
            desc_cargo TEXT,
            id_persona BIGINT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );",

        // caracter_mapuche
        "CREATE TABLE caracter_mapuche (
            id_caracter BIGINT GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
            cod_caracter TEXT,
            desc_caracter TEXT,
            id_persona BIGINT REFERENCES personas_mapuche(id_persona) ON DELETE CASCADE
        );"
    ];

    foreach ($createSQL as $index => $sql) {
        try {
            $conn_tkn->exec($sql);
            $tableName = $tables[$index] ?? "Tabla $index";
            echo "   - {$tableName} creada exitosamente\n";
        } catch (PDOException $e) {
            throw new Exception("Error creando tabla: " . $e->getMessage());
        }
    }

    // Confirmar todo
    $conn_tkn->commit();

    echo "\n✅ Todas las tablas creadas exitosamente\n\n";

    // Verificación final
    $stmt = $conn_tkn->query("
        SELECT table_name
        FROM information_schema.tables
        WHERE table_schema = 'public'
        ORDER BY table_name
    ");
    
    $tablesNow = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo "📋 Tablas actuales en la base:\n";
    foreach ($tablesNow as $t) {
        echo "   - $t\n";
    }

    echo "\n🎉 Proceso completado exitosamente!\n";

} catch (Exception $e) {
    if (isset($conn_tkn) && $conn_tkn->inTransaction()) {
        $conn_tkn->rollBack();
    }
    die("\n❌ Error: " . $e->getMessage() . "\n");
}