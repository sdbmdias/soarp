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

// Processa o formulário de cadastro
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['cadastrar_unidade'])) {
    $crbm = htmlspecialchars(trim($_POST['crbm']));
    $obm = htmlspecialchars(trim($_POST['obm']));

    if (!empty($crbm) && !empty($obm)) {
        $stmt_check = $conn->prepare("SELECT id FROM crbm_obm WHERE crbm = ? AND obm = ?");
        $stmt_check->bind_param("ss", $crbm, $obm);
        $stmt_check->execute();
        $result_check = $stmt_check->get_result();

        if ($result_check->num_rows == 0) {
            $stmt_insert = $conn->prepare("INSERT INTO crbm_obm (crbm, obm) VALUES (?, ?)");
            $stmt_insert->bind_param("ss", $crbm, $obm);
            if ($stmt_insert->execute()) {
                $mensagem_status = "<div class='success-message-box'>Unidade cadastrada com sucesso!</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro ao cadastrar a unidade: " . htmlspecialchars($stmt_insert->error) . "</div>";
            }
            $stmt_insert->close();
        } else {
            $mensagem_status = "<div class='error-message-box'>Esta OBM/Seção já está cadastrada para este CRBM.</div>";
        }
        $stmt_check->close();
    } else {
        $mensagem_status = "<div class='error-message-box'>Ambos os campos são obrigatórios.</div>";
    }
}

// Processa a exclusão
if ($isAdmin && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);
    $stmt_delete = $conn->prepare("DELETE FROM crbm_obm WHERE id = ?");
    $stmt_delete->bind_param("i", $delete_id);
    if ($stmt_delete->execute()) {
        $mensagem_status = "<div class='success-message-box'>Registro excluído com sucesso!</div>";
    } else {
        $mensagem_status = "<div class='error-message-box'>Erro ao excluir registro.</div>";
    }
    $stmt_delete->close();
}

// Busca as unidades existentes e as agrupa, aplicando a ordenação personalizada
$unidades_cadastradas = [];
$sql_unidades = "
    SELECT id, crbm, obm 
    FROM crbm_obm 
    ORDER BY 
        CASE 
            WHEN crbm NOT LIKE '%CRBM' THEN 1 
            ELSE 2 
        END, 
        crbm, 
        CASE 
            WHEN obm LIKE '%BBM%' THEN 1 
            WHEN obm LIKE '%CIBM%' THEN 2 
            ELSE 3 
        END, 
        obm ASC";
$result_unidades = $conn->query($sql_unidades);
if ($result_unidades->num_rows > 0) {
    while($row = $result_unidades->fetch_assoc()) {
        $unidades_cadastradas[$row['crbm']][] = $row;
    }
}
?>

<div class="main-content">
    <h1>Cadastro de CRBMs e OBMs/Seções</h1>

    <?php echo $mensagem_status; ?>

    <div class="form-container" style="margin-bottom: 40px;">
        <h2>Adicionar Nova Unidade</h2>
        <form id="unidadeForm" action="cadastro_crbm_obm.php" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label for="crbm">CRBM:</label>
                    <input type="text" id="crbm" name="crbm" placeholder="Ex: 1CRBM, BOA, GOST" required>
                </div>
                <div class="form-group">
                    <label for="obm">Nome da OBM/Seção:</label>
                    <input type="text" id="obm" name="obm" placeholder="Ex: 1º BBM, SOARP" required>
                </div>
            </div>
            <div class="form-actions">
                <button type="submit" name="cadastrar_unidade">Salvar Unidade</button>
            </div>
        </form>
    </div>

    <div class="table-container">
        <h2>Unidades Cadastradas</h2>
        <?php if (!empty($unidades_cadastradas)): ?>
            <?php foreach ($unidades_cadastradas as $crbm => $obms): ?>
                <h3 style="margin-top: 20px; border-bottom: 2px solid #ccc; padding-bottom: 5px;"><?php echo htmlspecialchars(preg_replace('/(\d)(CRBM)/', '$1º $2', $crbm)); ?></h3>
                <div class="table-container" style="padding: 0; box-shadow: none;">
                    <table class="data-table" style="margin-top: 10px;">
                        <thead>
                            <tr>
                                <th style="text-align: left;">OBM/Seção</th>
                                <th style="width: 150px;">Ação</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($obms as $unidade): ?>
                                <tr>
                                    <td style="text-align: left;"><?php echo htmlspecialchars($unidade['obm']); ?></td>
                                    <td class="action-buttons">
                                        <a href="cadastro_crbm_obm.php?delete_id=<?php echo $unidade['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir esta unidade?');">Excluir</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p>Nenhuma unidade cadastrada.</p>
        <?php endif; ?>
    </div>
</div>

<?php
// INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>