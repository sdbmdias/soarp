<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

$mensagem_status = "";

// 2. LÓGICA DE EXCLUSÃO (APENAS PARA ADMINS)
if ($isAdmin && isset($_GET['delete_id'])) {
    $delete_id = intval($_GET['delete_id']);

    // Verifica se a aeronave está associada a missões, manutenções ou controles
    $stmt_check_missoes = $conn->prepare("SELECT COUNT(*) AS total FROM missoes WHERE aeronave_id = ?");
    $stmt_check_missoes->bind_param("i", $delete_id);
    $stmt_check_missoes->execute();
    $missoes_count = $stmt_check_missoes->get_result()->fetch_assoc()['total'];
    $stmt_check_missoes->close();

    $stmt_check_manutencoes = $conn->prepare("SELECT COUNT(*) AS total FROM manutencoes WHERE equipamento_tipo = 'Aeronave' AND equipamento_id = ?");
    $stmt_check_manutencoes->bind_param("i", $delete_id);
    $stmt_check_manutencoes->execute();
    $manutencoes_count = $stmt_check_manutencoes->get_result()->fetch_assoc()['total'];
    $stmt_check_manutencoes->close();

    $stmt_check_controles = $conn->prepare("SELECT COUNT(*) AS total FROM controles WHERE aeronave_id = ?");
    $stmt_check_controles->bind_param("i", $delete_id);
    $stmt_check_controles->execute();
    $controles_count = $stmt_check_controles->get_result()->fetch_assoc()['total'];
    $stmt_check_controles->close();

    if ($missoes_count > 0 || $manutencoes_count > 0 || $controles_count > 0) {
        // Se houver qualquer vínculo, impede a exclusão
        $mensagem_status = "<div class='error-message-box'>Não é possível excluir esta aeronave, pois ela possui registros de missões, manutenções ou controles vinculados. Considere alterar o status para 'Baixada'.</div>";
    } else {
        // Permite a exclusão se não houver vínculos
        $stmt_delete = $conn->prepare("DELETE FROM aeronaves WHERE id = ?");
        $stmt_delete->bind_param("i", $delete_id);
        if ($stmt_delete->execute()) {
            // Também remove do logbook se existir
            $conn->query("DELETE FROM aeronaves_logbook WHERE aeronave_id = $delete_id");
            $mensagem_status = "<div class='success-message-box'>Aeronave excluída com sucesso!</div>";
        } else {
            $mensagem_status = "<div class='error-message-box'>Erro ao excluir a aeronave.</div>";
        }
        $stmt_delete->close();
    }
}


// 3. LÓGICA PARA LISTAR AS AERONAVES
$aeronaves = [];
$result_aeronaves = null;

if ($isPiloto) {
    // CORREÇÃO: Piloto agora vê apenas aeronaves da sua OBM, para consistência.
    $obm_do_usuario_logado = '';
    $stmt_obm = $conn->prepare("SELECT obm_piloto FROM pilotos WHERE id = ?");
    $stmt_obm->bind_param("i", $_SESSION['user_id']);
    $stmt_obm->execute();
    $result_obm = $stmt_obm->get_result();
    if ($result_obm->num_rows > 0) {
        $obm_do_usuario_logado = $result_obm->fetch_assoc()['obm_piloto'];
    }
    $stmt_obm->close();

    if (!empty($obm_do_usuario_logado)) {
        $stmt_aeronaves = $conn->prepare("SELECT id, prefixo, fabricante, modelo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, status, homologacao_anatel FROM aeronaves WHERE obm = ? ORDER BY prefixo ASC");
        $stmt_aeronaves->bind_param("s", $obm_do_usuario_logado);
        $stmt_aeronaves->execute();
        $result_aeronaves = $stmt_aeronaves->get_result();
        $stmt_aeronaves->close();
    }
} else { // Admin vê todas
    $sql_aeronaves = "SELECT id, prefixo, fabricante, modelo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, status, homologacao_anatel FROM aeronaves ORDER BY prefixo ASC";
    $result_aeronaves = $conn->query($sql_aeronaves);
}

if ($result_aeronaves && $result_aeronaves->num_rows > 0) {
    while ($row = $result_aeronaves->fetch_assoc()) {
        $aeronaves[] = $row;
    }
}
?>
<style>
/* Adiciona uma dica visual para rolagem em telas pequenas */
@media (max-width: 768px) {
    .table-container::after {
        content: '◄ Arraste para ver mais ►';
        display: block;
        text-align: center;
        font-size: 0.8em;
        color: #999;
        margin-top: 10px;
    }
}
</style>
<div class="main-content">
    <h1>Lista de Aeronaves</h1>
    
    <?php if(!empty($mensagem_status)) echo $mensagem_status; ?>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Prefixo</th>
                    <th>Fabricante/Modelo</th>
                    <th>Nº Série</th>
                    <th>SISANT (Val.)</th>
                    <th>Lotação (CRBM/OBM)</th>
                    <th>Tipo</th>
                    <th>PMD (kg)</th>
                    <th>Status</th>
                    <th>ANATEL</th>
                    <?php if ($isAdmin): ?>
                    <th>Ações</th>
                    <?php endif; ?>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($aeronaves)): ?>
                    <?php foreach ($aeronaves as $aeronave): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($aeronave['prefixo'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars(($aeronave['fabricante'] ?? 'N/A') . ' / ' . ($aeronave['modelo'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars($aeronave['numero_serie'] ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                $sisant = $aeronave['cadastro_sisant'] ?? 'N/A';
                                $validade = $aeronave['validade_sisant'] ?? null;
                                $validade_formatada = $validade ? date("d/m/Y", strtotime($validade)) : 'N/A';
                                echo htmlspecialchars($sisant) . ' (' . $validade_formatada . ')';
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $crbm_formatado = preg_replace('/(\d)(CRBM)/', '$1º $2', $aeronave['crbm'] ?? 'N/A');
                                    echo htmlspecialchars($crbm_formatado . ' / ' . ($aeronave['obm'] ?? 'N/A'));
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', '-', $aeronave['tipo_drone'] ?? 'N/A'))); ?></td>
                            <td><?php echo htmlspecialchars($aeronave['pmd_kg'] ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                $status_map = ['ativo' => 'Ativa', 'em_manutencao' => 'Em Manutenção', 'baixada' => 'Baixada', 'adida' => 'Adida'];
                                $status = $aeronave['status'] ?? 'desconhecido';
                                $status_texto = $status_map[$status] ?? ucfirst($status);
                                ?>
                                <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($aeronave['homologacao_anatel'] ?? 'Não'); ?></td>
                            <?php if ($isAdmin): ?>
                            <td class="action-buttons">
                                <a href="editar_aeronaves.php?id=<?php echo $aeronave['id']; ?>" class="edit-btn">Editar</a>
                                <a href="listar_aeronaves.php?delete_id=<?php echo $aeronave['id']; ?>" class="edit-btn" style="background-color:#dc3545;" onclick="return confirm('Tem certeza que deseja excluir esta aeronave? Esta ação não pode ser desfeita e só funcionará se não houver missões ou manutenções vinculadas.');">Excluir</a>
                            </td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="<?php echo $isAdmin ? '10' : '9'; ?>">Nenhuma aeronave encontrada.</td>
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