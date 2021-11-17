<?php
	/**
	* Permite obtener los datos de la base de datos y retornarlos
	* en modo json o array
	*/
	try {
		date_default_timezone_set('America/Caracas');
		// Se capturan las opciones por Post
		$opcion = (isset($_POST["opcion"])) ? $_POST["opcion"] : "";
		$fecha  = (isset($_POST["fecha"]) ) ? $_POST["fecha"]  : date("Y-m-d");
		$hora   = (isset($_POST["hora"])  ) ? $_POST["hora"]   : date("H:i:s");
		// id para los filtros en las consultas
		$idpara = (isset($_POST["idpara"])) ? $_POST["idpara"] : '';
		// Se establece la conexion con la BBDD
		$params = parse_ini_file('../../dist/config.ini');
		if ($params === false) {
			// exeption leyen archivo config
			throw new \Exception("Error reading database configuration file");
		}
		// connect to the sql server database
		if($params['instance']!='') {
			$conStr = sprintf("sqlsrv:Server=%s\%s;", $params['host_sql'], $params['instance']);
		} else {
			$conStr = sprintf("sqlsrv:Server=%s,%d;", $params['host_sql'], $params['port_sql']);
		}
		$connec = new \PDO($conStr, $params['user_sql'], $params['password_sql']);
		$host_ppl = $params['host_ppl'];
		$datos = [];
		extract($_POST);
		switch ($opcion) {
			case 'almacenes':
				$sql = "SELECT RIGHT(CODIGO, 2) AS CODIGO, LOCALIDAD, DESCRIPCION FROM BDES.dbo.ESAlmacen";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = $row;
				}
				echo json_encode($datos);
				break;
			case 'kardexDetalle':
				if($codigo!='') {
					$fechas = explode(',', $fechas);
					$fechai = $fechas[0];
					$fechaf = $fechas[1];
					$saldoi = 0;
					$sql = "SELECT SUM(saldo) AS saldoi
							FROM (
								SELECT
									CASE tipo_mov
										WHEN 1 THEN SUM(cantidad)
										WHEN 2 THEN SUM(cantidad)*(-1)
										WHEN 3 THEN SUM(cantidad)*(-1)
										WHEN 4 THEN SUM(cantidad)*(-1)
										WHEN 5 THEN SUM(cantidad)
									END AS saldo
								FROM BDES.dbo.HMKardexDetalle
								WHERE articulo = $codigo AND CAST(fecha AS DATE) < CAST('$fechai' AS DATE)
								GROUP BY tipo_mov
							) AS saldo";
					$sql = $connec->query($sql);
					if(!$sql) print_r($connec->errorInfo());
					else {
						$row = $sql->fetch();
						$saldoi = $row['saldoi']*1;
					}
					$datos[] = [
						'fecha'       => date('d-m-Y', strtotime($fechai)),
						'movimiento'  => 'Saldo Anterior',
						'cantidad'    => $saldoi,
						'documento'   => '',
						'afectado'    => '',
						'almacen'     => '',
						'localidad'   => '',
						'caja'        => '',
						'observacion' => '',
						'tipo'        => '',
					];
					$sql = "SELECT kd.fecha, kd.movimiento, kd.cantidad, kd.documento, kd.afectado,
								kd.almacen, kd.localidad, kd.caja, '' AS observaciones,
								CASE kd.tipo_mov
									WHEN 1 THEN 'Cargo'
									WHEN 2 THEN 'Descargo'
									WHEN 3 THEN 'Venta'
									WHEN 4 THEN 'Entrega'
									WHEN 5 THEN 'Devolución'
									ELSE 'Otros'
								END AS tipo
							FROM BDES.dbo.HMKardexDetalle AS kd
							WHERE kd.articulo = $codigo
							AND CAST(kd.fecha AS DATE)
								BETWEEN CAST('$fechai' AS DATE) AND CAST('$fechaf' AS DATE)
							ORDER BY kd.fecha ASC";
					$sql = $connec->query($sql);
					if(!$sql) print_r($connec->errorInfo());
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[] = [
							'fecha'       => date('d-m-Y h:i a', strtotime($row['fecha'])),
							'movimiento'  => $row['movimiento'],
							'cantidad'    => $row['cantidad']*1,
							'documento'   => $row['documento'],
							'afectado'    => $row['afectado'],
							'almacen'     => $row['almacen'],
							'localidad'   => $row['localidad'],
							'caja'        => $row['caja'],
							'observacion' => $row['observaciones'],
							'tipo'        => $row['tipo'],
						];
					}
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'listaArticulos':
				$and = ($codigo!=''?"AND art.codigo = $codigo":($nombre!=''?"AND art.descripcion LIKE '%$nombre%'":""));
				$sql = "SELECT art.codigo, art.descripcion, COALESCE(exi.almacen, 0) AS cod_almacen,
						COALESCE(exi.permitido, 0) AS permitido, COALESCE(alm.DESCRIPCION, 'Sin Asignar') AS almacen,
						COALESCE((exi.cantidad-exi.comprometida-exi.usada), 0) AS disponible
					FROM BDES.dbo.ESARTICULOS AS art
					LEFT JOIN BDES.dbo.HMKardexExistencias AS exi ON exi.articulo = art.codigo
					LEFT JOIN BDES.dbo.ESAlmacen AS alm ON alm.codigo = exi.almacen
					WHERE art.activo = 1 $and";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$txt = '<button type="button" title="Agregar Artículo" onclick="' .
								" selArt(".
									$row['codigo'].",'".
									$row['descripcion']."',".
									($row['cod_almacen']*1).",".
									($row['disponible']*1).",".
									($row['permitido']*1).")" .
								'" class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
								' style="white-space: normal; line-height: 1;">' . ucwords($row['descripcion']) .
							'</button>';
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => $txt,
						'descriptxt'  => $row['descripcion'],
						'cod_almacen' => $row['cod_almacen'],
						'almacen'     => $row['almacen'],
						'disponible'  => number_format($row['disponible'], 2),
						'permitido'   => number_format($row['permitido'], 2),
					];
				}
				echo json_encode($datos);
				break;
			case 'articulosXCargar':
				$sql = "SELECT hm.codigo, hm.descripcion, hm.almacen AS idalmacen, al.DESCRIPCION AS almacen, hm.cantidad, hm.permitido
						FROM BDES.dbo.HMCargaInventarioTMP AS hm
						INNER JOIN BDES.dbo.ESAlmacen AS al ON al.codigo = hm.almacen
						WHERE hm.usuario = '$usuario'";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = $row;
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'agregarArtTMP':
				$sql = "INSERT INTO BDES.dbo.HMCargaInventarioTMP(usuario, codigo, descripcion, almacen, cantidad, permitido)
						VALUES('$usuario', $codigo, '$descripcion', $almacen, $cantidad, $permitido)";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				else echo 1;
				break;
			case 'modificarArtTMP':
				$sql = "UPDATE BDES.dbo.HMCargaInventarioTMP
						SET almacen = '$almacen', cantidad = $cantidad, permitido = $permitido
						WHERE usuario = '$usuario' AND codigo = $codigo";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				else echo 1;
				break;
			case 'eliminarArtTMP':
				$sql = "DELETE FROM BDES.dbo.HMCargaInventarioTMP WHERE usuario = '$usuario' AND codigo = $codigo";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				else echo 1;
				break;
			case 'cargarExixtencia':
				$sql = "INSERT INTO BDES.dbo.HMKardexDetalle(
							organizacion, almacen, articulo, localidad, fecha, movimiento, tipo_mov, cantidad)
						SELECT $organizacion AS organizacion, hm.almacen, hm.codigo AS articulo, $localidad AS localidad,
							GETDATE() AS fecha, 'Carga de Existencia' AS movimiento, 1 AS tipo_mov, hm.cantidad
						FROM BDES.dbo.HMCargaInventarioTMP AS hm
						WHERE hm.usuario = '$usuario' AND hm.codigo IN($codigo)";
				$sql = $connec->query($sql);
				if(!$sql) { print_r($connec->errorInfo()); echo 0; }
				else {
					$sql = "MERGE INTO BDES.dbo.HMKardexExistencias AS destino
						USING (SELECT $organizacion AS organizacion, hm.almacen, hm.codigo AS articulo, $localidad AS localidad,
								GETDATE() AS fecha, 'Carga de Existencia' AS movimiento, 1 AS tipo_mov, hm.cantidad, hm.permitido
								FROM BDES.dbo.HMCargaInventarioTMP AS hm
								WHERE hm.usuario = '$usuario' AND hm.codigo IN($codigo)) AS origen
						ON destino.articulo = origen.articulo
							AND destino.almacen = origen.almacen
							AND destino.localidad = origen.localidad
						WHEN NOT MATCHED THEN
							INSERT (organizacion, almacen, articulo, localidad, cantidad, permitido, comprometida, usada)
							VALUES (origen.organizacion, origen.almacen, origen.articulo, origen.localidad,
									origen.cantidad, origen.permitido, 0, 0)
						WHEN MATCHED THEN
							UPDATE SET cantidad = destino.cantidad + origen.cantidad, permitido = origen.permitido;";
					$sql = $connec->query($sql);
					if(!$sql) { print_r($connec->errorInfo()); echo 0; }
					else {
						$sql = "DELETE FROM BDES.dbo.HMCargaInventarioTMP WHERE usuario = '$usuario' AND codigo IN($codigo)";
						$sql = $connec->query($sql);
						if(!$sql) { print_r($connec->errorInfo()); echo 0; }
						else { echo 1; }
					}
				}
				break;
			case 'cancelarProceso':
				$sql = "DELETE FROM BDES.dbo.HMCargaInventarioTMP WHERE usuario = '$usuario' AND codigo IN($codigo)";
				$sql = $connec->query($sql);
				if(!$sql) { print_r($connec->errorInfo()); echo 0; }
				else { echo 1; }
			default:
				# code...
				break;
		}
		// Se cierra la conexion
		$connec = null;
	} catch (PDOException $e) {
		echo "Error : " . $e->getMessage() . "<br/>";
		die();
	}
?>