
<?php

session_start();

// Limpa os dados da sessão
$_SESSION = [];

// Destrói a sessão
session_destroy();

// Volta para o login
header("Location: login.php");
exit();

?>
