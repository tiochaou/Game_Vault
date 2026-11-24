
<?php

require_once("valida_sessao.php");
require_once("conexao.php");

$mensagem = "";
$tipo_mensagem = "";

// Proteção CSRF
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

// Função para exibir textos com segurança
function escaparDev($valor) {
    return htmlspecialchars($valor ?? "", ENT_QUOTES, "UTF-8");
}

// Função para salvar imagens
function salvarImagemDev($arquivo) {

    if ($arquivo["error"] !== UPLOAD_ERR_OK) {
        return ["erro" => "Erro ao enviar a imagem."];
    }

    if ($arquivo["size"] > 5 * 1024 * 1024) {
        return ["erro" => "A imagem deve ter no máximo 5 MB."];
    }

    $informacoes = @getimagesize($arquivo["tmp_name"]);

    if ($informacoes === false) {
        return ["erro" => "O arquivo enviado não é uma imagem válida."];
    }

    $mime = $informacoes["mime"];

    $formatos = [
        "image/jpeg" => "jpg",
        "image/png"  => "png",
        "image/webp" => "webp"
    ];

    if (!isset($formatos[$mime])) {
        return ["erro" => "Envie uma imagem JPG, PNG ou WEBP."];
    }

    $nome = bin2hex(random_bytes(16)) . "." . $formatos[$mime];

    $pasta = __DIR__ . "/img/";

    if (!is_dir($pasta)) {
        mkdir($pasta, 0755, true);
    }

    $destino = $pasta . $nome;

    if (!move_uploaded_file($arquivo["tmp_name"], $destino)) {
        return ["erro" => "Não foi possível salvar a imagem."];
    }

    return ["caminho" => "img/" . $nome];
}

// =====================================
// PROCESSAMENTO DOS FORMULÁRIOS
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )) {
        $mensagem = "Solicitação inválida. Atualize a página.";
        $tipo_mensagem = "error";

    } else {

        $acao = $_POST["acao"] ?? "";

        // =====================================
        // CADASTRAR OU EDITAR
        // =====================================

        if ($acao === "salvar") {

            $id = (int)($_POST["id"] ?? 0);

            $nome = trim($_POST["nome"] ?? "");
            $pais = trim($_POST["pais"] ?? "");
            $ano = trim($_POST["ano_fundacao"] ?? "");
            $website = trim($_POST["website"] ?? "");
            $descricao = trim($_POST["descricao"] ?? "");

            $imagem = "";

            if ($nome === "") {

                $mensagem = "Informe o nome da desenvolvedora.";
                $tipo_mensagem = "error";

            } elseif (
                $ano !== "" &&
                (!ctype_digit($ano) || (int)$ano < 1900 || (int)$ano > (int)date("Y"))
            ) {

                $mensagem = "Informe um ano de fundação válido.";
                $tipo_mensagem = "error";

            } elseif (
                $website !== "" &&
                (!filter_var($website, FILTER_VALIDATE_URL) ||
                 !in_array(parse_url($website, PHP_URL_SCHEME), ["http", "https"], true))
            ) {

                $mensagem = "Informe um endereço de website válido.";
                $tipo_mensagem = "error";

            } else {

                // Busca os dados atuais quando estiver editando
                $atual = null;

                if ($id > 0) {

                    $consulta = $conn->prepare(
                        "SELECT imagem FROM desenvolvedoras WHERE id = ?"
                    );

                    $consulta->bind_param("i", $id);
                    $consulta->execute();

                    $atual = $consulta->get_result()->fetch_assoc();

                    $consulta->close();

                    if (!$atual) {
                        $mensagem = "Desenvolvedora não encontrada.";
                        $tipo_mensagem = "error";
                    } else {
                        $imagem = $atual["imagem"] ?? "";
                    }
                }

                // Upload opcional de imagem
                if (
                    $tipo_mensagem !== "error" &&
                    isset($_FILES["imagem"]) &&
                    $_FILES["imagem"]["error"] !== UPLOAD_ERR_NO_FILE
                ) {

                    $upload = salvarImagemDev($_FILES["imagem"]);

                    if (isset($upload["erro"])) {

                        $mensagem = $upload["erro"];
                        $tipo_mensagem = "error";

                    } else {

                        $imagem = $upload["caminho"];
                    }
                }

                if ($tipo_mensagem !== "error") {

                    if ($id > 0) {

                        $sql = "UPDATE desenvolvedoras
                                SET nome = ?, pais = ?,
                                    ano_fundacao = NULLIF(?, ''),
                                    website = ?, descricao = ?,
                                    imagem = ?
                                WHERE id = ?";

                        $stmt = $conn->prepare($sql);

                        $stmt->bind_param(
                            "ssssssi",
                            $nome,
                            $pais,
                            $ano,
                            $website,
                            $descricao,
                            $imagem,
                            $id
                        );

                        $operacao = "atualizada";

                    } else {

                        $sql = "INSERT INTO desenvolvedoras
                                (nome, pais, ano_fundacao, website, descricao, imagem)
                                VALUES (?, ?, NULLIF(?, ''), ?, ?, ?)";

                        $stmt = $conn->prepare($sql);

                        $stmt->bind_param(
                            "ssssss",
                            $nome,
                            $pais,
                            $ano,
                            $website,
                            $descricao,
                            $imagem
                        );

                        $operacao = "cadastrada";
                    }

                    if ($stmt->execute()) {

                        $mensagem = "Desenvolvedora " . $operacao . " com sucesso!";
                        $tipo_mensagem = "success";

                        // Remove imagem antiga somente após salvar a nova
                        if (
                            $id > 0 &&
                            !empty($upload["caminho"]) &&
                            !empty($atual["imagem"])
                        ) {
                            $antiga = __DIR__ . "/" . $atual["imagem"];

                            if (is_file($antiga)) {
                                unlink($antiga);
                            }
                        }

                    } else {

                        $mensagem = "Erro ao salvar os dados.";
                        $tipo_mensagem = "error";

                        // Remove o arquivo novo caso o cadastro falhe
                        if (!empty($upload["caminho"])) {
                            $novo = __DIR__ . "/" . $upload["caminho"];

                            if (is_file($novo)) {
                                unlink($novo);
                            }
                        }
                    }

                    $stmt->close();
                }
            }
        }

        // =====================================
        // EXCLUIR DESENVOLVEDORA
        // =====================================

        if ($acao === "excluir") {

            $id = (int)($_POST["id"] ?? 0);

            // Verifica se existem jogos vinculados
            $consulta = $conn->prepare(
                "SELECT COUNT(*) AS total
                 FROM jogos
                 WHERE desenvolvedora_id = ?"
            );

            $consulta->bind_param("i", $id);
            $consulta->execute();

            $total = $consulta->get_result()->fetch_assoc()["total"];

            $consulta->close();

            if ($total > 0) {

                $mensagem = "Não é possível excluir: esta desenvolvedora possui jogos cadastrados.";
                $tipo_mensagem = "error";

            } else {

                $consulta = $conn->prepare(
                    "SELECT imagem FROM desenvolvedoras WHERE id = ?"
                );

                $consulta->bind_param("i", $id);
                $consulta->execute();

                $dados = $consulta->get_result()->fetch_assoc();

                $consulta->close();

                $stmt = $conn->prepare(
                    "DELETE FROM desenvolvedoras WHERE id = ?"
                );

                $stmt->bind_param("i", $id);

                if ($stmt->execute() && $stmt->affected_rows > 0) {

                    $mensagem = "Desenvolvedora excluída com sucesso!";
                    $tipo_mensagem = "success";

                    if (!empty($dados["imagem"])) {

                        $arquivo = __DIR__ . "/" . $dados["imagem"];

                        if (is_file($arquivo)) {
                            unlink($arquivo);
                        }
                    }

                } else {

                    $mensagem = "Não foi possível excluir a desenvolvedora.";
                    $tipo_mensagem = "error";
                }

                $stmt->close();
            }
        }
    }
}

// =====================================
// BUSCAR DADOS PARA EDIÇÃO
// =====================================

$desenvolvedora = null;

if (isset($_GET["edit_id"])) {

    $id = (int)$_GET["edit_id"];

    $stmt = $conn->prepare(
        "SELECT * FROM desenvolvedoras WHERE id = ?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $desenvolvedora = $stmt->get_result()->fetch_assoc();

    $stmt->close();
}

// =====================================
// LISTAR DESENVOLVEDORAS
// =====================================

$desenvolvedoras = $conn->query(
    "SELECT * FROM desenvolvedoras ORDER BY nome"
);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Desenvolvedoras | GameVault</title>

    <link rel="stylesheet" href="styles.css">
</head>

<body>

<div class="container">

    <div class="page-header-bar">

        <h2>
            🏢
            <?php echo $desenvolvedora ? "Editar Desenvolvedora" : "Desenvolvedoras"; ?>
        </h2>

        <div class="header-actions">

            <a href="index.php" class="btn-back">
                Voltar ao Menu
            </a>

        </div>

    </div>

    <?php if ($mensagem !== ""): ?>

        <div class="message <?php echo escaparDev($tipo_mensagem); ?>">
            <?php echo escaparDev($mensagem); ?>
        </div>

    <?php endif; ?>

    <!-- FORMULÁRIO -->

    <h3>
        <?php echo $desenvolvedora ? "Editar informações" : "Cadastrar nova desenvolvedora"; ?>
    </h3>

    <form method="POST" enctype="multipart/form-data">

        <input type="hidden" name="acao" value="salvar">

        <input type="hidden" name="csrf_token"
               value="<?php echo escaparDev($_SESSION["csrf_token"]); ?>">

        <input type="hidden" name="id"
               value="<?php echo $desenvolvedora["id"] ?? ""; ?>">

        <div class="form-grid">

            <div class="form-group">

                <label for="nome">Nome da desenvolvedora *</label>

                <input
                    type="text"
                    id="nome"
                    name="nome"
                    maxlength="100"
                    value="<?php echo escaparDev($desenvolvedora["nome"] ?? ""); ?>"
                    placeholder="Ex: Rockstar Games"
                    required
                >

            </div>

            <div class="form-group">

                <label for="pais">País de origem</label>

                <input
                    type="text"
                    id="pais"
                    name="pais"
                    maxlength="60"
                    value="<?php echo escaparDev($desenvolvedora["pais"] ?? ""); ?>"
                    placeholder="Ex: Estados Unidos"
                >

            </div>

            <div class="form-group">

                <label for="ano_fundacao">Ano de fundação</label>

                <input
                    type="number"
                    id="ano_fundacao"
                    name="ano_fundacao"
                    min="1900"
                    max="<?php echo date("Y"); ?>"
                    value="<?php echo escaparDev($desenvolvedora["ano_fundacao"] ?? ""); ?>"
                    placeholder="Ex: 1998"
                >

            </div>

            <div class="form-group">

                <label for="website">Website oficial</label>

                <input
                    type="url"
                    id="website"
                    name="website"
                    value="<?php echo escaparDev($desenvolvedora["website"] ?? ""); ?>"
                    placeholder="https://www.exemplo.com"
                >

            </div>

            <div class="form-group full">

                <label for="descricao">Descrição</label>

                <textarea
                    id="descricao"
                    name="descricao"
                    placeholder="Conte um pouco sobre a desenvolvedora..."
                ><?php echo escaparDev($desenvolvedora["descricao"] ?? ""); ?></textarea>

            </div>

            <div class="form-group full">

                <label for="imagem">Logotipo ou imagem da desenvolvedora</label>

                <input
                    type="file"
                    id="imagem"
                    name="imagem"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <small>JPG, PNG ou WEBP. Máximo de 5 MB.</small>

                <?php if (!empty($desenvolvedora["imagem"])): ?>

                    <div class="current-image">

                        <img
                            src="<?php echo escaparDev($desenvolvedora["imagem"]); ?>"
                            alt="Imagem atual"
                        >

                        <small>Imagem atual. Deixe o campo vazio para mantê-la.</small>

                    </div>

                <?php endif; ?>

            </div>

        </div>

        <div class="form-actions">

            <button type="submit" class="btn-primary">

                <?php echo $desenvolvedora ? "Salvar alterações" : "Cadastrar desenvolvedora"; ?>

            </button>

            <?php if ($desenvolvedora): ?>

                <a href="cadastro_desenvolvedora.php" class="btn-back">
                    Cancelar
                </a>

            <?php endif; ?>

        </div>

    </form>

    <!-- LISTAGEM -->

    <h3>📋 Desenvolvedoras cadastradas</h3>

    <div class="table-container">

        <table>

            <thead>

                <tr>
                    <th>Imagem</th>
                    <th>Nome</th>
                    <th>País</th>
                    <th>Fundação</th>
                    <th>Website</th>
                    <th>Ações</th>
                </tr>

            </thead>

            <tbody>

                <?php if ($desenvolvedoras->num_rows > 0): ?>

                    <?php while ($row = $desenvolvedoras->fetch_assoc()): ?>

                        <tr>

                            <td>

                                <?php if (!empty($row["imagem"])): ?>

                                    <img
                                        src="<?php echo escaparDev($row["imagem"]); ?>"
                                        alt="<?php echo escaparDev($row["nome"]); ?>"
                                        class="table-thumbnail"
                                    >

                                <?php else: ?>

                                    <div class="no-image">Sem imagem</div>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?php echo escaparDev($row["nome"]); ?>
                            </td>

                            <td>
                                <?php echo escaparDev($row["pais"]) ?: "—"; ?>
                            </td>

                            <td>
                                <?php echo escaparDev($row["ano_fundacao"]) ?: "—"; ?>
                            </td>

                            <td>

                                <?php if (!empty($row["website"])): ?>

                                    <a
                                        href="<?php echo escaparDev($row["website"]); ?>"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        Visitar site
                                    </a>

                                <?php else: ?>

                                    —

                                <?php endif; ?>

                            </td>

                            <td>

                                <div class="action-buttons">

                                    <a
                                        href="?edit_id=<?php echo (int)$row["id"]; ?>"
                                        class="btn-edit"
                                    >
                                        Editar
                                    </a>

                                    <form
                                        method="POST"
                                        style="padding:0;margin:0;background:none;border:0"
                                        onsubmit="return confirm('Deseja excluir esta desenvolvedora?');"
                                    >

                                        <input type="hidden" name="acao" value="excluir">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo escaparDev($_SESSION["csrf_token"]); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?php echo (int)$row["id"]; ?>"
                                        >

                                        <button type="submit" class="btn-delete">
                                            Excluir
                                        </button>

                                    </form>

                                    <a
                                        href="cadastro_jogo.php?desenvolvedora_id=<?php echo (int)$row["id"]; ?>"
                                        class="btn-music"
                                    >
                                        Jogos
                                    </a>

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>
                        <td colspan="6" class="empty-cell">
                            Nenhuma desenvolvedora cadastrada ainda.
                        </td>
                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>
