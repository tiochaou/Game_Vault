
<?php
require_once("valida_sessao.php");
require_once("conexao.php");

// Buscar quantidade de desenvolvedoras
$resultadoDev = $conn->query(
    "SELECT COUNT(*) AS total FROM desenvolvedoras"
);
$totalDev = $resultadoDev->fetch_assoc()['total'];

// Buscar quantidade de jogos
$resultadoJogos = $conn->query(
    "SELECT COUNT(*) AS total FROM jogos"
);
$totalJogos = $resultadoJogos->fetch_assoc()['total'];
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>GameVault | Painel Principal</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>

<div class="container">

    <div class="topbar">
        <a href="logout.php" class="btn-back">Sair</a>
    </div>

    <div class="page-header">
        <h1>🎮 GameVault</h1>

        <p>Bem-vindo,<strong><?php echo htmlspecialchars($_SESSION['usuario']);?></strong>!</p>

        <p>Seu universo de jogos e desenvolvedoras.</p>
    </div>

    <div class="menu-grid">

        <div class="menu-card">
            <a href="cadastro_desenvolvedora.php">
                <div class="menu-card-icon">🏢</div>
                <div class="menu-card-title">
                    Cadastrar Desenvolvedoras
                </div>
                <div class="menu-card-desc">
                    Cadastrar e gerenciar empresas de jogos.
                </div>
            </a>
        </div>

        <div class="menu-card">
            <a href="cadastro_jogo.php">
                <div class="menu-card-icon">🎮</div>
                <div class="menu-card-title">
                    Cadastrar Jogos
                </div>
                <div class="menu-card-desc">
                    Adicionar e editar jogos.
                </div>
            </a>
        </div>

        <div class="menu-card">
            <a href="listagem_jogos.php">
                <div class="menu-card-icon">🕹️</div>
                <div class="menu-card-title">
                    Biblioteca de Jogos
                </div>
                <div class="menu-card-desc">
                    Consultar e filtrar jogos cadastrados.
                </div>
            </a>
        </div>

    </div>

    <div class="menu-grid">

        <div class="menu-card">
            <div class="menu-card-icon">🏢</div>
            <div class="menu-card-title">
                <?php echo $totalDev; ?>
            </div>
            <div class="menu-card-desc">
                Desenvolvedoras cadastradas
            </div>
        </div>

        <div class="menu-card">
            <div class="menu-card-icon">🎮</div>
            <div class="menu-card-title">
                <?php echo $totalJogos; ?>
            </div>
            <div class="menu-card-desc">
                Jogos cadastrados
            </div>
        </div>

    </div>

</div>

</body>
</html>
