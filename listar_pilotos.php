<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

$mensagem_status = "";

// Lógica para buscar o CRBM do piloto logado, se for um piloto
$logged_in_pilot_crbm = '';
if ($isPiloto && isset($_SESSION['user_id'])) {
    $stmt_crbm = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    if ($stmt_crbm) {
        $stmt_crbm->bind_param("i", $_SESSION['user_id']);
        $stmt_crbm->execute();
        $result_crbm = $stmt_crbm->get_result();
        if ($result_crbm->num_rows > 0) {
            $logged_in_pilot_crbm = $result_crbm->fetch_assoc()['crbm_piloto'];
        }
        $stmt_crbm->close();
    } else {
        // Erro na preparação da consulta do CRBM
        error_log("Erro na preparação da consulta de CRBM do piloto: " . $conn->error);
    }
}

// 2. LÓGICA DE EXCLUSÃO (APENAS PARA ADMINS)
if ($isAdmin && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // Verifica se o piloto está associado a missões
    $stmt_check_missoes = $conn->prepare("SELECT COUNT(*) AS total FROM missoes_pilotos WHERE piloto_id = ?");
    if ($stmt_check_missoes) {
        $stmt_check_missoes->bind_param("i", $delete_id);
        $stmt_check_missoes->execute();
        $missoes_count = $stmt_check_missoes->get_result()->fetch_assoc()['total'];
        $stmt_check_missoes->close();
    } else {
        error_log("Erro na preparação da consulta de missões para exclusão de piloto: " . $conn->error);
        $missoes_count = 0; // Assume 0 para não bloquear indevidamente
    }

    if ($missoes_count > 0) {
        $mensagem_status = "<div class='error-message-box'>Não é possível excluir este piloto, pois ele possui missões vinculadas.</div>";
    } else {
        $stmt_delete = $conn->prepare("DELETE FROM pilotos WHERE id = ?");
        if ($stmt_delete) {
            $stmt_delete->bind_param("i", $delete_id);
            if ($stmt_delete->execute()) {
                $mensagem_status = "<div class='success-message-box'>Piloto excluído com sucesso!</div>";
            } else {
                $mensagem_status = "<div class='error-message-box'>Erro ao excluir o piloto.</div>";
            }
            $stmt_delete->close();
        } else {
            error_log("Erro na preparação da consulta de exclusão de piloto: " . $conn->error);
            $mensagem_status = "<div class='error-message-box'>Erro interno ao tentar excluir o piloto.</div>";
        }
    }
}

// 3. LÓGICA PARA LISTAR OS PILOTOS
$pilotos = [];
$where_clauses = [];
$params = [];
$types = '';

// Se for um piloto, filtra automaticamente pelo CRBM dele
if ($isPiloto && !empty($logged_in_pilot_crbm)) {
    $where_clauses[] = "crbm_piloto = ?";
    $params[] = $logged_in_pilot_crbm;
    $types .= 's';
}

$sql_pilotos = "SELECT id, posto_graduacao, nome_completo, cpf, crbm_piloto, obm_piloto, status_piloto, tipo_usuario FROM pilotos";

if (!empty($where_clauses)) {
    $sql_pilotos .= " WHERE " . implode(' AND ', $where_clauses);
}

// Ordenação padrão por posto/graduação e nome
$sql_pilotos .= " ORDER BY CASE posto_graduacao WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3 WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6 WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9 WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12 WHEN 'Sd. QPBM' THEN 13 ELSE 14 END, nome_completo ASC";

$stmt_pilotos = $conn->prepare($sql_pilotos);
if ($stmt_pilotos) {
    if (!empty($params)) {
        $stmt_pilotos->bind_param($types, ...$params);
    }
    $stmt_pilotos->execute();
    $result_pilotos = $stmt_pilotos->get_result();
    if ($result_pilotos && $result_pilotos->num_rows > 0) {
        while ($row = $result_pilotos->fetch_assoc()) {
            $pilotos[] = $row;
        }
    }
    $stmt_pilotos->close();
} else {
    die("Erro na preparação da consulta de pilotos: " . $conn->error);
}
// A conexão com o BD é fechada no footer.php
?>
<div class="main-content">
    <h1>Lista de Pilotos <?php echo ($isPiloto && !empty($logged_in_pilot_crbm)) ? ' - CRBM ' . htmlspecialchars($logged_in_pilot_crbm) : ''; ?></h1>
    
    <?php if(!empty($mensagem_status)) echo $mensagem_status; ?>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Posto/Grad.</th>
                    <th>Nome Completo</th>
                    <th>CPF</th>
                    <th>CRBM</th>
                    <th>OBM</th>
                    <th>Status</th>
                    <th>Tipo Usuário</th>
                    <?php if ($isAdmin): ?>
                    <th>Ações</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pilotos)): ?>
                    <?php foreach ($pilotos as $piloto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($piloto['posto_graduacao'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($piloto['nome_completo'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(substr($piloto['cpf'], 0, 3) . '.XXX.XXX-' . substr($piloto['cpf'], -2)); ?></td>
                            <td><?php echo htmlspecialchars($piloto['crbm_piloto'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($piloto['obm_piloto'] ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                $status_map = ['ativo' => 'Ativo', 'afastado' => 'Afastado', 'desativado' => 'Desativado'];
                                $status = $piloto['status_piloto'] ?? 'N/A';
                                $status_texto = $status_map[$status] ?? ucfirst($status);
                                ?>
                                <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars(ucfirst($piloto['tipo_usuario'] ?? 'N/A')); ?></td>
                            <?php if ($isAdmin): ?>
                            <td class="action-buttons">
                                <a href="editar_pilotos.php?id=<?php echo $piloto['id']; ?>" class="edit-btn">Editar</a>
                                <a href="listar_pilotos.php?delete_id=<?php echo $piloto['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir este piloto? Esta ação não pode ser desfeita e só funcionará se não houver missões vinculadas.');">Excluir</a>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $isAdmin ? '8' : '7'; ?>">Nenhum piloto encontrado<?php echo ($isPiloto && !empty($logged_in_pilot_crbm)) ? ' para o CRBM ' . htmlspecialchars($logged_in_pilot_crbm) : ''; ?>.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
require_once 'includes/footer.php';
?>