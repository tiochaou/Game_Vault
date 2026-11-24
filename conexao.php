<?php


$servername = "localhost";
$username = "root";
$password = "Senai@118";
$dbname = "gamevault";

$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn ->connect_error) {
    die("Erro na conexão: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");

if (!is_dir(__DIR__ . "/img")) {
    mkdir(__DIR__ . "/img", 0755, true);
}

?>