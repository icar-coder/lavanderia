<?php
require_once __DIR__ . '/config/init.php';
cerrarSesion();
redirigir(ROOT_URL . 'login.php');
