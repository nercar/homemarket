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
			case 'iniciar_sesion':
				extract($_POST);
				if(empty($tusuario) || empty($tclave)){
					header("Location: " . $idpara);
					break;
				}
				$sql = "SELECT us.login, us.descripcion, us.codusuario, us.activo, us.nivel
						FROM BDES.dbo.ESUsuarios AS us
						INNER JOIN BDES.dbo.ESVENDEDORES AS ven ON ven.CODUSUARIO = us.codusuario
						WHERE LOWER(login)=LOWER('$tusuario') AND password = '$tclave'";
				$sql = $connec->query($sql);
				$row = $sql->fetch(\PDO::FETCH_ASSOC);
				if($row) {
					if($row['activo']!=1) {
						session_destroy();
						session_commit();
						session_start();
						session_id($_SESSION['id']);
						session_destroy();
						session_commit();
						session_start();
						$_SESSION['error'] = 2;
						header("Location: " . $idpara);
					} else {
						session_start();
						$_SESSION['id']         = session_id();
						$_SESSION['url']        = $idpara;
						$_SESSION['usuario']    = strtolower($row['login']);
						$_SESSION['nomusuario'] = ucwords(strtolower($row['descripcion']));
						$_SESSION['codusuario'] = $row['codusuario'];
						$_SESSION['nivel']      = $row['nivel'];
						$_SESSION['error']      = 0;
						$_SESSION['nivel']      = $row['nivel'];
						header("Location: " . $idpara . "inicio.php");
					}
				} else {
					session_start();
					session_id($_SESSION['id']);
					session_destroy();
					session_commit();
					session_start();
					$_SESSION['error'] = 1;
					header("Location: " . $idpara);
				}
				break;
			case 'cerrar_sesion':
				session_start();
				session_id($_SESSION['id']);
				session_destroy();
				session_commit();
				header("Location: " . $_SESSION['url']);
				exit();
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