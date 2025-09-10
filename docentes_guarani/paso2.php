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
    JOIN negocio.sga_insc_cursada sic
        ON c.comision = sic.comision
    WHERE EXTRACT(YEAR FROM pl.fecha_inicio_dictado) BETWEEN 2012 AND EXTRACT(YEAR FROM CURRENT_DATE)";
    
    $stmt_verificacion = $conn_guarani3->prepare($sql_verificacion);
    $stmt_verificacion->execute();
    $totales = $stmt_verificacion->fetch(PDO::FETCH_ASSOC);
    
    echo "📊 Total de registros esperados: " . number_format($totales['total_registros']) . "\n";
    echo "🏫 Total de comisiones: " . number_format($totales['total_comisiones']) . "\n";
    echo "👨‍🏫 Total de docentes: " . number_format($totales['total_docentes']) . "\n";
    echo "📅 Rango de años: {$totales['año_minimo']} - {$totales['año_maximo']}\n\n";
    
    // ===== CONSULTA PRINCIPAL CON APROBADOS =====
    $sql_query = "
    WITH aprobados_por_materia AS (
        SELECT
            me.elemento,
            EXTRACT(YEAR FROM a.fecha_generacion) AS anio_aprobacion,
            COUNT(DISTINCT sad.alumno) AS cant_aprobados
        FROM negocio.sga_actas_detalle sad
        JOIN negocio.sga_actas a ON sad.id_acta = a.id_acta
        JOIN negocio.sga_llamados_mesa lm ON a.llamado_mesa = lm.llamado_mesa
        JOIN negocio.sga_mesas_examen me ON lm.mesa_examen = me.mesa_examen
        WHERE sad.resultado = 'A'
        GROUP BY me.elemento, EXTRACT(YEAR FROM a.fecha_generacion)
    )
    SELECT
        sra.nombre as responsabilidad_academica,
        sra.codigo as codigo_responsabilidad,
        sp2.nombre as Propuesta,
        sp2.propuesta as codigo_propuesta,
        c.nombre as comision,
        EXTRACT(YEAR FROM pl.fecha_inicio_dictado) AS anio_academico,
        p.nombre AS periodo,
        se.nombre as actividad,
        se.codigo as codigo_actividad,
        COALESCE(ap.cant_aprobados, 0) AS estudiantes_aprobados,
        mpd.nro_documento as numero_doc
    FROM negocio.sga_comisiones c
    JOIN negocio.sga_periodos_lectivos pl 
        ON pl.periodo_lectivo = c.periodo_lectivo
    JOIN negocio.sga_periodos p 
        ON p.periodo = pl.periodo
    JOIN negocio.sga_elementos se 
        ON c.elemento = se.elemento
    LEFT JOIN aprobados_por_materia ap 
        ON se.elemento = ap.elemento 
        AND EXTRACT(YEAR FROM pl.fecha_inicio_dictado) = ap.anio_aprobacion
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
    WHERE 
        EXTRACT(YEAR FROM pl.fecha_inicio_dictado) BETWEEN 2012 AND EXTRACT(YEAR FROM CURRENT_DATE)
        AND COALESCE(ap.cant_aprobados, 0) > 0
    GROUP BY
        sra.nombre,
        sra.codigo,
        sp2.nombre,
        sp2.propuesta,
        c.nombre,
        EXTRACT(YEAR FROM pl.fecha_inicio_dictado),
        p.nombre,
        se.nombre,
        se.codigo,
        mpd.nro_documento,
        ap.cant_aprobados
    ORDER BY 
        responsabilidad_academica,
        Propuesta,
        comision,
        anio_academico,
        periodo,
        actividad,
        codigo_actividad;
    ";
    
    // ===== EJECUTAR CONSULTA =====
    echo "Ejecutando consulta completa...\n";
    $stmt_query = $conn_guarani3->prepare($sql_query);
    $stmt_query->execute();
    $resultados = $stmt_query->fetchAll(PDO::FETCH_ASSOC);
    
    $totalRegistros = count($resultados);
    echo "✅ Registros obtenidos: " . number_format($totalRegistros) . "\n";

    // ===== CONSULTAS PREPARADAS =====
    echo "\n🔧 Preparando consultas de inserción...\n";

    // 1. DEPARTAMENTOS
    $sql_departamento = "INSERT INTO departamentos_guarani (codigo_departamentos, nombre_departamentos) 
                        VALUES (:codigo, :nombre) 
                        ON CONFLICT (codigo_departamentos) DO NOTHING
                        RETURNING id_departamentos";

    // 2. PROPUESTAS
    $sql_propuesta = "INSERT INTO propuestas_guarani (cod_prop, nombre_prop, id_departamentos, id_persona) 
                        VALUES (:cod_prop, :nombre_prop, :id_departamentos, :id_persona) 
                        RETURNING id_propuesta";

    // 3. AÑOS
    $sql_anio = "INSERT INTO anios_guarani (anio_academico, id_persona) 
                VALUES (:anio_academico, :id_persona) 
                RETURNING id_anio";

    // 4. PERIODOS
    $sql_periodo = "INSERT INTO periodos_guarani (nombre, id_anio, id_persona) 
                    VALUES (:nombre, :id_anio, :id_persona) 
                    RETURNING id_periodo";

    // 5. ELEMENTOS
    $sql_elemento = "INSERT INTO elementos_guarani (codigo_elemento, nombre, id_propuesta, id_persona) 
                    VALUES (:codigo, :nombre, :id_propuesta, :id_persona) 
                    RETURNING id_elemento";

    // 6. COMISIONES
    $sql_comision = "INSERT INTO comisiones_guarani (nombre, id_periodo, id_elemento, id_persona) 
                    VALUES (:nombre, :id_periodo, :id_elemento, :id_persona) 
                    RETURNING id_comision";

    // 7. ESTUDIANTES
    $sql_estudiantes = "INSERT INTO estudiantes_guarani (estudiantes, id_comision, id_persona) 
                        VALUES (:estudiantes, :id_comision, :id_persona)";

    // Preparar statements
    $stmt_departamento = $conn_tkn->prepare($sql_departamento);
    $stmt_propuesta    = $conn_tkn->prepare($sql_propuesta);
    $stmt_anio         = $conn_tkn->prepare($sql_anio);
    $stmt_periodo      = $conn_tkn->prepare($sql_periodo);
    $stmt_elemento     = $conn_tkn->prepare($sql_elemento);
    $stmt_comision     = $conn_tkn->prepare($sql_comision);
    $stmt_estudiantes  = $conn_tkn->prepare($sql_estudiantes);

    // Cache
    $cache_departamentos = [];
    $cache_propuestas = [];
    $cache_anios = [];
    $cache_periodos = [];
    $cache_elementos = [];
    $cache_comisiones = [];

    // Función para buscar persona
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

    // ===== RECORRER RESULTADOS =====
    $importedCount = 0;
    $skippedCount = 0;
    $inicio = time();

    foreach ($resultados as $fila) {
        $importedCount++;

        $responsabilidad_academica = trim($fila['responsabilidad_academica'] ?? '');
        $codigo_responsabilidad    = trim($fila['codigo_responsabilidad'] ?? '');
        $Propuesta                 = trim($fila['Propuesta'] ?? '');
        $codigo_propuesta          = trim($fila['codigo_propuesta'] ?? '');
        $comision                  = trim($fila['comision'] ?? '');
        $anio_academico            = trim($fila['anio_academico'] ?? '');
        $periodo                   = trim($fila['periodo'] ?? '');
        $actividad                 = trim($fila['actividad'] ?? '');
        $codigo_actividad          = trim($fila['codigo_actividad'] ?? '');
        $estudiantes_aprobados     = (int)($fila['estudiantes_aprobados'] ?? 0);
        $nro_documento             = trim($fila['numero_doc'] ?? '');

        if (empty($nro_documento)) {
            $skippedCount++;
            continue;
        }

        $id_persona = obtenerIdPersona($nro_documento, $conn_tkn);
        if (!$id_persona) {
            $skippedCount++;
            continue;
        }

        try {
            // ===== 1. DEPARTAMENTO =====
            $cache_key_dep = $codigo_responsabilidad;
            if (!isset($cache_departamentos[$cache_key_dep])) {
                $stmt_departamento->bindValue(':codigo', $codigo_responsabilidad ?: 'SIN_ID');
                $stmt_departamento->bindValue(':nombre', $responsabilidad_academica ?: 'Sin departamento');
                $stmt_departamento->execute();
                $result = $stmt_departamento->fetch(PDO::FETCH_ASSOC);
                $id_departamentos = $result ? $result['id_departamentos'] : null;
                if ($id_departamentos) {
                    $cache_departamentos[$cache_key_dep] = $id_departamentos;
                    echo "\n✅ Departamento insertado: $responsabilidad_academica";
                }
            } else {
                $id_departamentos = $cache_departamentos[$cache_key_dep];
            }

            // ===== 2. PROPUESTA =====
            $cache_key_prop = $codigo_propuesta . '_' . $id_departamentos;
            if (!isset($cache_propuestas[$cache_key_prop]) && $id_departamentos) {
                $stmt_propuesta->bindValue(':cod_prop', $codigo_propuesta ?: 'SIN_CODIGO');
                $stmt_propuesta->bindValue(':nombre_prop', $Propuesta ?: 'Sin propuesta');
                $stmt_propuesta->bindValue(':id_departamentos', $id_departamentos, PDO::PARAM_INT);
                $stmt_propuesta->bindValue(':id_persona', $id_persona, PDO::PARAM_INT);
                $stmt_propuesta->execute();
                $id_propuesta = $stmt_propuesta->fetch(PDO::FETCH_ASSOC)['id_propuesta'];
                $cache_propuestas[$cache_key_prop] = $id_propuesta;
                echo "\n✅ Propuesta insertada: $Propuesta (Código: $codigo_propuesta)";
            } else {
                $id_propuesta = $cache_propuestas[$cache_key_prop] ?? null;
            }

            // ===== 3. AÑO =====
            $cache_key_anio = $anio_academico . '_' . $id_persona;
            if (!isset($cache_anios[$cache_key_anio])) {
                $stmt_anio->bindValue(':anio_academico', $anio_academico ?: 2012, PDO::PARAM_INT);
                $stmt_anio->bindValue(':id_persona', $id_persona, PDO::PARAM_INT);
                $stmt_anio->execute();
                $id_anio = $stmt_anio->fetch(PDO::FETCH_ASSOC)['id_anio'];
                $cache_anios[$cache_key_anio] = $id_anio;
                echo "\n✅ Año insertado: $anio_academico";
            } else {
                $id_anio = $cache_anios[$cache_key_anio];
            }

            // ===== 4. PERIODO =====
            $cache_key_periodo = $periodo . '_' . $id_anio;
            if (!isset($cache_periodos[$cache_key_periodo])) {
                $stmt_periodo->bindValue(':nombre', $periodo ?: 'Sin periodo');
                $stmt_periodo->bindValue(':id_anio', $id_anio, PDO::PARAM_INT);
                $stmt_periodo->bindValue(':id_persona', $id_persona, PDO::PARAM_INT);
                $stmt_periodo->execute();
                $id_periodo = $stmt_periodo->fetch(PDO::FETCH_ASSOC)['id_periodo'];
                $cache_periodos[$cache_key_periodo] = $id_periodo;
                echo "\n✅ Periodo insertado: $periodo";
            } else {
                $id_periodo = $cache_periodos[$cache_key_periodo];
            }

            // ===== 5. ELEMENTO =====
            $cache_key_elemento = $codigo_actividad . '_' . $id_propuesta;
            if (!isset($cache_elementos[$cache_key_elemento]) && $id_propuesta) {
                $stmt_elemento->bindValue(':codigo', $codigo_actividad ?: 'SIN_CODIGO');
                $stmt_elemento->bindValue(':nombre', $actividad ?: 'Sin actividad');
                $stmt_elemento->bindValue(':id_propuesta', $id_propuesta, PDO::PARAM_INT);
                $stmt_elemento->bindValue(':id_persona', $id_persona, PDO::PARAM_INT);
                $stmt_elemento->execute();
                $id_elemento = $stmt_elemento->fetch(PDO::FETCH_ASSOC)['id_elemento'];
                $cache_elementos[$cache_key_elemento] = $id_elemento;
                echo "\n✅ Elemento insertado: $actividad";
            } else {
                $id_elemento = $cache_elementos[$cache_key_elemento] ?? null;
            }

            // ===== 6. COMISION =====
            $cache_key_comision = $comision . '_' . $id_periodo . '_' . $id_elemento;
            if (!isset($cache_comisiones[$cache_key_comision]) && $id_periodo && $id_elemento) {
                $stmt_comision->bindValue(':nombre', $comision ?: 'Sin comisión');
                $stmt_comision->bindValue(':id_periodo', $id_periodo, PDO::PARAM_INT);
                $stmt_comision->bindValue(':id_elemento', $id_elemento, PDO::PARAM_INT);
                $stmt_comision->bindValue(':id_persona', $id_persona, PDO::PARAM_INT);
                $stmt_comision->execute();
                $id_comision = $stmt_comision->fetch(PDO::FETCH_ASSOC)['id_comision'];
                $cache_comisiones[$cache_key_comision] = $id_comision;
                echo "\n✅ Comisión insertada: $comision";
            } else {
                $id_comision = $cache_comisiones[$cache_key_comision] ?? null;
            }

            // ===== 7. ESTUDIANTES APROBADOS =====
            if ($id_comision && $estudiantes_aprobados > 0) {
                $stmt_estudiantes->bindValue(':estudiantes', $estudiantes_aprobados, PDO::PARAM_INT);
                $stmt_estudiantes->bindValue(':id_comision', $id_comision, PDO::PARAM_INT);
                $stmt_estudiantes->bindValue(':id_persona', $id_persona, PDO::PARAM_INT);
                $stmt_estudiantes->execute();
                echo "\n✅ Estudiantes aprobados insertados: $estudiantes_aprobados";
            }

        } catch (PDOException $e) {
            echo "\n⚠️ Error en registro $importedCount: " . $e->getMessage();
            $skippedCount++;
            continue;
        }
    }

    $tiempoTotal = time() - $inicio;
    echo "\n\n✅ ¡Proceso completado en $tiempoTotal segundos!";
    echo "\nProcesados: $importedCount";
    echo "\nSaltados: $skippedCount\n";

    echo "\n📊 Resumen:";
    echo "\n - Departamentos: " . count($cache_departamentos);
    echo "\n - Propuestas: " . count($cache_propuestas);
    echo "\n - Años: " . count($cache_anios);
    echo "\n - Periodos: " . count($cache_periodos);
    echo "\n - Elementos: " . count($cache_elementos);
    echo "\n - Comisiones: " . count($cache_comisiones) . "\n";

} catch (PDOException $e) {
    echo "\n🚨 Error DB: " . $e->getMessage();
} catch (Exception $e) {
    echo "\n🚨 Error general: " . $e->getMessage();
}
?>