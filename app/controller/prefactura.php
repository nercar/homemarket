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
		$host_ppl = $params['host_ppl'];
		switch ($opcion) {
			case 'cedulasid':
				$sql = "SELECT id, descripcion, predeterminado
						FROM BDES.dbo.ESCedulasId
						ORDER BY predeterminado DESC";
				$sql = $connec->query($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = $row;
				}
				echo json_encode($datos);
				break;
			case 'buscarTemporal':
				$sql = "SELECT ID AS nrodoc, CONVERT(VARCHAR, FECHAHORA, 103) AS FECHA,
							CONVERT(VARCHAR(5), FECHAHORA, 108) AS HORA,
							RIF, NOMBRE, TELEFONO, CORREO, DIRECCION
						FROM BDES_POS.dbo.DB_TMP_VENTAS
						WHERE RIF = '$idpara' AND ACTIVA = 1";
				$sql = $connec->query($sql);
				$cabecera = $sql->fetch(\PDO::FETCH_ASSOC);
				if( $cabecera ) {
					$recupera = 1;
					$sql = "SELECT det.ID, det.ARTICULO, det.BARRA,
								(CASE WHEN CAST(GETDATE() AS DATE) BETWEEN CAST(art.fechainicio AS DATE) AND CAST(art.fechafinal AS DATE)
									THEN ROUND((art.preciooferta * (1+(art.impuesto/100))), 2)
									ELSE ROUND((art.precio1 * (1+(art.impuesto/100))), 2)
								END) AS PRECIO,
								ROUND(art.costo, 2) AS COSTO,
								det.CANTIDAD, det.PORC,
								ROUND(art.precio1, 2) AS PRECIOREAL,
								det.ACTIVA, art.descripcion AS DESCRIPCION,
								( CASE WHEN art.tipoarticulo != 0 THEN 1 ELSE 0 END ) AS PESADO,
								COALESCE(SUM(d.existlocal), 0) AS EXISTLOCAL,
								art.FACTOR, art.MONEDA
							FROM BDES_POS.dbo.DB_TMP_VENTAS_DET det
							INNER JOIN BDES.dbo.ESARTICULOS art ON art.codigo = det.ARTICULO
							INNER JOIN (SELECT articulo, SUM(cantidad-comprometida-usada+permitido) AS existlocal
										FROM BDES.dbo.HMKardexExistencias
										WHERE RIGHT(almacen, 2) IN('01','02')
										GROUP BY articulo) AS d ON d.articulo = det.ARTICULO
							WHERE ACTIVA = 1 AND ID = " . $cabecera['nrodoc'] ."
							GROUP BY det.ID, det.ARTICULO, det.BARRA, det.CANTIDAD, det.PORC, det.ACTIVA, art.descripcion,
								art.tipoarticulo, art.fechainicio, art.fechafinal, art.precio1, art.preciooferta,
								art.impuesto, art.factor, art.moneda, art.costo";
					$sql = $connec->query($sql);
					if(!$sql) print_r( $connec->errorInfo() );
					$detalle = $sql->fetchAll(\PDO::FETCH_ASSOC);
				} else {
					$recupera = 0;
					$cabecera = [];
					$detalle = [];
				}
				echo json_encode(array('recupera' => $recupera, 'cabecera' => $cabecera, 'detalle' => $detalle));
				break;
			case 'consultarClte':
				$sql = "SELECT RIF, RAZON, DIRECCION, EMAIL, TELEFONO
						FROM BDES_POS.dbo.ESCLIENTESPOS
						WHERE RIF = '$idpara'
						AND activo = 1";
				$sql = $connec->query($sql);
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$datos[] = [
						'rif'       => $row['RIF'],
						'razon'     => $row['RAZON'],
						'telefono'  => $row['TELEFONO'],
						'email'     => $row['EMAIL'],
						'direccion' => $row['DIRECCION'],
					];
				}
				echo json_encode($datos);
				break;
			case 'crearTemporalCab':
				extract($_POST);
				$sql = "INSERT INTO BDES_POS.dbo.DB_TMP_VENTAS
							(FECHAHORA, RIF, NOMBRE, TELEFONO, CORREO, DIRECCION, ACTIVA)
							VALUES
							(CURRENT_TIMESTAMP, '$idclte', '$nomcte', '$telcte', '$emacte', '$dircte', 1)";
				$sql = $connec->query($sql);
				if($sql) {
					$sql  ="SELECT IDENT_CURRENT('BDES_POS.dbo.DB_TMP_VENTAS') as idtr";
					$sql  = $connec->query($sql);
					$sql  = $sql->fetch(\PDO::FETCH_ASSOC);
					$idtr = $sql['idtr'];
				}
				echo $idtr;
				break;
			case 'crearTemporalDet':
				extract($_POST);
				$sql = "INSERT INTO BDES_POS.dbo.DB_TMP_VENTAS_DET
						(ID, ARTICULO, BARRA, PRECIO, COSTO, CANTIDAD, PORC, PRECIOREAL, ACTIVA)
						VALUES
						($idtemp, $codart, '$barart', $pvpart, $cosart, $canart, $impart, $preart, 1)";
				$sql = $connec->query($sql);
				if($sql)
					echo 1;
				else {
					echo 0;
					print_r( $connec->errorInfo() );
				}
				break;
			case 'eliminarTemporal':
				$sql = "UPDATE BDES_POS.dbo.DB_TMP_VENTAS     SET ACTIVA = 0 WHERE ID = $idpara;
						UPDATE BDES_POS.dbo.DB_TMP_VENTAS_DET SET ACTIVA = 0 WHERE ID = $idpara";
				$sql = $connec->query($sql);
				if($sql) {
					echo '1-'.$idpara;
				} else {
					echo 0;
					print_r( $connec->errorInfo() );
				}
				break;
			case 'consultaDispo':
				extract($_POST);
				if($buscaren==2) {
					$sql = "SELECT CODIGO
							FROM BDES.dbo.ESGrupos
							WHERE DESCRIPCION LIKE '%$idpara%'";
					$sql = $connec->query($sql);
					// Se prepara el array para almacenar los datos obtenidos
					$grupos = '';
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$grupos.= $row['CODIGO'] .',';
					}
					$grupos = ($grupos!='') ? substr($grupos, 0, -1) : '99999';
					$sql = "SELECT CODIGO
							FROM BDES.dbo.ESSubgrupos
							WHERE DESCRIPCION LIKE '%$idpara%'";
					$sql = $connec->query($sql);
					// Se prepara el array para almacenar los datos obtenidos
					$subgrupos = '';
					while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
						$subgrupos.= $row['CODIGO'] .',';
					}
					$subgrupos = ($subgrupos!='') ? substr($subgrupos, 0, -1) : '99999';
				}
				if($buscaren==1) {
					if(is_numeric($idpara)) {
						$where = " WHERE (a.codigo = $idpara OR ";
					} else {
						$where = " WHERE (";
					}
					$where.= " a.descripcion LIKE '%$idpara%' OR bar.barra = '$idpara')";
				} else {
					$where = " WHERE a.grupo IN ($grupos) OR a.subgrupo IN ($subgrupos)";
				}
				$sql="SELECT d.material, COALESCE(bar.barra, CAST(d.material AS VARCHAR)) AS barra,
						a.descripcion, (CASE WHEN a.tipoarticulo != 0 THEN 1 ELSE 0 END) AS pesado,
						COALESCE(SUM(d.ExistLocal), 0) AS existlocal, a.impuesto, a.factor,
						a.precio1 AS base, a.costo AS costo,
						(CASE WHEN CAST(GETDATE() AS DATE)
							BETWEEN CAST(a.fechainicio AS DATE) AND CAST(a.fechafinal AS DATE)
						THEN a.preciooferta ELSE 0 END) AS oferta, 60 AS moneda, d.sin_existencia, cantidad_extra
					FROM (SELECT articulo AS material, SUM(cantidad-comprometida-usada) AS existlocal,
							CASE WHEN SUM(permitido) > 0 THEN 1 ELSE 0 END AS sin_existencia,
							SUM(permitido) AS cantidad_extra
							FROM BDES.dbo.HMKardexExistencias
							WHERE RIGHT(almacen, 2) = '02'
							GROUP BY articulo) AS d
					INNER JOIN BDES.dbo.ESARTICULOS a ON a.codigo = d.material AND a.activo = 1
					LEFT JOIN (SELECT escodigo, MAX(DISTINCT barra) AS barra
								FROM BDES.dbo.ESCodigos
								WHERE CAST(escodigo AS VARCHAR) != barra
								GROUP BY escodigo) AS bar ON bar.escodigo = a.codigo
					$where
					GROUP BY d.material, a.descripcion, bar.barra, a.tipoarticulo, d.sin_existencia, d.cantidad_extra,
						a.precio1, a.impuesto, a.preciooferta, a.fechainicio, a.fechafinal, a.costo, a.moneda, a.factor
					HAVING (SUM(d.existlocal)>0 OR (d.sin_existencia=1 AND d.cantidad_extra>0))
					ORDER BY a.descripcion ASC";
				// Se ejecuta la consulta en la BBDD
				$sql = $connec->query($sql);
				if(!$sql) print_r($connec->errorInfo());
				// Se prepara el array para almacenar los datos obtenidos
				$datos = [];
				while ($row = $sql->fetch(\PDO::FETCH_ASSOC)) {
					$precio = round($row['base'] * (1+($row['impuesto']/100)), 2);
					if($row['oferta']>0) {
						$precio = round($row['oferta'] * (1+($row['impuesto']/100)), 2);
					}
					$existlocal = $row['existlocal']*1;
					$extra_exis = ($row['sin_existencia']*1==1?$row['cantidad_extra']*1:0);
					if($precio>0 && ($existlocal>0 || $extra_exis>0)) {
						$txt = '<button type="button" title="Agregar Artículo" onclick="' .
									" addarticulo('" . $row['material'] . "','" . $row['barra'] .
										"','" . $row['descripcion'] . "'," . round($precio, 2)*1 .
										"," . $existlocal . "," . $extra_exis . "," . $row['pesado']*1 .
										"," . round($row['base']*1,2) . "," . round($row['impuesto']*1,2) .
										"," . round($row['costo']*1,2) . "," . $row['moneda'] . "," . $row['factor'] . ")" .
									'" class="btn btn-link m-0 p-0 text-left font-weight-bold" ' .
									' style="white-space: normal; line-height: 1;">' . ucwords($row['descripcion']) .
								'</button>';
						$datos[] = [
							'material'    => $row['material'],
							'barra'       => '<span title="' . $row['material'] . '">' . $row['barra'] . '</span>',
							'descripcion' => $txt,
							'precio'      => number_format($precio, 2),
							'existlocal'  => number_format($existlocal, 2),
							'extra_exis'  => number_format($extra_exis, 2),
							'pesado'      => $row['pesado']*1,
							'nombre'      => $row['descripcion'],
							'cbarra'      => $row['barra'],
							'nprecio'     => round($precio, 2)*1,
							'nexistlocal' => $existlocal,
							'nextra_exis' => $extra_exis,
							'precioreal'  => round($row['base']*1,2),
							'impuesto'    => round($row['impuesto']*1,2),
							'costo'       => round($row['costo']*1,2),
							'moneda'      => $row['moneda']*1,
						];
					}
				}
				echo json_encode($datos);
				break;
			case 'modCantidadTmp':
				$params = explode('¬', $idpara);
				$sql = "UPDATE BDES_POS.dbo.DB_TMP_VENTAS_DET SET
						CANTIDAD = $params[2]
						WHERE ID = $params[0] AND ARTICULO = $params[1]";
				$sql = $connec->query($sql);
				if($sql)
					echo 1;
				else {
					echo 0;
					print_r( $connec->errorInfo() );
				}
				break;
			case 'delArtTmp':
				$idpara = explode('¬', $idpara);
				$sql = "DELETE FROM BDES_POS.dbo.DB_TMP_VENTAS_DET WHERE ID = $idpara[0] AND ARTICULO = $idpara[1]";
				$sql = $connec->query($sql);
				if($sql) {
					echo 1;
				} else {
					echo 0;
					print_r( $connec->errorInfo() );
				}
				break;
			case 'guardarPrefactura':
				extract($_POST);
				$detalle = json_decode($detalle);
				$sql = "SELECT (ValorCorrelativo + 1) AS ValorCorrelativo
						FROM BDES_POS.dbo.ESCORRELATIVOS
						WHERE Correlativo = 'CompraEsperaRem'";
				$sql = $connec->query($sql);
				$idtr = $sql->fetch(\PDO::FETCH_ASSOC);
				$idtr = $idtr['ValorCorrelativo'];
				$sql = "UPDATE BDES_POS.dbo.ESCORRELATIVOS
						SET ValorCorrelativo = $idtr
						WHERE Correlativo = 'CompraEsperaRem'";
				$sql = $connec->query($sql);
				if($sql) {
					$vendedor = str_pad($coduser, 10, '0', STR_PAD_RIGHT).str_pad($idtr, 8, '0', STR_PAD_LEFT);
					$sql = "INSERT INTO BDES_POS.dbo.DBVENTAS_TMP
								(IDTR, IDCLIENTE, ACTIVA, FECHAHORA, CAJA, RAZON, DIRECCION, LIMITE, CREADO_POR, email,
								telefono, GRUPOC, FECHA_PROCESADO, PROCESADO_POR, VENDEDOR)
							VALUES($idtr, '$cedulasid'+'$idclte', 1, CURRENT_TIMESTAMP, 999,
								'$nomclte', '$dirclte', 0, '$usuario', '$emailclte', '$telclte', 2, CURRENT_TIMESTAMP,
								'$usuario', $vendedor);
							INSERT INTO BDES_POS.dbo.ESVENTAS_TMP
								(IDTR, IDCLIENTE, ACTIVA, LIMITE, FECHAHORA, SUSPENDIDO,
								 PERMITEREG, CAJA, RAZON, DIRECCION, SODEXOACTIVO, pais,
								 estado, ciudad, tipoc, Codigoc, NDE, GRUPOC, email, telefono, VENDEDOR)
							SELECT
							 	IDTR, IDCLIENTE, ACTIVA, LIMITE, FECHAHORA, SUSPENDIDO,
							 	PERMITEREG, CAJA, RAZON, DIRECCION, SODEXOACTIVO, pais,
							 	estado, ciudad, tipoc, Codigoc, 0, GRUPOC, email, telefono, VENDEDOR
							FROM BDES_POS.dbo.DBVENTAS_TMP WHERE IDTR = $idtr;";
					$sql = $connec->query($sql);
					if($sql) {
						$sql = "INSERT INTO BDES_POS.dbo.DBVENTAS_TMP_DET
								(IDTR, LINEA, ARTICULO, BARRA, PRECIO, COSTO, CANTIDAD, PEDIDO, SUBTOTAL, IMPUESTO,
								PORC, PRECIOREAL, PROMO, PROMODSCTO, PRECIOOFERTA, MONEDA, FACTOR, IMAI)
								VALUES";
						$i = 1;
						foreach ($detalle as $value) {
							$sqlf = "SELECT FACTOR FROM BDES.dbo.ESFormasPago_FactorC WHERE CODIGO = " . $value->moneda;
							$sqlf = $connec->query($sqlf);
							$factor = $sqlf->fetch(\PDO::FETCH_ASSOC);
							$factor = $factor['FACTOR'];
							$precio = $value->precio/(1+($value->impuesto/100));
							$sql .= "($idtr, $i, '$value->codigo', '$value->barra', $precio*$factor,
									  $value->costo*$factor, $value->cantidad, $value->cantidad, 0, 0, $value->impuesto,
									  $value->precioreal*$factor, 0, 0, $precio*$factor, $value->moneda, $factor, 1),";
							$i++;
						}
						$sql = substr($sql, 0, -1).';'.chr(13);
						$sql.= "INSERT INTO BDES_POS.dbo.ESVENTAS_TMP_DET
									(IDTR, LINEA, ARTICULO, BARRA, PRECIO, COSTO, CANTIDAD,
									 SUBTOTAL, IMPUESTO, PORC, PRECIOREAL, PROMO, PROMODSCTO,
									 IMAI, NDEREL, MONEDA, FACTOR)
								SELECT IDTR, LINEA, ARTICULO, BARRA, PRECIO, COSTO, CANTIDAD,
									 SUBTOTAL, IMPUESTO, PORC, PRECIOREAL, PROMO, PROMODSCTO,
									 IMAI, NDEREL, MONEDA, FACTOR
								FROM BDES_POS.dbo.DBVENTAS_TMP_DET WHERE IDTR = $idtr AND IMAI = 1;
								UPDATE exi SET exi.comprometida = exi.comprometida + ped.CANTIDAD
								FROM  BDES.dbo.BIKardexExistencias AS exi
								INNER JOIN (SELECT ARTICULO, SUM(CANTIDAD) AS CANTIDAD FROM
								BDES_POS.dbo.DBVENTAS_TMP_DET
								WHERE IDTR = $idtr AND IMAI = 1
								GROUP BY IDTR, IMAI, ARTICULO) AS ped ON exi.articulo = ped.ARTICULO
								WHERE RIGHT(exi.almacen, 2) = '02';";
						$sql = $connec->query($sql);
						if(!$sql) {
							print_r( $connec->errorInfo() );
						}
					} else {
						print_r( $connec->errorInfo() );
					}
					$sql = "SELECT COUNT(*) AS cuenta FROM BDES_POS.dbo.ESCLIENTESPOS
							WHERE RIF = '$cedulasid'+'$idclte'
							AND ACTIVO = 1";
					$sql = $connec->query($sql);
					$sql = $sql->fetch(\PDO::FETCH_ASSOC);
					if($sql['cuenta']==0) {
						$sql = "SELECT (ValorCorrelativo + 1) AS ValorCorrelativo
								FROM BDES_POS.dbo.ESCORRELATIVOS
								WHERE Correlativo = 'ClientePos'";
						$sql = $connec->query($sql);
						$codclte = $sql->fetch(\PDO::FETCH_ASSOC);
						$codclte = $codclte['ValorCorrelativo'];
						$sql = "UPDATE BDES.dbo.ESCorrelativos
								SET ValorCorrelativo = $codclte
								WHERE Correlativo = 'Cliente'";
						$sql = $connec->query($sql);
						if($sql) {
							$sql = "INSERT INTO BDES_POS.dbo.ESCLIENTESPOS
									(RIF, RAZON, DIRECCION, ACTIVO, IDTR, ACTUALIZO, EMAIL, TELEFONO)
									VALUES('$cedulasid'+'$idclte', '$nomclte', '$dirclte', 1, $codclte,
									1, '$emailclte', '$telclte')";
							$sql = $connec->query($sql);
						} else {
							print_r( $connec->errorInfo() );
						}
					} else {
						$sql = "UPDATE BDES_POS.dbo.ESCLIENTESPOS
								SET RAZON = '$nomclte', DIRECCION = '$dirclte', ACTIVO = 1,
								EMAIL = '$emailclte', TELEFONO = '$telclte'
								WHERE RIF = '$cedulasid'+'$idclte'";
						$sql = $connec->query($sql);
					}
				} else {
					print_r( $connec->errorInfo() );
				}
				if($sql) {
					echo $idtr;
				} else {
					echo 0;
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