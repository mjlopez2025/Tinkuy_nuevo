<?php
include_once("../config.php");

// Definir variables principales
$consulta_sql = "";
$totalRegistros = 0;

try {
    // 1. Verificar conexiones
    if (!isset($conn_m) || !($conn_m instanceof PDO)) {
        die("❌ Error: Conexión a Mapuche no está disponible en config.php");
    }
    if (!isset($conn_tkn) || !($conn_tkn instanceof PDO)) {
        die("❌ Error: Conexión a Tinkuy no está disponible en config.php");
    }
    echo "✅ Conexiones cargadas desde config.php\n";

    // 2. Consulta SQL (la misma que tenías)
    echo "⚙️ Ejecutando consulta SQL en mapuche...\n";
    $consulta_sql = "
    SELECT DISTINCT ON (d03.nro_legaj, d03.nro_cargo)
        d03.nro_legaj, 
        d01.desc_appat, 
        d01.desc_nombr, 
        CONCAT(TRIM(d01.desc_appat), ', ', TRIM(d01.desc_nombr)) AS apellidonombre_desc,
        d01.tipo_docum,
        d01.nro_docum,
        dhr2.codc_dedic,           
        dh31.desc_dedic,           
        d03.nro_cargo,
        d03.codc_categ,
        d61.desc_categ,  
        d03.codc_carac,
        d35.desc_grupo,  
        d03.fec_alta, 
        d03.fec_baja,
        d05.nrovarlicencia, 
        d05.fec_hasta,
        d03.codc_uacad,
        uacad.desc_item,
        d03.coddependesemp,
        d36.descdependesemp,  
        d03.tipo_norma,
        d03.tipo_emite,
        d03.fec_norma,
        d03.nro_norma
    FROM mapuche.dh03 d03
    INNER JOIN mapuche.dh01 d01 ON d01.nro_legaj = d03.nro_legaj
    LEFT JOIN mapuche.dhr2 dhr2 ON dhr2.nro_docum = d01.nro_docum   
    LEFT JOIN mapuche.dh31 dh31 ON dh31.codc_dedic = dhr2.codc_dedic    
    LEFT JOIN mapuche.dh05 d05 ON d05.nro_legaj = d03.nro_legaj
    LEFT JOIN mapuche.dh36 d36 ON d36.coddependesemp = d03.coddependesemp
    LEFT JOIN mapuche.dh61 d61 ON d61.codc_categ = d03.codc_categ
    LEFT JOIN mapuche.dh35 d35 ON d35.codc_carac = d03.codc_carac  
    LEFT JOIN mapuche.dh30 uacad ON uacad.desc_abrev = d03.codc_uacad AND uacad.nro_tabla = 13
    WHERE 
        (d03.fec_baja > '2025-06-01' OR d03.fec_baja IS NULL)
        AND (d05.fec_hasta > '2025-06-01' OR d05.fec_hasta IS NULL)
        AND d03.codc_categ NOT IN ('Cat1','Cat2','Cat3','Cat4','Cat5','Cat6','Cat7')
    ORDER BY d03.nro_legaj, d03.nro_cargo, d05.fec_hasta DESC
    ";

    $stmt_mapuche = $conn_m->query($consulta_sql);
    if (!$stmt_mapuche) {
        throw new PDOException("Error al ejecutar consulta SQL: " . implode(" ", $conn_m->errorInfo()));
    }

    // 3. Iniciar transacción principal
    $conn_tkn->beginTransaction();

    // 4. Crear tabla temporal en PostgreSQL para mapeo
    $conn_tkn->exec("
        CREATE TEMP TABLE temp_mapeo_legajos (
            nro_legaj VARCHAR(50) PRIMARY KEY,
            id_persona INTEGER
        )
    ");
    echo "📋 Tabla temporal creada\n";

    // 5. Limpiar todas las tablas de Tinkuy (en orden correcto por FK)
    $tablas = [
        'licencias_mapuche',
        'cargo_mapuche', 
        'caracter_mapuche',
        'categoria_mapuche',
        'dedicacion_mapuche',
        'norma_mapuche',
        'unidad_acad_mapuche',
        'personas_mapuche'
    ];
    
    foreach ($tablas as $tabla) {
        $conn_tkn->exec("TRUNCATE TABLE $tabla RESTART IDENTITY CASCADE");
    }
    echo "🗑️ Tablas de Tinkuy limpiadas\n";

    // 6. Preparar statements para inserción
    $stmtPersona = $conn_tkn->prepare("
        INSERT INTO personas_mapuche 
        (apellido_nombre, tipo_documento, nro_documento, nro_legajo, fecha_alta, fecha_baja)
        VALUES (?, ?, ?, ?, ?, ?) RETURNING id_persona
    ");

    $stmtUnidad = $conn_tkn->prepare("
        INSERT INTO unidad_acad_mapuche (id_persona, cod_auacad, desc_uacad)
        VALUES (?, ?, ?)
    ");

    $stmtNorma = $conn_tkn->prepare("
        INSERT INTO norma_mapuche (id_persona, tipo_norma, tipo_emite, fecha_norma, nro_norma)
        VALUES (?, ?, ?, ?, ?)
    ");

    $stmtLicencia = $conn_tkn->prepare("
        INSERT INTO licencias_mapuche (id_persona, desc_licen, fecha_desde, fecha_hasta)
        VALUES (?, ?, ?, ?)
    ");

    $stmtDedicacion = $conn_tkn->prepare("
        INSERT INTO dedicacion_mapuche (id_persona, cod_dedicacion, desc_dedicacion)
        VALUES (?, ?, ?)
    ");

    $stmtCategoria = $conn_tkn->prepare("
        INSERT INTO categoria_mapuche (id_persona, cod_categ, desc_categ)
        VALUES (?, ?, ?)
    ");

    $stmtCargo = $conn_tkn->prepare("
        INSERT INTO cargo_mapuche (id_persona, nro_cargo)
        VALUES (?, ?)
    ");

    $stmtCaracter = $conn_tkn->prepare("
        INSERT INTO caracter_mapuche (id_persona, cod_caracter, desc_caracter)
        VALUES (?, ?, ?)
    ");

    $stmtMapeo = $conn_tkn->prepare("
        INSERT INTO temp_mapeo_legajos (nro_legaj, id_persona)
        VALUES (?, ?)
    ");

    // 7. Procesar cada registro
    echo "⏳ Procesando registros...\n";
    while ($row = $stmt_mapuche->fetch(PDO::FETCH_ASSOC)) {
        $nro_legaj = $row['nro_legaj'] ?? null;
        
        // 7.1. Insertar en personas_mapuche si no existe en el mapeo
        $stmtCheck = $conn_tkn->prepare("SELECT id_persona FROM temp_mapeo_legajos WHERE nro_legaj = ?");
        $stmtCheck->execute([$nro_legaj]);
        $id_persona = $stmtCheck->fetchColumn();

        if (!$id_persona) {
            // Insertar persona (manejar nulos)
            $stmtPersona->execute([
                $row['apellidonombre_desc'] ?? null,
                $row['tipo_docum'] ?? null,
                $row['nro_docum'] ?? null,
                $nro_legaj,
                $row['fec_alta'] ?? null,
                $row['fec_baja'] ?? null
            ]);
            
            $id_persona = $conn_tkn->lastInsertId();
            
            // Guardar en tabla temporal
            $stmtMapeo->execute([$nro_legaj, $id_persona]);
        }

        // 7.2. Insertar en otras tablas (solo si tienen datos, aceptando nulos)
        // Unidad Académica
        if (!empty($row['codc_uacad']) || !empty($row['desc_item'])) {
            $stmtUnidad->execute([
                $id_persona, 
                $row['codc_uacad'] ?? null, 
                $row['desc_item'] ?? null
            ]);
        }

        // Norma
        if (!empty($row['tipo_norma']) || !empty($row['nro_norma'])) {
            $stmtNorma->execute([
                $id_persona, 
                $row['tipo_norma'] ?? null,
                $row['tipo_emite'] ?? null,
                $row['fec_norma'] ?? null,
                $row['nro_norma'] ?? null
            ]);
        }

        // Licencias
        if (!empty($row['nrovarlicencia']) || !empty($row['fec_hasta'])) {
            $stmtLicencia->execute([
                $id_persona, 
                $row['nrovarlicencia'] ?? null, 
                null, // fecha_desde (no está en tu consulta)
                $row['fec_hasta'] ?? null
            ]);
        }

        // Dedicación
        if (!empty($row['codc_dedic']) || !empty($row['desc_dedic'])) {
            $stmtDedicacion->execute([
                $id_persona, 
                $row['codc_dedic'] ?? null, 
                $row['desc_dedic'] ?? null
            ]);
        }

        // Categoría
        if (!empty($row['codc_categ']) || !empty($row['desc_categ'])) {
            $stmtCategoria->execute([
                $id_persona, 
                $row['codc_categ'] ?? null, 
                $row['desc_categ'] ?? null
            ]);
        }

        // Cargo
        if (!empty($row['nro_cargo'])) {
            $stmtCargo->execute([
                $id_persona, 
                $row['nro_cargo'] ?? null
            ]);
        }

        // Carácter
        if (!empty($row['codc_carac']) || !empty($row['desc_grupo'])) {
            $stmtCaracter->execute([
                $id_persona, 
                $row['codc_carac'] ?? null, 
                $row['desc_grupo'] ?? null
            ]);
        }

        $totalRegistros++;
        
        // Mostrar progreso cada 100 registros
        if ($totalRegistros % 100 === 0) {
            echo "📦 Procesados $totalRegistros registros\n";
        }
    }

    // 8. Commit de la transacción
    $conn_tkn->commit();
    echo "\n✅ Migración completada. Total registros procesados: $totalRegistros\n";

    // 9. Verificación final
    $countPersonas = $conn_tkn->query("SELECT COUNT(*) FROM personas_mapuche")->fetchColumn();
    echo "📊 Personas migradas: $countPersonas\n";
    
    $countCargos = $conn_tkn->query("SELECT COUNT(*) FROM cargo_mapuche")->fetchColumn();
    echo "📊 Cargos migrados: $countCargos\n";

    // 10. La tabla temporal se elimina automáticamente al cerrar la conexión

} catch (PDOException $e) {
    if (isset($conn_tkn) && $conn_tkn->inTransaction()) {
        $conn_tkn->rollBack();
        echo "🔙 Se revirtió la transacción debido a un error\n";
    }
    
    echo "\n❌ Error crítico: " . $e->getMessage() . "\n";
    echo "Código de error: " . $e->getCode() . "\n";
    if (!empty($consulta_sql)) {
        error_log("Consulta fallida:\n" . $consulta_sql);
    }
    exit(1);

} finally {
    // Cerrar statements
    $statements = ['stmtPersona', 'stmtUnidad', 'stmtNorma', 'stmtLicencia', 
                  'stmtDedicacion', 'stmtCategoria', 'stmtCargo', 'stmtCaracter', 'stmtMapeo'];
    foreach ($statements as $stmt) {
        if (isset($$stmt)) $$stmt = null;
    }
    if (isset($stmt_mapuche)) $stmt_mapuche = null;
    
    echo "\n🔌 Script finalizado\n";
}
?>