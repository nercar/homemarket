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
		switch ($opcion) {
			case 'listaDocsweb':
				$idpara = explode(',', $idpara);
				$sql = "UPDATE BDES_POS.dbo.DBVENTAS_TMP
						SET GRUPOC = 3
						WHERE IDTR NOT IN(SELECT IDTR FROM BDES_POS.dbo.ESVENTAS_TMP)
						AND GRUPOC = 2";
				$sql = $connec->query($sql);
				$sql = "SELECT IDTR,
						(CONVERT(VARCHAR(10), FECHAHORA, 105)+' '+CONVERT(VARCHAR(5), FECHAHORA, 108)) AS FECHAHORA, RAZON, GRUPOC, FECHA_PROCESADO, PROCESADO_POR,
						(SELECT COUNT(IDTR) FROM BDES_POS.dbo.DBVENTAS_TMP_DET
							WHERE IMAI = 1 AND IDTR = cab.IDTR) AS items,
						(SELECT SUM(PEDIDO) FROM BDES_POS.dbo.DBVENTAS_TMP_DET
							WHERE IMAI = 1 AND IDTR = cab.IDTR) AS pedidos,
						(SELECT SUM(CANTIDAD) FROM BDES_POS.dbo.DBVENTAS_TMP_DET
							WHERE IMAI = 1 AND IDTR = cab.IDTR) AS unidades,
						(SELECT SUM(ROUND((PRECIO*(1+(PORC/100)))*CANTIDAD, 2)) FROM BDES_POS.dbo.DBVENTAS_TMP_DET
							WHERE IMAI = 1 AND IDTR = cab.IDTR) AS total
						FROM BDES_POS.dbo.DBVENTAS_TMP cab
						WHERE GRUPOC = 3 AND CAST(FECHAHORA AS DATE) BETWEEN '$idpara[0]' AND '$idpara[1]' AND VENDEDOR > 2
						ORDER BY GRUPOC";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$xfactura = '<div class="d-flex w-100 align-items-center">';
					$xfactura.= '<div style="width: 20%"><i class="fas fa-cash-register fa-2x"></i></div>';
					$xfactura.= '<div style="width: 80%" class="mbadge">'.$row['PROCESADO_POR'].'</div>';
					$xfactura.= '</div';
					$xfactura = ($row['GRUPOC']==1) ? $xfactura : '';
					$datos[] = [
						'nrodoc'   => $row['IDTR'],
						'fecha'    => date('d-m-Y H:i', strtotime($row['FECHAHORA'])),
						'nombre'   => '<span class="btn-link p-0 m-0" style="cursor: pointer"'.
									'   onclick="verDetalle('.$row['IDTR'].')" title="Ver Prefactura">'.
										$row['RAZON'].
									' </span>',
						'items'    => $row['items'],
						'pedidos'  => $row['pedidos'],
						'unidades' => $row['unidades'],
						'total'    => $row['total'],
					];
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'datosPreFactura':
				// Se crea el query para obtener los datos
				$sql = "SELECT cab.IDTR,
							(CONVERT(VARCHAR(10), cab.FECHAHORA, 105)+' '+CONVERT(VARCHAR(5), cab.FECHAHORA, 108)) AS FECHA,
							cli.RAZON, cli.DIRECCION, cli.TELEFONO, cli.EMAIL, cab.IDCLIENTE, det.ARTICULO AS material,
							art.descripcion AS ARTICULO, det.PEDIDO, det.CANTIDAD, ROUND(det.PORC, 0) AS PORC,
							ROUND((det.PRECIO*(1+(det.PORC/100))), 2) AS PRECIO,
							ROUND((det.PRECIO*(1+(det.PORC/100)))*det.CANTIDAD, 2) AS TOTAL,
							(det.PRECIO*det.CANTIDAD) AS SUBTOTAL, (det.COSTO*det.CANTIDAD) AS COSTO
						FROM BDES_POS.dbo.DBVENTAS_TMP AS cab
							INNER JOIN BDES_POS.dbo.ESCLIENTESPOS cli ON cli.RIF = cab.IDCLIENTE
							INNER JOIN BDES_POS.dbo.DBVENTAS_TMP_DET det ON det.IDTR = cab.IDTR
							INNER JOIN BDES.dbo.ESARTICULOS art ON art.codigo = det.ARTICULO
						WHERE det.IMAI = 1 AND cab.IDTR = $idpara
						ORDER BY det.LINEA";
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				// Se prepara el array para almacenar los datos obtenidos
				$datos= [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'nrodoc'      => $row['IDTR'],
						'fecha'       => date('d-m-Y', strtotime($row['FECHA'])),
						'hora'        => date('h:i a', strtotime($row['FECHA'])),
						'razon'       => $row['RAZON'],
						'direccion'   => ucwords(strtolower(substr($row['DIRECCION'], 0, 100))),
						'telefono'    => $row['TELEFONO'],
						'email'       => $row['EMAIL'],
						'cliente'     => $row['IDCLIENTE'],
						'material'    => $row['material'],
						'descripcion' => $row['ARTICULO'],
						'pedido'      => $row['PEDIDO']*1,
						'cantidad'    => $row['CANTIDAD']*1,
						'precio'      => $row['PRECIO']*1,
						'impuesto'    => $row['PORC']*1,
						'total'       => $row['TOTAL']*1,
						'subtotal'    => $row['SUBTOTAL']*1,
						'costo'       => $row['COSTO']*1,
					];
				}
				// Se retornan los datos obtenidos
				echo json_encode($datos);
				break;
			case 'procDctoweb':
				// se extraen los valores del parametro idpara
				$params = explode('¬', $idpara);
				// Se modifica el valor para indicar si se envia o no al excel
				$sql = "UPDATE BDES_POS.dbo.DBVENTAS_TMP SET
						GRUPOC = 2, FECHA_PROCESADO = CURRENT_TIMESTAMP,
						PROCESADO_POR = '$params[1]'
						WHERE IDTR = $params[0]";
				$sql = $connec->query($sql);
				if($sql) {
					$sql = "INSERT INTO BDES_POS.dbo.ESVENTAS_TMP
								(IDTR, IDCLIENTE, ACTIVA, LIMITE, FECHAHORA, SUSPENDIDO,
								 PERMITEREG, CAJA, RAZON, DIRECCION, SODEXOACTIVO, pais,
								 estado, ciudad, tipoc, Codigoc, NDE, GRUPOC, email, telefono, VENDEDOR)
							SELECT
							 	IDTR, IDCLIENTE, ACTIVA, LIMITE, FECHAHORA, SUSPENDIDO,
							 	PERMITEREG, CAJA, RAZON, DIRECCION, SODEXOACTIVO, pais,
							 	estado, ciudad, tipoc, Codigoc, 0, GRUPOC, email, telefono, VENDEDOR
							FROM BDES_POS.dbo.DBVENTAS_TMP WHERE IDTR = $params[0];
							INSERT INTO BDES_POS.dbo.ESVENTAS_TMP_DET
								(IDTR, LINEA, ARTICULO, BARRA, PRECIO, COSTO, CANTIDAD,
								 SUBTOTAL, IMPUESTO, PORC, PRECIOREAL, PROMO, PROMODSCTO,
								 IMAI, NDEREL, MONEDA, FACTOR)
							SELECT IDTR, LINEA, ARTICULO, BARRA, PRECIO, COSTO, CANTIDAD,
								 SUBTOTAL, IMPUESTO, PORC, PRECIOREAL, PROMO, PROMODSCTO,
								 IMAI, NDEREL, MONEDA, FACTOR
							FROM BDES_POS.dbo.DBVENTAS_TMP_DET WHERE IDTR = $params[0] AND IMAI = 1";
					$sql = $connec->query($sql);
				}
				if($sql) {
					echo '1';
				} else {
					echo '0';
					print_r( $connec->errorInfo() );
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