<?php
	/**
	* Permite obtener los datos de la base de datos y retornarlos
	* en modo json o array
	*/
	try {
		date_default_timezone_set('America/Caracas');
		// Se establece la conexion con la BD
		$params = parse_ini_file('../../dist/config.ini');
		if ($params === false) {
			// exeption leyen archivo config
			throw new \Exception("Error reading database configuration file");
		}
		// connect to the sql server database
		if($params['instance']!='') {
			$conStr = sprintf("sqlsrv:Server=%s\%s;",
				$params['host_sql'],
				$params['instance']);
		} else {
			$conStr = sprintf("sqlsrv:Server=%s,%d;",
				$params['host_sql'],
				$params['port_sql']);
		}
		$connec = new \PDO($conStr, $params['user_sql'], $params['password_sql']);
		$host_ppl    = $params['host_ppl'];
		extract($_POST);
		$datos = [];
		switch ($opcion) {
			case 'tiendaActiva':
				$sql = "SELECT TOP 1 codigo, nombre FROM BDES.dbo.ESSucursales WHERE activa = 1";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = $row;
				}
				echo json_encode($datos);
				break;
			case 'cabeceraDocumentos':
				if($nrodoc!='') {
					// Se prepara la consulta para los articulos
					$sql = "SELECT SUM(VENDIDO) AS VENDIDO, SUM(ENTREGADO) AS ENTREGADO FROM
							(SELECT COALESCE(SUM(det.CANTIDAD), 0) AS VENDIDO,
								(SELECT COALESCE(MAX(entregado), 0)
								FROM BDES.dbo.HMDespachoDet
								WHERE status > 1 AND codigo = det.articulo
									AND documento = $nrodoc AND localidad = $tienda AND caja = $nrocaja) AS ENTREGADO
							FROM BDES_POS.dbo.ESVENTASPOS_DET AS det
							WHERE det.DOCUMENTO = $nrodoc AND det.LOCALIDAD = $tienda AND det.CAJA = $nrocaja
							GROUP BY det.ARTICULO) AS datos
							HAVING SUM(VENDIDO) - SUM(ENTREGADO) > 0";
					$sql = $connec->query($sql);
					$sql = $sql->fetch();
					$vendido = $sql['VENDIDO']*1;
					$entregado = $sql['ENTREGADO']*1;
					if($vendido > 0 && $entregado < $vendido) {
						$sql = "SELECT TOP 1 cab.DOCUMENTO, cab.CAJA, cab.LOCALIDAD, cab.FECHA AS FECDOC,
									cab.IDCLIENTE, cab.RAZON, cab.VENDEDOR, cli.TELEFONO,
									cab.DIRECCION, COALESCE(dcab.usuario, '') AS USUARIO,
									COALESCE(dcab.fecha, '') AS FECHA, COALESCE(dcab.STATUS, 1) AS STATUS, dcab.id AS IDCAB
								FROM BDES_POS.dbo.ESVENTASPOS cab
								LEFT JOIN BDES_POS.dbo.ESCLIENTESPOS cli ON cli.RIF = cab.IDCLIENTE
								LEFT JOIN BDES.dbo.HMDespachoCab AS dcab ON dcab.documento = cab.DOCUMENTO AND dcab.status = 1
								WHERE cab.VENDEDOR > 3 AND cab.DOCUMENTO = $nrodoc AND cab.LOCALIDAD = $tienda
									AND cab.CAJA = $nrocaja
								GROUP BY cab.DOCUMENTO, cab.CAJA, cab.LOCALIDAD, cab.FECHA, cab.IDCLIENTE, cab.RAZON, cab.VENDEDOR,
									cli.TELEFONO, cab.DIRECCION, dcab.usuario, dcab.fecha, dcab.STATUS, dcab.id";
					} else {
						$sql = "SELECT TOP 1 cab.DOCUMENTO, cab.CAJA, cab.LOCALIDAD, cab.FECHA AS FECDOC,
									cab.IDCLIENTE, cab.RAZON, cab.VENDEDOR, cli.TELEFONO,
									cab.DIRECCION, COALESCE(dcab.usuario, '') AS USUARIO,
									COALESCE(dcab.fecha, '') AS FECHA, dcab.STATUS, dcab.id AS IDCAB
								FROM BDES_POS.dbo.ESVENTASPOS cab
								LEFT JOIN BDES_POS.dbo.ESCLIENTESPOS cli ON cli.RIF = cab.IDCLIENTE
								LEFT JOIN BDES.dbo.HMDespachoCab AS dcab ON dcab.documento = cab.DOCUMENTO
								WHERE dcab.status = 2 AND cab.VENDEDOR > 3 AND cab.DOCUMENTO = $nrodoc
									AND cab.LOCALIDAD = $tienda AND cab.CAJA = $nrocaja
								GROUP BY cab.DOCUMENTO, cab.CAJA, cab.LOCALIDAD, cab.FECHA, cab.IDCLIENTE, cab.RAZON, cab.VENDEDOR,
									cli.TELEFONO, cab.DIRECCION, dcab.usuario, dcab.fecha, dcab.STATUS, dcab.id";
					}
					// Se ejecuta la consulta en la BBDD
					$sql = $connec->query($sql);
					if(!$sql) print_r($connec->errorInfo());
					// Se prepara el array para almacenar los datos obtenidos
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[] = $row;
					}
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'detalleDocumento':
				if($nrodoc!='') {
					$sql = "SELECT art.codigo, CAST(det.vendido AS NUMERIC(18, 0)) AS vendido,
								CAST(det.vendido-det.entregado AS NUMERIC(18, 0)) AS pendiente,
								CAST(det.cantidad AS NUMERIC(18, 0)) AS cantidad,
								art.descripcion AS nombre, det.documento, det.localidad, det.caja, det.id_cab				
							FROM BDES.dbo.HMDespachoDet AS det
							INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = det.codigo
							WHERE det.documento = $nrodoc AND det.localidad = $tienda AND det.caja = $caja
							AND det.status = 1";
					$sql = $connec->query($sql);
					if(!$sql) print_r($connec->errorInfo());
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$datos[] = $row;
					}
				}
				echo json_encode($datos);
				break;
			case 'detalleFactura':
				$sql = "SELECT * FROM
						(SELECT art.codigo AS CODIGO, art.descripcion AS ARTICULO,
							det.DOCUMENTO, det.LOCALIDAD, det.CAJA, det.FECHA,
							ROUND(det.cantidad, 0) AS VENDIDO,
							COALESCE(MAX(detd.entregado), 0) AS ENTREGADO
						FROM BDES_POS.dbo.ESVENTASPOS_DET AS det
						INNER JOIN BDES.dbo.HMKardexExistencias AS exis ON exis.articulo = det.ARTICULO
						INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = exis.articulo
						LEFT JOIN BDES.dbo.HMDespachoDet AS detd ON detd.codigo = exis.articulo
							AND det.DOCUMENTO = detd.documento AND det.CAJA = detd.caja
							AND det.LOCALIDAD = detd.localidad
							WHERE det.DOCUMENTO = $nrodoc AND det.LOCALIDAD = $tienda AND det.CAJA = $caja
						GROUP BY art.codigo, art.descripcion, det.cantidad, det.DOCUMENTO, det.LOCALIDAD,
							det.CAJA, det.FECHA) AS detalle
						WHERE (detalle.VENDIDO - detalle.ENTREGADO > 0)";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$txt = '<button type="button" title="Agregar Artículo" onclick=" '.
								"addarticulo(".$row['DOCUMENTO'].','.$row['CODIGO'].",'".$row['ARTICULO']."','".$row['FECHA']."',".
								round($row['VENDIDO']*1, 0).",".round($row['ENTREGADO']*1, 0).",".$row['LOCALIDAD'].",".$row['CAJA'].')" '.
								'class="btn btn-link m-0 p-0 text-left font-weight-bold" '.
								'style="white-space: normal; line-height: 1;">'.ucwords($row['ARTICULO']).
							'</button>';
					$row['ARTICULO'] = $txt;
					$datos[] = $row;
				}
				echo json_encode($datos);
				break;
			case 'crearDespachoCab':
				$fecdoc = str_replace(' ', 'T', $fecdoc);
				$sql = "MERGE INTO BDES.dbo.HMDespachoCab AS destino
						USING (VALUES('$userid', $nrodoc, $caja, $tienda, '$fecdoc', 1)) AS origen
							(usuario, documento, caja, localidad, fecha_doc, status)
						ON origen.usuario = destino.usuario AND origen.documento = destino.documento
							AND origen.caja = destino.caja AND origen.localidad = destino.localidad
							AND destino.status = 1
						WHEN MATCHED THEN
							UPDATE SET status = origen.status
						WHEN NOT MATCHED THEN
						INSERT (usuario, documento, caja, localidad, fecha_doc)
						VALUES (origen.usuario, origen.documento, origen.caja, origen.localidad, origen.fecha_doc);";
				$sql = $connec->query($sql);
				if($sql) {
					$sql ="SELECT IDENT_CURRENT('BDES.dbo.HMDespachoCab') as id ";
					$sql = $connec->query($sql);
					$sql = $sql->fetch();
					$idc = $sql['id'];
					echo $idc.'¬Creación exitosa';
				} else {
					echo '0¬<b>Error al Crear el Despacho</b><br>'.$connec->errorInfo()[2];
				}
				break;
			case 'agregaArtDesp':
				$sql = "INSERT INTO BDES.dbo.HMDespachoDet
						(id_cab, codigo, vendido, entregado, cantidad, documento, localidad, caja)
						VALUES
						($id_cab, $codigo, $vendido, $entrega, 0, $nrodoc, $tienda, $caja)";
				$sql = $connec->query($sql);
				if($sql) {
					echo '1¬Agregado con Exito';
				} else {
					echo '0¬<b>Error no se actualizó</b><br>'.$connec->errorInfo()[2];
				}
				break;
			case 'delArtDesp':
				$sql = "DELETE FROM BDES.dbo.HMDespachoDet WHERE id_cab = $id_cab AND codigo = $codigo";
				$sql = $connec->query($sql);
				if($sql) {
					$sql = "SELECT COUNT(*) AS filas FROM BDES.dbo.HMDespachoDet WHERE id_cab = $id_cab";
					$sql = $connec->query($sql);
					$sql = $sql->fetch();
					$cnt = $sql['filas'];
					if($cnt == 0) {
						$sql = "DELETE FROM BDES.dbo.HMDespachoCab WHERE id = $id_cab";
						$sql = $connec->query($sql);
						if($sql) {
							echo '1¬Eliminado Correctamente';
						} else {
							echo '0¬'.$connec->errorInfo()[2];
						}
					}
				} else {
					echo '0¬'.$connec->errorInfo()[2];
				}
				break;
			case 'modCantidad':
				// se extraen los valores del parametro idpara
				$params = explode('¬', $idpara);
				// Se modifica el valor para indicar si se envia o no al excel
				$sql = "UPDATE BDES.dbo.HMDespachoDet SET cantidad = $valor, entregado = entregado + ($valor*1)
						WHERE id_cab = $id_cab AND codigo = $codigo";
				$sql = $connec->query($sql);
				if($sql) {
					echo '1¬Modificado con Exito';
				} else {
					echo '0¬<b>Error no se actualizó</b><br>'.$connec->errorInfo()[2];
				}
				break;
			case 'procEntrega':
				// Se modifica el valor para indicar si se envia o no al excel
				$sql = "DELETE FROM BDES.dbo.HMDespachoDet WHERE id_cab = $id_cab AND cantidad = 0;
						UPDATE BDES.dbo.HMDespachoDet SET status = 2 WHERE id_cab = $id_cab;
						UPDATE BDES.dbo.HMDespachoCab SET status = 2 WHERE id = $id_cab;";
				$sql = $connec->query($sql);
				if($sql) {
					$sql = "INSERT INTO BDES.dbo.HMKardexDetalle(
								organizacion, localidad, almacen, articulo, movimiento, tipo_mov,
								cantidad, documento, afectado, caja)
							SELECT $orgaid, cab.localidad,
								(SELECT TOP 1 CAST(CAST(codigo AS VARCHAR)+'02' AS INT)
								FROM BDES.dbo.ESSucursales WHERE activa = 1),
								det.codigo, 'Entrega/Despacho de Productos', 3,
								det.cantidad, cab.id, cab.documento, cab.caja
							FROM BDES.dbo.HMDespachoCab AS cab
							INNER JOIN BDES.dbo.HMDespachoDet AS det ON det.id_cab = cab.id
							WHERE cab.id = $id_cab;";
					$sql = $connec->query($sql);
					if($sql) {
						echo '1¬Modificado con Exito';
					} else {
						echo '0¬<b>Error no se actualizó el kardex</b><br>'.$connec->errorInfo()[2];
					}
				} else {
					echo '0¬<b>Error no se actualizó</b><br>'.$connec->errorInfo()[2];
				}
				break;
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