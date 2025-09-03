<?php
include_once("../config.php");

echo "Paso 2 - Iniciando importación desde 2012...\n";
try {
    $conn_guarani3->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $conn_tkn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // ===== VERIFICACIÓN INICIAL =====
    echo "=== VERIFICACIÓN DE TOTALES ===\n";
    
    $sql_verificacion = "SELECT 
        COUNT(*) as total_registros,
        COUNT(DISTINCT c.comision) as total_comisiones,
        COUNT(DISTINCT sd.docente) as total_docentes,
        MIN(EXTRACT(YEAR FROM pl.fecha_inicio_dictado)) as año_minimo,
        MAX(EXTRACT(YEAR FROM pl.fecha_inicio_dictado)) as año_maximo
    FROM negocio.sga_comisiones c
    JOIN negocio.sga_periodos_lectivos pl 
          ON pl.periodo_lectivo = c.periodo_lectivo
    JOIN negocio.sga_periodos p 
          ON p.periodo = pl.periodo
    JOIN negocio.sga_elementos se 
          ON c.elemento = se.elemento
    JOIN negocio.sga_elementos_plan sep 
          ON se.elemento = sep.elemento_plan
    JOIN negocio.sga_planes sp 
          ON sep.plan_version = sp.plan
    JOIN negocio.sga_propuestas sp2 
          ON sp.propuesta = sp2.propuesta
    JOIN negocio.sga_docentes_comision sdc 
          ON c.comision = sdc.comision
    JOIN negocio.sga_docentes sd 
          ON sdc.docente = sd.docente
    JOIN negocio.mdp_personas mp 
          ON sd.persona = mp.persona
    JOIN negocio.mdp_personas_documentos mpd 
          ON sd.persona = mpd.persona
    JOIN negocio.sga_docentes_ra sdr 
          ON sd.docente = sdr.docente
    JOIN negocio.sga_responsables_academicas sra 
          ON sdr.responsable_academica = sra.responsable_academica
    WHERE EXTRACT(YEAR FROM pl.fecha_inicio_dictado) BETWEEN 2012 AND EXTRACT(YEAR FROM CURRENT_DATE)";
    
    $stmt_verificacion = $conn_guarani3->prepare($sql_verificacion);
    $stmt_verificacion->execute();
    $totales = $stmt_verificacion->fetch(PDO::FETCH_ASSOC);
    
    echo "📊 Total de registros esperados: " . number_format($totales['total_registros']) . "\n";
    echo "🏫 Total de comisiones: " . number_format($totales['total_comisiones']) . "\n";
    echo "👨‍🏫 Total de docentes: " . number_format($totales['total_docentes']) . "\n";
    echo "📅 Rango de años: {$totales['año_minimo']} - {$totales['año_maximo']}\n\n";
    
    // ===== CONSULTA PRINCIPAL COMPLETA =====
    $sql_query = "SELECT DISTINCT
        EXTRACT(YEAR FROM pl.fecha_inicio_dictado) AS anio_academico,
        p.nombre AS periodo,
        c.nombre as comision,
        sra.nombre as responsabilidad_academica,
        sp2.nombre as Propuesta,
        se.nombre as elemento,
        se.codigo,
        mpd.nro_documento
    FROM negocio.sga_comisiones c
    JOIN negocio.sga_periodos_lectivos pl 
          ON pl.periodo_lectivo = c.periodo_lectivo
    JOIN negocio.sga_periodos p 
          ON p.periodo = pl.periodo
    JOIN negocio.sga_elementos se 
          ON c.elemento = se.elemento
    JOIN negocio.sga_elementos_plan sep 
          ON se.elemento = sep.elemento_plan
    JOIN negocio.sga_planes sp 
          ON sep.plan_version = sp.plan
    JOIN negocio.sga_propuestas sp2 
          ON sp.propuesta = sp2.propuesta
    JOIN negocio.sga_docentes_comision sdc 
          ON c.comision = sdc.comision
    JOIN negocio.sga_docentes sd 
          ON sdc.docente = sd.docente
    JOIN negocio.mdp_personas mp 
          ON sd.persona = mp.persona
    JOIN negocio.mdp_personas_documentos mpd 
          ON sd.persona = mpd.persona
    JOIN negocio.sga_docentes_ra sdr 
          ON sd.docente = sdr.docente
    JOIN negocio.sga_responsables_academicas sra 
          ON sdr.responsable_academica = sra.responsable_academica
    WHERE EXTRACT(YEAR FROM pl.fecha_inicio_dictado) BETWEEN 2012 AND EXTRACT(YEAR FROM CURRENT_DATE)
    ORDER BY anio_academico, periodo, comision";
    
    // ===== EJECUTAR CONSULTA =====
    echo "Ejecutando consulta completa...\n";
    $stmt_query = $conn_guarani3->prepare($sql_query);
    $stmt_query->execute();
    $resultados = $stmt_query->fetchAll(PDO::FETCH_ASSOC);
    
    $totalRegistros = count($resultados);
    echo "✅ Registros obtenidos: " . number_format($totalRegistros) . "\n";

    // Consultas preparadas para INSERT
    $sql_propuesta = "INSERT INTO propuestas_guarani (cod_prop, nombre_prop, id_persona) VALUES (:cod_prop, :nombre_prop, :id_persona)";
    $sql_comision = "INSERT INTO comisiones_guarani (nombre, id_persona) VALUES (:nombre, :id_persona)";
    $sql_anio = "INSERT INTO anios_guarani (anio_academico, id_persona) VALUES (:anio_academico, :id_persona)";
    $sql_periodo = "INSERT INTO periodos_guarani (nombre, id_persona) VALUES (:nombre, :id_persona)";
    $sql_elemento = "INSERT INTO elementos_guarani (codigo_elemento, nombre, id_persona) VALUES (:codigo, :nombre, :id_persona)";
    $sql_estudiantes = "INSERT INTO estudiantes_guarani (estudiantes, id_comision, id_persona) VALUES (:estudiantes, :id_comision, :id_persona)";

    // Preparar statements
    $stmt_propuesta = $conn_tkn->prepare($sql_propuesta);
    $stmt_comision = $conn_tkn->prepare($sql_comision);
    $stmt_anio = $conn_tkn->prepare($sql_anio);
    $stmt_periodo = $conn_tkn->prepare($sql_periodo);
    $stmt_elemento = $conn_tkn->prepare($sql_elemento);
    $stmt_estudiantes = $conn_tkn->prepare($sql_estudiantes);

    // Función para obtener ID de persona
    function obtenerIdPersona($nro_documento, $conn_tkn) {
        if (empty($nro_documento)) {
            return null;
        }
        
        $sql_check = "SELECT id_persona FROM personas_mapuche WHERE nro_documento = :nro_documento";
        $stmt_check = $conn_tkn->prepare($sql_check);
        $stmt_check->bindValue(':nro_documento', $nro_documento);
        $stmt_check->execute();
        
        $persona = $stmt_check->fetch(PDO::FETCH_ASSOC);
        return $persona ? $persona['id_persona'] : null;
    }

    $importedCount = 0;
    $skippedCount = 0;
    $totalRegistros = count($resultados);
    $inicio = time();

    echo "===========================================================\n";
    echo "Iniciando importación...\n";
    echo "Total de registros a procesar: $totalRegistros\n";
    echo "===========================================================\n\n";

    foreach ($resultados as $fila) {
        $importedCount++;
        $porcentaje = round(($importedCount / $totalRegistros) * 100, 2);
        
        if ($importedCount % 100 === 0 || $importedCount === $totalRegistros) {
            echo "\r🔄 Procesados: $importedCount/$totalRegistros ($porcentaje%) | Saltados: $skippedCount";
        }

        // Obtener datos de la fila con los nombres correctos de columnas
        $responsabilidad_academica = trim($fila['responsabilidad_academica'] ?? '');
        $Propuesta = trim($fila['Propuesta'] ?? '');
        $comision = trim($fila['comision'] ?? '');
        $anio_academico = trim($fila['anio_academico'] ?? '');
        $periodo = trim($fila['periodo'] ?? '');
        $elemento = trim($fila['elemento'] ?? '');
        $codigo = trim($fila['codigo'] ?? '');
        $nro_documento = trim($fila['nro_documento'] ?? '');
        $estudiantes = 0; // Valor por defecto para estudiantes

        // Validar campos obligatorios
        if (empty($nro_documento)) {
            $skippedCount++;
            continue;
        }

        // Obtener ID de persona
        $id_persona = obtenerIdPersona($nro_documento, $conn_tkn);

        if (!$id_persona) {
            $skippedCount++;
            continue;
        }

        try {
            // Insertar en cada tabla con validación de datos
            $stmt_propuesta->bindValue(':cod_prop', !empty($codigo) ? $codigo : 'SIN_CODIGO');
            $stmt_propuesta->bindValue(':nombre_prop', !empty($Propuesta) ? $Propuesta : 'Sin nombre');
            $stmt_propuesta->bindValue(':id_persona', $id_persona, PDO::PARAM_INT);
            $stmt_propuesta->execute();

            $stmt_comision->bindValue(':nombre', !empty($comision) ? $comision : 'Sin nombre');
            $stmt_comision->bindValue(':id_persona', $id_persona, PDO::PARAM_INT);
            $stmt_comision->execute();
            $id_comision = $conn_tkn->lastInsertId();

            $stmt_anio->bindValue(':anio_academico', !empty($anio_academico) ? $anio_academico : 2012, PDO::PARAM_INT);
            $stmt_anio->bindValue(':id_persona', $id_persona, PDO::PARAM_INT);
            $stmt_anio->execute();

            $stmt_periodo->bindValue(':nombre', !empty($periodo) ? $periodo : 'Sin periodo');
            $stmt_periodo->bindValue(':id_persona', $id_persona, PDO::PARAM_INT);
            $stmt_periodo->execute();

            $stmt_elemento->bindValue(':codigo', !empty($codigo) ? $codigo : 'SIN_CODIGO');
            $stmt_elemento->bindValue(':nombre', !empty($elemento) ? $elemento : 'Sin nombre');
            $stmt_elemento->bindValue(':id_persona', $id_persona, PDO::PARAM_INT);
            $stmt_elemento->execute();

            $stmt_estudiantes->bindValue(':estudiantes', $estudiantes, PDO::PARAM_INT);
            $stmt_estudiantes->bindValue(':id_comision', $id_comision, PDO::PARAM_INT);
            $stmt_estudiantes->bindValue(':id_persona', $id_persona, PDO::PARAM_INT);
            $stmt_estudiantes->execute();

        } catch (PDOException $e) {
            echo "\n⚠️ Error al insertar registro $importedCount: " . $e->getMessage() . "\n";
            echo "Datos problemáticos:\n";
            print_r($fila);
            echo "\n";
            $skippedCount++;
            continue;
        }
    }

    $tiempoTotal = time() - $inicio;
    $insertedCount = $importedCount - $skippedCount;
    
    echo "\n\n✅ ¡Procesamiento completado en $tiempoTotal segundos!\n";
    echo "Total de registros procesados: $importedCount\n";
    echo "Registros insertados: $insertedCount\n";
    echo "Registros saltados (sin persona o con error): $skippedCount\n";

} catch (PDOException $e) {
    echo "\n🚨 Error de base de datos: " . $e->getMessage() . "\n";
} catch (Exception $e) {
    echo "\n🚨 Error general: " . $e->getMessage() . "\n";
}