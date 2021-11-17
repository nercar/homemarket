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
		$host_ppl    = $params['host_ppl'];
		$datos = [];
		extract($_POST);
		switch ($opcion) {
			case 'inventario':
				$sql = "SELECT exi.articulo, art.descripcion, exi.almacen, exi.permitido,
						(exi.cantidad-exi.comprometida-exi.usada) AS disponible
						FROM BDES.dbo.HMKardexExistencias AS exi
						INNER JOIN BDES.dbo.ESARTICULOS AS art ON art.codigo = exi.articulo
						WHERE art.activo = 1";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'articulo'    => $row['articulo'],
						'descripcion' => $row['descripcion'],
						'almacen'     => $row['almacen'],
						'permitido'   => (int) $row['permitido'],
						'disponible'  => (int) $row['disponible'],
					];
				}
				echo json_encode(array('data' => $datos));
				break;
			case 'listaArticulos':
				$sql = "SELECT art.codigo, art.descripcion, COALESCE(exi.almacen, 0) AS alamcen,
						COALESCE(exi.permitido, 0) AS permitido,
						(exi.cantidad-exi.comprometida-exi.usada) AS disponible
					FROM BDES.dbo.ESARTICULOS AS art
					LEFT JOIN BDES.dbo.HMKardexExistencias AS exi ON exi.articulo = art.codigo
					WHERE activo = 1 AND (descripcion LIKE '%$idpara%' OR CAST(codigo AS VARCHAR) = '$idpara')";
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$txt = '<button type="button" title="Agregar Artículo" onclick="' .
								" addArtInv(".
									$row['codigo'].",'".
									$row['descripcion']."',".
									($row['almacen']*1).",".
									($row['disponible']*1).",".
									($row['permitido']*1).")" .
								'" class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
								' style="white-space: normal; line-height: 1;">' . ucwords($row['descripcion']) .
							'</button>';
					$datos[] = [
						'codigo'      => $row['codigo'],
						'descripcion' => $txt,
						'alamcen'     => $row['alamcen'],
						'disponible'  => $row['disponible'],
						'permitido'   => $row['permitido'],
					];
				}
				echo json_encode($datos);
				break;			
			case 'agregaArtInv':
				$sql = "INSERT INTO BDES.dbo.HMKardexExistencias
						(organizacion, almacen, articulo, localidad, cantidad, comprometida, usada, permitido)
						VALUES
						(0, 0, $codigo, 0, 0, 0, 0, 0)";
				$sql = $connec->query($sql);
				if($sql) {
					echo 1;
				} else {
					echo 0;
					print_r( $connec->errorInfo() );
				}
				break;
			case 'almacenes':
				$sql = "SELECT RIGHT(CODIGO, 2) AS CODIGO, LOCALIDAD, DESCRIPCION FROM BDES.dbo.ESAlmacen";			
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = $row;
				}				
				echo json_encode($datos);
				break;
			case 'guardarArtInv':
				$sql = "UPDATE BDES.dbo.HMKardexExistencias SET
						almacen = $almacen, permitido = $permitido, cantidad = cantidad + $disponible
						WHERE articulo = $codigo";			
				$sql = $connec->query($sql);
				if(!$sql) {
					print_r($connec->errorInfo());
					echo 0;
				} else {
					echo 1;
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