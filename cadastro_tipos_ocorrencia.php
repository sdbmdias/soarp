<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. VERIFICAÇÃO DE PERMISSÃO
if (!$isAdmin) {
    header("Location: dashboard.php");
    exit();
}

// 3. LÓGICA ESPECÍFICA DA PÁGINA
$mensagem_status = "";

// Assume-se que uma tabela `tipos_ocorrencia` (id, nome) existe.

// Processa o formulário de cadastro
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cadastrar_ocorrencia'])) {
    $nome_ocorrencia = htmlspecialchars(trim($_POST['nome_ocorrencia']));

    if (!empty($nome_ocorrencia)) {
        $stmt_check = $conn->prepare("SELECT id FROM tipos_ocorrencia WHERE nome = ?");
        $stmt_check->bind_param("s", $nome_ocorrencia);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows == 0) {
            $stmt_insert = $conn->prepare("INSERT INTO tipos_ocorrencia (nome) VALUES (?)");
            $stmt_insert->bind_param("s", $nome_ocorrencia);
            if ($stmt_insert->execute()) {
                $mensagem_status = "<div class='success-message-box'>Tipo de ocorrência cadastrado com sucesso!</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar: " . htmlspecialchars($stmt_insert->error) . "</div>";
            }
            $stmt_insert->close();
        } else {
            $mensagem_status = "<div class='error-message-box'>Este tipo de ocorrência já existe.</div>";
        }
        $stmt_check->close();
    } else {
        $mensagem_status = "<div class='error-message-box'>O nome da ocorrência não pode ser vazio.</div>";
    }
}

// Processa a exclusão
if ($isAdmin && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt_delete = $conn->prepare("DELETE FROM tipos_ocorrencia WHERE id = ?");
    $stmt_delete->bind_param("i", $delete_id);
    if ($stmt_delete->execute()) {
        $mensagem_status = "<div class='success-message-box'>Registro excluído com sucesso!</div>";
    } else {
        $mensagem_status = "<div class='error-message-box'>Erro ao excluir registro.</div>";
    }
    $stmt_delete->close();
}

// Busca os tipos de ocorrência
$tipos_ocorrencia = [];
$sql_ocorrencias = "SELECT id, nome FROM tipos_ocorrencia ORDER BY nome ASC";
$result_ocorrencias = $conn->query($sql_ocorrencias);
if ($result_ocorrencias->num_rows > 0) {
    while($row = $result_ocorrencias->fetch_assoc()) {
        $tipos_ocorrencia[] = $row;
    }
}
?>

<div class="main-content">
    <h1>Cadastro de Tipos de Ocorrência</h1>
    <p>Os tipos aqui cadastrados aparecerão como opções na tela de registro de missão.</p>

    <?php echo $mensagem_status; ?>

    <div class="form-container" style="margin-bottom: 40px;">
        <h2>Adicionar Novo Tipo</h2>
        <form action="cadastro_tipos_ocorrencia.php" method="POST">
            <div class="form-grid" style="grid-template-columns: 1fr auto;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label for="nome_ocorrencia">Nome do Tipo de Ocorrência:</label>
                    <input type="text" id="nome_ocorrencia" name="nome_ocorrencia" placeholder="Ex: Busca e Salvamento, Incêndio Florestal" required>
                </div>
                <div class="form-actions" style="padding-top: 15px;">
                     <button type="submit" name="cadastrar_ocorrencia">Salvar Tipo</button>
                </div>
            </div>
        </form>
    </div>

    <div class="table-container">
        <h2>Tipos Cadastrados</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th style="text-align: left;">Nome</th>
                    <th style="width: 150px;">Ação</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($tipos_ocorrencia)): ?>
                    <?php foreach ($tipos_ocorrencia as $tipo): ?>
                        <tr>
                            <td style="text-align: left;"><?php echo htmlspecialchars($tipo['nome']); ?></td>
                            <td class="action-buttons">
                                <a href="cadastro_tipos_ocorrencia.php?delete_id=<?php echo $tipo['id']; ?>" class="delete-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir este tipo de ocorrência?');">Excluir</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="2">Nenhum tipo de ocorrência cadastrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>