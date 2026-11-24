
<?php

require_once("valida_sessao.php");
require_once("conexao.php");

$mensagem = "";
$tipo_mensagem = "";
$upload = null;

// Proteção CSRF
if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function escaparJogo($valor) {
    return htmlspecialchars($valor ?? "", ENT_QUOTES, "UTF-8");
}

// =====================================
// UPLOAD DA CAPA DO JOGO
// =====================================

function salvarCapaJogo($arquivo) {

    if ($arquivo["error"] !== UPLOAD_ERR_OK) {
        return ["erro" => "Erro ao enviar a imagem."];
    }

    if ($arquivo["size"] > 5 * 1024 * 1024) {
        return ["erro" => "A imagem deve ter no máximo 5 MB."];
    }

    $info = @getimagesize($arquivo["tmp_name"]);

    if ($info === false) {
        return ["erro" => "Arquivo de imagem inválido."];
    }

    $formatos = [
        "image/jpeg" => "jpg",
        "image/png" => "png",
        "image/webp" => "webp"
    ];

    if (!isset($formatos[$info["mime"]])) {
        return ["erro" => "Utilize JPG, PNG ou WEBP."];
    }

    $nome = bin2hex(random_bytes(16)) . "." . $formatos[$info["mime"]];

    $pasta = __DIR__ . "/img/";

    if (!is_dir($pasta)) {
        mkdir($pasta, 0755, true);
    }

    if (!move_uploaded_file(
        $arquivo["tmp_name"],
        $pasta . $nome
    )) {
        return ["erro" => "Não foi possível salvar a capa."];
    }

    return ["caminho" => "img/" . $nome];
}

// =====================================
// CADASTRAR E EDITAR JOGOS
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    if (!hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )) {

        $mensagem = "Solicitação inválida.";
        $tipo_mensagem = "error";

    } else {

        $acao = $_POST["acao"] ?? "";

        if ($acao === "salvar") {

            $id = (int)($_POST["id"] ?? 0);

            $desenvolvedora_id = (int)($_POST["desenvolvedora_id"] ?? 0);
            $titulo = trim($_POST["titulo"] ?? "");
            $genero = trim($_POST["genero"] ?? "");
            $plataforma = trim($_POST["plataforma"] ?? "");
            $ano = trim($_POST["ano_lancamento"] ?? "");
            $descricao = trim($_POST["descricao"] ?? "");
            $trailer = trim($_POST["trailer_link"] ?? "");

            $upload = null;
            $atual = null;
            $imagem = "";

            if ($titulo === "" || $desenvolvedora_id <= 0) {

                $mensagem = "Preencha o título e selecione uma desenvolvedora.";
                $tipo_mensagem = "error";

            } elseif (
                $ano !== "" &&
                (!ctype_digit($ano) ||
                (int)$ano < 1950 ||
                (int)$ano > (int)date("Y") + 5)
            ) {

                $mensagem = "Informe um ano de lançamento válido.";
                $tipo_mensagem = "error";

            } elseif (
                $trailer !== "" &&
                (
                    !filter_var($trailer, FILTER_VALIDATE_URL) ||
                    !in_array(parse_url($trailer, PHP_URL_SCHEME), ["http", "https"], true)
                )
            ) {

                $mensagem = "Informe um link de trailer válido.";
                $tipo_mensagem = "error";

            } else {

                // Verifica se a desenvolvedora existe
                $verifica = $conn->prepare(
                    "SELECT id FROM desenvolvedoras WHERE id = ?"
                );

                $verifica->bind_param("i", $desenvolvedora_id);
                $verifica->execute();

                $existe = $verifica->get_result()->num_rows > 0;

                $verifica->close();

                if (!$existe) {

                    $mensagem = "Desenvolvedora não encontrada.";
                    $tipo_mensagem = "error";

                }

                // Busca imagem atual na edição
                if ($tipo_mensagem !== "error" && $id > 0) {

                    $consulta = $conn->prepare(
                        "SELECT imagem_capa FROM jogos WHERE id = ?"
                    );

                    $consulta->bind_param("i", $id);
                    $consulta->execute();

                    $atual = $consulta->get_result()->fetch_assoc();

                    $consulta->close();

                    if (!$atual) {

                        $mensagem = "Jogo não encontrado.";
                        $tipo_mensagem = "error";

                    } else {

                        $imagem = $atual["imagem_capa"] ?? "";
                    }
                }

                // Upload opcional
                if (
                    $tipo_mensagem !== "error" &&
                    isset($_FILES["imagem_capa"]) &&
                    $_FILES["imagem_capa"]["error"] !== UPLOAD_ERR_NO_FILE
                ) {

                    $upload = salvarCapaJogo($_FILES["imagem_capa"]);

                    if (isset($upload["erro"])) {

                        $mensagem = $upload["erro"];
                        $tipo_mensagem = "error";

                    } else {

                        $imagem = $upload["caminho"];
                    }
                }

                // Salvar no banco
                if ($tipo_mensagem !== "error") {

                    if ($id > 0) {

                        $sql = "UPDATE jogos SET
                            desenvolvedora_id = ?,
                            titulo = ?,
                            genero = ?,
                            plataforma = ?,
                            ano_lancamento = NULLIF(?, ''),
                            descricao = ?,
                            trailer_link = ?,
                            imagem_capa = ?
                            WHERE id = ?";

                        $stmt = $conn->prepare($sql);

                        $stmt->bind_param(
                            "isssssssi",
                            $desenvolvedora_id,
                            $titulo,
                            $genero,
                            $plataforma,
                            $ano,
                            $descricao,
                            $trailer,
                            $imagem,
                            $id
                        );

                        $operacao = "atualizado";

                    } else {

                        $sql = "INSERT INTO jogos (
                            desenvolvedora_id,
                            titulo,
                            genero,
                            plataforma,
                            ano_lancamento,
                            descricao,
                            trailer_link,
                            imagem_capa
                        ) VALUES (?, ?, ?, ?, NULLIF(?, ''), ?, ?, ?)";

                        $stmt = $conn->prepare($sql);

                        $stmt->bind_param(
                            "isssssss",
                            $desenvolvedora_id,
                            $titulo,
                            $genero,
                            $plataforma,
                            $ano,
                            $descricao,
                            $trailer,
                            $imagem
                        );

                        $operacao = "cadastrado";
                    }

                    if ($stmt->execute()) {

                        $mensagem = "Jogo " . $operacao . " com sucesso!";
                        $tipo_mensagem = "success";

                        // Remove capa antiga após salvar a nova
                        if (
                            $id > 0 &&
                            !empty($upload["caminho"]) &&
                            !empty($atual["imagem_capa"])
                        ) {

                            $antiga = __DIR__ . "/" . $atual["imagem_capa"];

                            if (is_file($antiga)) {
                                unlink($antiga);
                            }
                        }

                    } else {

                        $mensagem = "Erro ao salvar o jogo.";
                        $tipo_mensagem = "error";

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
        // EXCLUIR JOGO
        // =====================================

        if ($acao === "excluir") {

            $id = (int)($_POST["id"] ?? 0);

            $consulta = $conn->prepare(
                "SELECT imagem_capa FROM jogos WHERE id = ?"
            );

            $consulta->bind_param("i", $id);
            $consulta->execute();

            $dados = $consulta->get_result()->fetch_assoc();

            $consulta->close();

            if (!$dados) {

                $mensagem = "Jogo não encontrado.";
                $tipo_mensagem = "error";

            } else {

                $stmt = $conn->prepare(
                    "DELETE FROM jogos WHERE id = ?"
                );

                $stmt->bind_param("i", $id);

                if ($stmt->execute() && $stmt->affected_rows > 0) {

                    $mensagem = "Jogo excluído com sucesso!";
                    $tipo_mensagem = "success";

                    if (!empty($dados["imagem_capa"])) {

                        $arquivo = __DIR__ . "/" . $dados["imagem_capa"];

                        if (is_file($arquivo)) {
                            unlink($arquivo);
                        }
                    }

                } else {

                    $mensagem = "Erro ao excluir o jogo.";
                    $tipo_mensagem = "error";
                }

                $stmt->close();
            }
        }
    }
}

// =====================================
// BUSCAR JOGO PARA EDIÇÃO
// =====================================

$jogo = null;

if (isset($_GET["edit_id"])) {

    $id = (int)$_GET["edit_id"];

    $stmt = $conn->prepare(
        "SELECT * FROM jogos WHERE id = ?"
    );

    $stmt->bind_param("i", $id);
    $stmt->execute();

    $jogo = $stmt->get_result()->fetch_assoc();

    $stmt->close();
}

// =====================================
// FILTRO POR DESENVOLVEDORA
// =====================================

$filtro = isset($_GET["desenvolvedora_id"])
    ? (int)$_GET["desenvolvedora_id"]
    : (int)($jogo["desenvolvedora_id"] ?? 0);

// Buscar desenvolvedoras
$desenvolvedoras = $conn->query(
    "SELECT id, nome FROM desenvolvedoras ORDER BY nome"
);

// =====================================
// LISTAR JOGOS
// =====================================

$sql = "SELECT j.*, d.nome AS desenvolvedora_nome
        FROM jogos j
        INNER JOIN desenvolvedoras d
        ON j.desenvolvedora_id = d.id";

if ($filtro > 0) {

    $sql .= " WHERE j.desenvolvedora_id = ?";

}

$sql .= " ORDER BY j.titulo";

if ($filtro > 0) {

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $filtro);
    $stmt->execute();

    $jogos = $stmt->get_result();

} else {

    $jogos = $conn->query($sql);
}

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Jogos | GameVault</title>

    <link rel="stylesheet" href="styles.css?v=4">

</head>

<body>

<div class="container">

    <div class="page-header-bar">

        <h2>🎮 <?php echo $jogo ? "Editar Jogo" : "Cadastro de Jogos"; ?></h2>

        <div class="header-actions">

            <a href="index.php" class="btn-back">
                Voltar ao Menu
            </a>

            <a href="listagem_jogos.php" class="btn-primary">
                Biblioteca
            </a>

        </div>

    </div>

    <?php if ($mensagem !== ""): ?>

        <div class="message <?php echo escaparJogo($tipo_mensagem); ?>">

            <?php echo escaparJogo($mensagem); ?>

        </div>

    <?php endif; ?>

    <h3>
        <?php echo $jogo ? "Editar informações do jogo" : "Cadastrar novo jogo"; ?>
    </h3>

    <!-- FORMULÁRIO -->

    <form method="POST" enctype="multipart/form-data">

        <input type="hidden" name="acao" value="salvar">

        <input
            type="hidden"
            name="csrf_token"
            value="<?php echo escaparJogo($_SESSION["csrf_token"]); ?>"
        >

        <input
            type="hidden"
            name="id"
            value="<?php echo $jogo["id"] ?? ""; ?>"
        >

        <div class="form-grid">

            <div class="form-group">

                <label for="titulo">Nome do jogo *</label>

                <input
                    type="text"
                    id="titulo"
                    name="titulo"
                    maxlength="120"
                    value="<?php echo escaparJogo($jogo["titulo"] ?? ""); ?>"
                    placeholder="Ex: Grand Theft Auto V"
                    required
                >

            </div>

            <div class="form-group">

                <label for="desenvolvedora_id">Desenvolvedora *</label>

                <select
                    name="desenvolvedora_id"
                    id="desenvolvedora_id"
                    required
                >

                    <option value="">Selecione uma desenvolvedora</option>

                    <?php while ($dev = $desenvolvedoras->fetch_assoc()): ?>

                        <option
                            value="<?php echo (int)$dev["id"]; ?>"
                            <?php echo (
                                ($jogo["desenvolvedora_id"] ?? $filtro)
                                == $dev["id"]
                            ) ? "selected" : ""; ?>
                        >

                            <?php echo escaparJogo($dev["nome"]); ?>

                        </option>

                    <?php endwhile; ?>

                </select>

            </div>

            <div class="form-group">

                <label for="genero">Gênero</label>

                <input
                    type="text"
                    id="genero"
                    name="genero"
                    maxlength="60"
                    value="<?php echo escaparJogo($jogo["genero"] ?? ""); ?>"
                    placeholder="Ex: Ação, Aventura, RPG..."
                >

            </div>

            <div class="form-group">

                <label for="plataforma">Plataforma</label>

                <input
                    type="text"
                    id="plataforma"
                    name="plataforma"
                    maxlength="100"
                    value="<?php echo escaparJogo($jogo["plataforma"] ?? ""); ?>"
                    placeholder="Ex: PC, PlayStation 5, Xbox..."
                >

            </div>

            <div class="form-group">

                <label for="ano_lancamento">Ano de lançamento</label>

                <input
                    type="number"
                    id="ano_lancamento"
                    name="ano_lancamento"
                    min="1950"
                    max="<?php echo date("Y") + 5; ?>"
                    value="<?php echo escaparJogo($jogo["ano_lancamento"] ?? ""); ?>"
                    placeholder="Ex: 2013"
                >

            </div>

            <div class="form-group">

                <label for="trailer_link">Link do trailer</label>

                <input
                    type="url"
                    id="trailer_link"
                    name="trailer_link"
                    value="<?php echo escaparJogo($jogo["trailer_link"] ?? ""); ?>"
                    placeholder="https://www.youtube.com/watch?v=..."
                >

            </div>

            <div class="form-group full">

                <label for="descricao">Descrição do jogo</label>

                <textarea
                    id="descricao"
                    name="descricao"
                    placeholder="Descreva o jogo..."
                ><?php echo escaparJogo($jogo["descricao"] ?? ""); ?></textarea>

            </div>

            <div class="form-group full">

                <label for="imagem_capa">Capa do jogo</label>

                <input
                    type="file"
                    id="imagem_capa"
                    name="imagem_capa"
                    accept=".jpg,.jpeg,.png,.webp"
                >

                <small>
                    JPG, PNG ou WEBP. Máximo de 5 MB.
                </small>

                <?php if (!empty($jogo["imagem_capa"])): ?>

                    <div class="current-image">

                        <img
                            src="<?php echo escaparJogo($jogo["imagem_capa"]); ?>"
                            alt="Capa atual"
                        >

                        <small>
                            Capa atual. Deixe vazio para mantê-la.
                        </small>

                    </div>

                <?php endif; ?>

            </div>

        </div>

        <div class="form-actions">

            <button type="submit" class="btn-primary">

                <?php echo $jogo ? "Salvar alterações" : "Cadastrar jogo"; ?>

            </button>

            <?php if ($jogo): ?>

                <a href="cadastro_jogo.php" class="btn-back">
                    Cancelar
                </a>

            <?php endif; ?>

        </div>

    </form>

    <!-- LISTAGEM DE JOGOS -->

    <h3>🎮 Jogos cadastrados</h3>

    <div class="table-container">

        <table>

            <thead>

                <tr>
                    <th>Capa</th>
                    <th>Jogo</th>
                    <th>Desenvolvedora</th>
                    <th>Gênero</th>
                    <th>Plataforma</th>
                    <th>Ano</th>
                    <th>Ações</th>
                </tr>

            </thead>

            <tbody>

                <?php if ($jogos->num_rows > 0): ?>

                    <?php while ($row = $jogos->fetch_assoc()): ?>

                        <tr>

                            <td>

                                <?php if (!empty($row["imagem_capa"])): ?>

                                    <img
                                        src="<?php echo escaparJogo($row["imagem_capa"]); ?>"
                                        alt="<?php echo escaparJogo($row["titulo"]); ?>"
                                        class="table-thumbnail"
                                    >

                                <?php else: ?>

                                    <div class="no-image">Sem capa</div>

                                <?php endif; ?>

                            </td>

                            <td>
                                <?php echo escaparJogo($row["titulo"]); ?>
                            </td>

                            <td>
                                <?php echo escaparJogo($row["desenvolvedora_nome"]); ?>
                            </td>

                            <td>
                                <?php echo escaparJogo($row["genero"]) ?: "—"; ?>
                            </td>

                            <td>
                                <?php echo escaparJogo($row["plataforma"]) ?: "—"; ?>
                            </td>

                            <td>
                                <?php echo escaparJogo($row["ano_lancamento"]) ?: "—"; ?>
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
                                        onsubmit="return confirm('Deseja excluir este jogo?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="acao"
                                            value="excluir"
                                        >

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?php echo escaparJogo($_SESSION["csrf_token"]); ?>"
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

                                </div>

                            </td>

                        </tr>

                    <?php endwhile; ?>

                <?php else: ?>

                    <tr>

                        <td colspan="7" class="empty-cell">

                            Nenhum jogo cadastrado ainda.

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>
