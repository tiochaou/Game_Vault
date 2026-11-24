
<?php

session_start();

// Se já estiver conectado, vai para o menu
if (isset($_SESSION['usuario'])) {
    header("Location: index.php");
    exit();
}

require_once("conexao.php");

$mensagem = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $usuario = trim($_POST["usuario"] ?? "");
    $senha = $_POST["senha"] ?? "";

    if (empty($usuario) || empty($senha)) {

        $mensagem = "Preencha todos os campos.";

    } else {

        // Consulta segura ao banco
        $stmt = $conn->prepare(
            "SELECT id, usuario, senha FROM usuarios WHERE usuario = ?"
        );

        $stmt->bind_param("s", $usuario);
        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows === 1) {

            $user = $resultado->fetch_assoc();

            // Verifica a senha criptografada
            if (password_verify($senha, $user["senha"])) {

                // Protege contra Session Fixation
                session_regenerate_id(true);

                $_SESSION["usuario"] = $user["usuario"];
                $_SESSION["usuario_id"] = $user["id"];

                header("Location: index.php");
                exit();

            } else {

                $mensagem = "Senha incorreta.";

            }

        } else {

            $mensagem = "Usuário não encontrado.";

        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>GameVault | Login</title>

    <link rel="stylesheet" href="styles.css">
</head>

<body class="login-page">

    <div class="login-card">

        <div class="login-header">
            <h1>🎮 GameVault</h1>
            <p>Seu universo de jogos e desenvolvedoras</p>
        </div>

        <?php if (!empty($mensagem)): ?>
            <div class="message error">
                <?php echo htmlspecialchars($mensagem); ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="form-group">
                <label for="usuario">Usuário</label>

                <input
                    type="text"
                    id="usuario"
                    name="usuario"
                    placeholder="Digite seu usuário"
                    required
                    autocomplete="username"
                >
            </div>

            <div class="form-group">
                <label for="senha">Senha</label>

                <input
                    type="password"
                    id="senha"
                    name="senha"
                    placeholder="Digite sua senha"
                    required
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" class="btn-primary">
                Entrar no GameVault
            </button>

        </form>

        <div class="login-hint">
            <strong>Acesso de teste</strong>
            <p>Usuário: admin</p>
            <p>Senha: 123</p>
        </div>

    </div>

</body>
</html>
