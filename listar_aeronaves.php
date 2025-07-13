<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA ESPECÍFICA DA PÁGINA
$aeronaves = [];

// A consulta SQL muda dependendo do perfil do usuário
if ($isPiloto) {
    // 1. Busca o CRBM do piloto logado
    $crbm_do_usuario_logado = '';
    $stmt_crbm = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    $stmt_crbm->bind_param("i", $_SESSION['user_id']);
    $stmt_crbm->execute();
    $result_crbm = $stmt_crbm->get_result();
    if ($result_crbm->num_rows > 0) {
        $crbm_do_usuario_logado = $result_crbm->fetch_assoc()['crbm_piloto'];
    }
    $stmt_crbm->close();

    // 2. Prepara a consulta para buscar apenas as aeronaves do mesmo CRBM
    if (!empty($crbm_do_usuario_logado)) {
        $stmt_aeronaves = $conn->prepare("SELECT id, prefixo, fabricante, modelo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, status, homologacao_anatel FROM aeronaves WHERE crbm = ? ORDER BY prefixo ASC");
        $stmt_aeronaves->bind_param("s", $crbm_do_usuario_logado);
        $stmt_aeronaves->execute();
        $result_aeronaves = $stmt_aeronaves->get_result();
        $stmt_aeronaves->close();
    }
} else { // Se for Administrador, busca todas as aeronaves
    $sql_aeronaves = "SELECT id, prefixo, fabricante, modelo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, status, homologacao_anatel FROM aeronaves ORDER BY prefixo ASC";
    $result_aeronaves = $conn->query($sql_aeronaves);
}

// Popula o array de aeronaves com o resultado da consulta
if (isset($result_aeronaves) && $result_aeronaves->num_rows > 0) {
    while ($row = $result_aeronaves->fetch_assoc()) {
        $aeronaves[] = $row;
    }
}
?>

<div class="main-content">
    <h1>Lista de Aeronaves</h1>

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
                    <th>ANATEL</th> <?php if ($isAdmin): ?>
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
                            <td><?php echo htmlspecialchars(($aeronave['crbm'] ?? 'N/A') . ' / ' . ($aeronave['obm'] ?? 'N/A')); ?></td>
                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', '-', $aeronave['tipo_drone'] ?? 'N/A'))); ?></td>
                            <td><?php echo htmlspecialchars($aeronave['pmd_kg'] ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                $status_map = [
                                    'ativo' => 'Ativa',
                                    'em_manutencao' => 'Em Manutenção',
                                    'baixada' => 'Baixada',
                                    'adida' => 'Adida'
                                ];
                                $status = $aeronave['status'] ?? 'desconhecido';
                                $status_texto = $status_map[$status] ?? ucfirst($status);
                                ?>
                                <span class="status-<?php echo htmlspecialchars($status); ?>">
                                    <?php echo htmlspecialchars($status_texto); ?>
                                </span>
                            </td>
                            <td><?php echo htmlspecialchars($aeronave['homologacao_anatel'] ?? 'Não'); ?></td> <?php if ($isAdmin): ?>
                            <td class="action-buttons">
                                <a href="editar_aeronaves.php?id=<?php echo $aeronave['id']; ?>" class="edit-btn">Editar</a>
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