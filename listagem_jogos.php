
<?php

require_once("valida_sessao.php");
require_once("conexao.php");

$mensagem = "";
$tipo_mensagem = "";

// =====================================
// PROTEÇÃO CSRF
// =====================================

if (empty($_SESSION["csrf_token"])) {
    $_SESSION["csrf_token"] = bin2hex(random_bytes(32));
}

function escaparLista($valor) {
    return htmlspecialchars($valor ?? "", ENT_QUOTES, "UTF-8");
}

// =====================================
// EXCLUIR JOGO
// =====================================

if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["acao"] ?? "") === "excluir") {

    if (!hash_equals(
        $_SESSION["csrf_token"],
        $_POST["csrf_token"] ?? ""
    )) {

        $mensagem = "Solicitação inválida.";
        $tipo_mensagem = "error";

    } else {

        $id = (int)($_POST["id"] ?? 0);

        // Buscar capa antes da exclusão
        $stmt = $conn->prepare(
            "SELECT imagem_capa FROM jogos WHERE id = ?"
        );

        $stmt->bind_param("i", $id);
        $stmt->execute();

        $dados = $stmt->get_result()->fetch_assoc();

        $stmt->close();

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

                // Apagar capa do servidor
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

// =====================================
// RECEBER FILTROS
// =====================================

$busca = trim($_GET["busca"] ?? "");
$desenvolvedora = (int)($_GET["desenvolvedora"] ?? 0);
$genero = trim($_GET["genero"] ?? "");
$plataforma = trim($_GET["plataforma"] ?? "");

// =====================================
// MONTAR CONSULTA DINÂMICA
// =====================================

$where = [];
$params = [];
$types = "";

$sql = "SELECT
            j.*,
            d.nome AS desenvolvedora_nome

        FROM jogos j

        INNER JOIN desenvolvedoras d
        ON j.desenvolvedora_id = d.id";

// Busca pelo nome do jogo
if ($busca !== "") {

    $where[] = "j.titulo LIKE ?";

    $params[] = "%" . $busca . "%";

    $types .= "s";
}

// Filtro por desenvolvedora
if ($desenvolvedora > 0) {

    $where[] = "j.desenvolvedora_id = ?";

    $params[] = $desenvolvedora;

    $types .= "i";
}

// Filtro por gênero
if ($genero !== "") {

    $where[] = "j.genero = ?";

    $params[] = $genero;

    $types .= "s";
}

// Filtro por plataforma
if ($plataforma !== "") {

    $where[] = "j.plataforma = ?";

    $params[] = $plataforma;

    $types .= "s";
}

// Acrescentar condições
if (!empty($where)) {

    $sql .= " WHERE " . implode(" AND ", $where);

}

$sql .= " ORDER BY j.titulo";

// Executar consulta
if (!empty($params)) {

    $stmt = $conn->prepare($sql);

    $stmt->bind_param($types, ...$params);

    $stmt->execute();

    $jogos = $stmt->get_result();

} else {

    $jogos = $conn->query($sql);

}

// =====================================
// CARREGAR OPÇÕES DOS FILTROS
// =====================================

$desenvolvedoras = $conn->query(
    "SELECT id, nome FROM desenvolvedoras ORDER BY nome"
);

$generos = $conn->query(
    "SELECT DISTINCT genero
     FROM jogos
     WHERE genero IS NOT NULL AND genero != ''
     ORDER BY genero"
);

$plataformas = $conn->query(
    "SELECT DISTINCT plataforma
     FROM jogos
     WHERE plataforma IS NOT NULL AND plataforma != ''
     ORDER BY plataforma"
);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Biblioteca de Jogos | GameVault</title>

    <link rel="stylesheet" href="styles.css?v=4">

</head>

<body>

<div class="container">

    <!-- CABEÇALHO -->

    <div class="page-header-bar">

        <h2>🕹️ Biblioteca de Jogos</h2>

        <div class="header-actions">

            <a href="index.php" class="btn-back">
                Voltar ao Menu
            </a>

            <a href="cadastro_jogo.php" class="btn-primary">
                + Novo Jogo
            </a>

        </div>

    </div>

    <!-- MENSAGEM -->

    <?php if ($mensagem !== ""): ?>

        <div class="message <?php echo escaparLista($tipo_mensagem); ?>">

            <?php echo escaparLista($mensagem); ?>

        </div>

    <?php endif; ?>

    <!-- FILTROS -->

    <h3>🔎 Encontrar jogos</h3>

    <div class="filters">

        <form method="GET" class="filter-form">

            <div class="filter-group">

                <!-- BUSCA -->

                <div class="filter-item">

                    <label for="busca">Nome do jogo</label>

                    <input
                        type="text"
                        id="busca"
                        name="busca"
                        value="<?php echo escaparLista($busca); ?>"
                        placeholder="Ex: Grand Theft Auto..."
                    >

                </div>

                <!-- DESENVOLVEDORA -->

                <div class="filter-item">

                    <label for="desenvolvedora">Desenvolvedora</label>

                    <select name="desenvolvedora" id="desenvolvedora">

                        <option value="">Todas as desenvolvedoras</option>

                        <?php while ($dev = $desenvolvedoras->fetch_assoc()): ?>

                            <option
                                value="<?php echo (int)$dev["id"]; ?>"
                                <?php echo $desenvolvedora == $dev["id"] ? "selected" : ""; ?>
                            >

                                <?php echo escaparLista($dev["nome"]); ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

                <!-- GÊNERO -->

                <div class="filter-item">

                    <label for="genero">Gênero</label>

                    <select name="genero" id="genero">

                        <option value="">Todos os gêneros</option>

                        <?php while ($g = $generos->fetch_assoc()): ?>

                            <option
                                value="<?php echo escaparLista($g["genero"]); ?>"
                                <?php echo $genero === $g["genero"] ? "selected" : ""; ?>
                            >

                                <?php echo escaparLista($g["genero"]); ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

                <!-- PLATAFORMA -->

                <div class="filter-item">

                    <label for="plataforma">Plataforma</label>

                    <select name="plataforma" id="plataforma">

                        <option value="">Todas as plataformas</option>

                        <?php while ($p = $plataformas->fetch_assoc()): ?>

                            <option
                                value="<?php echo escaparLista($p["plataforma"]); ?>"
                                <?php echo $plataforma === $p["plataforma"] ? "selected" : ""; ?>
                            >

                                <?php echo escaparLista($p["plataforma"]); ?>

                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>

            </div>

            <div class="filter-actions">

                <button type="submit" class="btn-primary">
                    🔍 Filtrar jogos
                </button>

                <a href="listagem_jogos.php" class="btn-back">
                    Limpar filtros
                </a>

            </div>

        </form>

    </div>

    <!-- RESULTADOS -->

    <h3>🎮 Jogos encontrados</h3>

    <p class="text-muted mb-2">

        Total de resultados:
        <strong><?php echo $jogos->num_rows; ?></strong>

    </p>

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

                            <!-- CAPA -->

                            <td>

                                <?php if (!empty($row["imagem_capa"])): ?>

                                    <img
                                        src="<?php echo escaparLista($row["imagem_capa"]); ?>"
                                        alt="<?php echo escaparLista($row["titulo"]); ?>"
                                        class="table-thumbnail"
                                    >

                                <?php else: ?>

                                    <div class="no-image">
                                        Sem capa
                                    </div>

                                <?php endif; ?>

                            </td>

                            <!-- INFORMAÇÕES -->

                            <td>
                                <strong>
                                    <?php echo escaparLista($row["titulo"]); ?>
                                </strong>
                            </td>

                            <td>
                                <?php echo escaparLista($row["desenvolvedora_nome"]); ?>
                            </td>

                            <td>
                                <?php echo escaparLista($row["genero"]) ?: "—"; ?>
                            </td>

                            <td>
                                <?php echo escaparLista($row["plataforma"]) ?: "—"; ?>
                            </td>

                            <td>
                                <?php echo escaparLista($row["ano_lancamento"]) ?: "—"; ?>
                            </td>

                            <!-- AÇÕES -->

                            <td>

                                <div class="action-buttons">

                                    <a
                                        href="cadastro_jogo.php?edit_id=<?php echo (int)$row["id"]; ?>"
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
                                            value="<?php echo escaparLista($_SESSION["csrf_token"]); ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?php echo (int)$row["id"]; ?>"
                                        >

                                        <button
                                            type="submit"
                                            class="btn-delete"
                                        >
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

                            <?php if (!empty($_GET)): ?>

                                Nenhum jogo encontrado com esses filtros.

                            <?php else: ?>

                                Nenhum jogo cadastrado ainda.

                            <?php endif; ?>

                        </td>

                    </tr>

                <?php endif; ?>

            </tbody>

        </table>

    </div>

</div>

</body>
</html>


