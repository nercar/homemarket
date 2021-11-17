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
			case 'monitorlistaDocsweb':
				$sql = "UPDATE BDES_POS.dbo.DBVENTAS_TMP
						SET GRUPOC = 3
						WHERE IDTR NOT IN(SELECT IDTR FROM BDES_POS.dbo.ESVENTAS_TMP)
						AND GRUPOC = 2";
				$sql = $connec->query($sql);
				$sql = "SELECT IDTR,
						(SELECT TOP (1) [TELEFONO] FROM [BDES_POS].[dbo].[ESCLIENTESPOS] WHERE RIF = cab.IDCLIENTE) AS TELEFONO,
						(CONVERT(VARCHAR(10), FECHAHORA, 105)+' '+CONVERT(VARCHAR(5), FECHAHORA, 108)) AS FECHAHORA,
						(CASE WHEN FECHA_PICKING IS NULL THEN '' ELSE 
						(CONVERT(VARCHAR(10), FECHA_PICKING, 105)+' '+CONVERT(VARCHAR(5), FECHA_PICKING, 108)) END) AS FECHA_PICKING,
						(CASE WHEN FECHA_PROCESADO IS NULL THEN '' ELSE 
						(CONVERT(VARCHAR(10), FECHA_PROCESADO, 105)+' '+CONVERT(VARCHAR(5), FECHA_PROCESADO, 108)) END) AS FECHA_PROCESADO,
						RAZON, GRUPOC, CREADO_POR, PICKING_POR, PROCESADO_POR, paymentStatus AS FPAGO, paymentModule AS TPAGO,
						(SELECT ROUND(SUM((COALESCE(PRECIOOFERTA, PRECIO)*(1+(PORC/100)))*CANTIDAD),2) FROM BDES_POS.dbo.DBVENTAS_TMP_DET WHERE IDTR = cab.IDTR) AS total,
						montodomicilio, descuentos
						FROM BDES_POS.dbo.DBVENTAS_TMP AS cab
						WHERE GRUPOC != 3
						ORDER BY GRUPOC, cab.FECHAHORA ASC";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$pedidopor = '';
					$pickingpor = '';
					$procesadopor = '';
					if($row['GRUPOC']==0) {
						$pedidopor = '<i class="fas fa-donate fa-2x mr-1 mt-1 mb-auto float-left"></i>'.
									 '<span class="mbadge m-0 p-0 text-left"><b>Pedido x: </b><br>'.$row['CREADO_POR'].'</span>';
					}
					if($row['GRUPOC']==1) {
						$pickingpor = '<i class="fas fa-cart-arrow-down fa-2x mr-1 mt-auto mb-auto float-left"></i>'.
									  '<span class="mbadge m-0 p-0 text-left"><b>Picking x: </b><br>'.$row['PICKING_POR'].'</span>';
					}
					if($row['GRUPOC']==2) {
						$procesadopor = '<i class="fas fa-cash-register fa-2x mr-1 mt-auto mb-auto float-left"></i>'.
										'<span class="mbadge m-0 p-0 text-left"><b>Procesado x: </b><br>'.$row['PROCESADO_POR'].'</span>';
					}
					$datos[] = [
						'nrodoc'         => $row['IDTR'],
						'nombre'  		 => $row['RAZON'].'<br><i class="fas fa-phone"></i> '.$row['TELEFONO'],
						'grupoc'         => $row['GRUPOC'],
						'fechapedido'    => $row['FECHAHORA'],
						'fechapicking'   => $row['FECHA_PICKING'],
						'fechaprocesado' => $row['FECHA_PROCESADO'],
						'pedidopor'      => $pedidopor,
						'pickingpor'     => $pickingpor,
						'procesadopor'   => $procesadopor,
						'fpago'          => ($row['FPAGO']==21?
											'<div class="w-100 text-center"><img height="35px" class="drop" src="dist/img/paguelofacil.png" title="'.$row['TPAGO'].'"></div>':
						                    ($row['FPAGO']==20?
											'<div class="w-100 text-center"><img height="35px" class="drop" src="dist/img/instapago.png" title="'.$row['TPAGO'].'"></div>':
											($row['FPAGO']==15?
											'<div class="w-100 text-center"><img height="35px" class="drop" src="dist/img/datafono.png" title="'.$row['TPAGO'].'"></div>':
											($row['FPAGO']==14?
											'<div class="w-100 text-center"><img height="35px" class="drop" src="dist/img/monedas.png" title="'.$row['TPAGO'].'"></div>':
											'<div class="w-100 text-center"></div>')))),
						'monto'			 => $row['total']+$row['montodomicilio']-$row['descuentos'],
					];
				}
				echo json_encode(array('data' => $datos));
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