<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA ESPECÍFICA DA PÁGINA
$pilotos = [];

// A consulta SQL muda dependendo do perfil do usuário
if ($isPiloto) {
    // 1. Busca o CRBM do piloto logado para filtrar a visualização
    $crbm_do_usuario_logado = '';
    $stmt_crbm = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    $stmt_crbm->bind_param("i", $_SESSION['user_id']);
    $stmt_crbm->execute();
    $result_crbm = $stmt_crbm->get_result();
    if ($result_crbm->num_rows > 0) {
        $crbm_do_usuario_logado = $result_crbm->fetch_assoc()['crbm_piloto'];
    }
    $stmt_crbm->close();

    // 2. Prepara a consulta para buscar apenas os pilotos do mesmo CRBM
    if (!empty($crbm_do_usuario_logado)) {
        $stmt_pilotos = $conn->prepare("SELECT id, nome_completo, cpf, email, telefone, crbm_piloto, obm_piloto, cadastro_sarpas, cparp, status_piloto, tipo_usuario FROM pilotos WHERE crbm_piloto = ? ORDER BY nome_completo ASC");
        $stmt_pilotos->bind_param("s", $crbm_do_usuario_logado);
        $stmt_pilotos->execute();
        $result_pilotos = $stmt_pilotos->get_result();
        $stmt_pilotos->close();
    }
} else { // Se for Administrador, busca todos os pilotos
    $sql_pilotos = "SELECT id, nome_completo, cpf, email, telefone, crbm_piloto, obm_piloto, cadastro_sarpas, cparp, status_piloto, tipo_usuario FROM pilotos ORDER BY nome_completo ASC";
    $result_pilotos = $conn->query($sql_pilotos);
}

// Popula o array de pilotos com o resultado da consulta
if (isset($result_pilotos) && $result_pilotos->num_rows > 0) {
    while ($row = $result_pilotos->fetch_assoc()) {
        $pilotos[] = $row;
    }
}
?>

<div class="main-content">
    <h1>Lista de Pilotos</h1>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Nome Completo</th>
                    <?php if ($isAdmin): ?>
                    <th>CPF</th>
                    <?php endif; ?>
                    <th>Contato</th>
                    <th>CRBM/OBM</th>
                    <th>SARPAS/CPARP</th>
                    <th>Status</th>
                    <?php if ($isAdmin): ?>
                    <th>Tipo Usuário</th>
                    <th>Ações</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pilotos)): ?>
                    <?php foreach ($pilotos as $piloto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($piloto['nome_completo'] ?? 'N/A'); ?></td>
                            
                            <?php if ($isAdmin): ?>
                            <td>
                                <?php
                                // Máscara de CPF para privacidade
                                $cpf_original = $piloto['cpf'] ?? '';
                                if (strlen($cpf_original) == 14) {
                                    echo htmlspecialchars('XXX.' . substr($cpf_original, 4, 7) . '-XX');
                                } else {
                                    echo htmlspecialchars($cpf_original ?: 'N/A');
                                }
                                ?>
                            </td>
                            <?php endif; ?>

                            <td><?php echo htmlspecialchars($piloto['email'] ?? 'N/A') . '<br>' . htmlspecialchars($piloto['telefone'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(($piloto['crbm_piloto'] ?? 'N/A') . ' / ' . ($piloto['obm_piloto'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars(($piloto['cadastro_sarpas'] ?? 'N/A') . ' (' . ($piloto['cparp'] ?? 'N/A') . ')'); ?></td>
                            <td>
                                <?php 
                                $status = $piloto['status_piloto'] ?? 'desconhecido';
                                $status_texto = ucfirst($status);
                                ?>
                                <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                            </td>

                            <?php if ($isAdmin): ?>
                            <td><?php echo htmlspecialchars(ucfirst($piloto['tipo_usuario'] ?? 'N/A')); ?></td>
                            <td class="action-buttons">
                                <a href="editar_pilotos.php?id=<?php echo $piloto['id']; ?>" class="edit-btn">Editar</a>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $isAdmin ? '8' : '5'; ?>">Nenhum piloto encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>