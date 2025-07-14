<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA PARA BUSCAR O HISTÓRICO DE MANUTENÇÕES
$historico_manutencoes = [];
$manutencoes_por_crbm = []; // Novo array para a visão do admin

// A consulta agora busca o CRBM de ambos os tipos de equipamento
$sql_base = "SELECT 
                m.*, 
                a.prefixo AS aeronave_prefixo, 
                a.modelo AS aeronave_modelo,
                a.crbm AS aeronave_crbm,
                c.numero_serie AS controle_sn,
                c.modelo AS controle_modelo,
                c.crbm AS controle_crbm,
                a_vinc.prefixo AS controle_vinculado_a
             FROM manutencoes m 
             LEFT JOIN aeronaves a ON m.equipamento_id = a.id AND m.equipamento_tipo = 'Aeronave'
             LEFT JOIN controles c ON m.equipamento_id = c.id AND m.equipamento_tipo = 'Controle'
             LEFT JOIN aeronaves a_vinc ON c.aeronave_id = a_vinc.id";

if ($isAdmin) {
    // Admin vê tudo, ordenado por CRBM e depois por data
    $sql_historico = $sql_base . " ORDER BY aeronave_crbm, controle_crbm, m.data_manutencao DESC";
    $result_historico = $conn->query($sql_historico);
    
    // Organiza os resultados em um array agrupado por CRBM
    if ($result_historico && $result_historico->num_rows > 0) {
        while ($row = $result_historico->fetch_assoc()) {
            // Determina a qual CRBM o registro pertence
            $crbm_do_registro = $row['aeronave_crbm'] ?? $row['controle_crbm'];
            if (empty($crbm_do_registro)) {
                $crbm_do_registro = 'Sem Lotação';
            }
            $manutencoes_por_crbm[$crbm_do_registro][] = $row;
        }
    }

} else { // Visão do Piloto (permanece a mesma)
    // 1. Busca a OBM do piloto logado
    $obm_do_piloto = '';
    $stmt_obm = $conn->prepare("SELECT obm_piloto FROM pilotos WHERE id = ?");
    $stmt_obm->bind_param("i", $_SESSION['user_id']);
    $stmt_obm->execute();
    $result_obm = $stmt_obm->get_result();
    if ($result_obm->num_rows > 0) {
        $obm_do_piloto = $result_obm->fetch_assoc()['obm_piloto'];
    }
    $stmt_obm->close();

    // 2. Busca o histórico de manutenções de equipamentos da OBM do piloto
    if (!empty($obm_do_piloto)) {
        // A cláusula WHERE agora verifica a OBM em ambas as tabelas de equipamento
        $sql_historico = $sql_base . " WHERE (a.obm = ? OR c.obm = ?) ORDER BY m.data_manutencao DESC";
        $stmt_historico = $conn->prepare($sql_historico);
        $stmt_historico->bind_param("ss", $obm_do_piloto, $obm_do_piloto);
        $stmt_historico->execute();
        $result_historico = $stmt_historico->get_result();
        if ($result_historico) {
            while ($row = $result_historico->fetch_assoc()) {
                $historico_manutencoes[] = $row;
            }
        }
    }
}
?>

<style>
@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
    .table-container::after { content: '◄ Arraste para ver mais ►'; display: block; text-align: center; font-size: 0.8em; color: #999; margin-top: 10px; }
}
</style>

<div class="main-content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Histórico de Manutenções</h1>
        <a href="cadastro_manutencao.php" class="form-actions button" style="text-decoration: none; display: inline-block; padding: 10px 20px;">
            <i class="fas fa-plus"></i> Registrar Nova Manutenção
        </a>
    </div>

    <?php if ($isAdmin): ?>
        <?php if (!empty($manutencoes_por_crbm)): ?>
            <?php foreach ($manutencoes_por_crbm as $crbm => $manutencoes): ?>
                <div class="table-container" style="margin-top: 30px;">
                    <h2><?php echo htmlspecialchars(preg_replace('/(\d)(CRBM)/', '$1º $2', $crbm)); ?></h2>
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Equipamento</th>
                                <th>Tipo</th>
                                <th>Data</th>
                                <th>Responsável</th>
                                <th>Nota Fiscal / OS</th>
                                <th>Garantia até</th>
                                <th>Descrição</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($manutencoes as $manutencao): ?>
                                <tr>
                                    <td>
                                        <?php 
                                            if ($manutencao['equipamento_tipo'] == 'Aeronave') {
                                                echo '<strong>Aeronave:</strong><br>' . htmlspecialchars($manutencao['aeronave_prefixo'] . ' - ' . $manutencao['aeronave_modelo']);
                                            } else {
                                                $vinculo = !empty($manutencao['controle_vinculado_a']) ? ' (Vinculado ao ' . htmlspecialchars($manutencao['controle_vinculado_a']) . ')' : ' (Reserva)';
                                                echo '<strong>Controle:</strong><br>S/N: ' . htmlspecialchars($manutencao['controle_sn'] . ' - ' . $manutencao['controle_modelo']) . ' ' . $vinculo;
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($manutencao['tipo_manutencao']); ?></td>
                                    <td><?php echo date("d/m/Y", strtotime($manutencao['data_manutencao'])); ?></td>
                                    <td><?php echo htmlspecialchars($manutencao['responsavel']); ?></td>
                                    <td><?php echo htmlspecialchars($manutencao['documento_servico'] ?? 'N/A'); ?></td>
                                    <td><?php echo !empty($manutencao['garantia_ate']) ? date("d/m/Y", strtotime($manutencao['garantia_ate'])) : 'N/A'; ?></td>
                                    <td title="<?php echo htmlspecialchars($manutencao['descricao']); ?>">
                                        <?php echo htmlspecialchars(substr($manutencao['descricao'], 0, 50)) . (strlen($manutencao['descricao']) > 50 ? '...' : ''); ?>
                                    </td>
                                    <td class="action-buttons">
                                        <a href="ver_manutencao.php?id=<?php echo $manutencao['id']; ?>" class="edit-btn">Ver Detalhes</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="table-container">
                <p style="text-align: center;">Nenhum registro de manutenção encontrado.</p>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Equipamento</th>
                        <th>Tipo</th>
                        <th>Data</th>
                        <th>Responsável</th>
                        <th>Nota Fiscal / OS</th>
                        <th>Garantia até</th>
                        <th>Descrição</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($historico_manutencoes)): ?>
                        <?php foreach ($historico_manutencoes as $manutencao): ?>
                            <tr>
                                <td>
                                    <?php 
                                        if ($manutencao['equipamento_tipo'] == 'Aeronave') {
                                            echo '<strong>Aeronave:</strong><br>' . htmlspecialchars($manutencao['aeronave_prefixo'] . ' - ' . $manutencao['aeronave_modelo']);
                                        } else {
                                            $vinculo = !empty($manutencao['controle_vinculado_a']) ? ' (Vinculado ao ' . htmlspecialchars($manutencao['controle_vinculado_a']) . ')' : ' (Reserva)';
                                            echo '<strong>Controle:</strong><br>S/N: ' . htmlspecialchars($manutencao['controle_sn'] . ' - ' . $manutencao['controle_modelo']) . ' ' . $vinculo;
                                        }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($manutencao['tipo_manutencao']); ?></td>
                                <td><?php echo date("d/m/Y", strtotime($manutencao['data_manutencao'])); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['responsavel']); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['documento_servico'] ?? 'N/A'); ?></td>
                                <td><?php echo !empty($manutencao['garantia_ate']) ? date("d/m/Y", strtotime($manutencao['garantia_ate'])) : 'N/A'; ?></td>
                                <td title="<?php echo htmlspecialchars($manutencao['descricao']); ?>">
                                    <?php echo htmlspecialchars(substr($manutencao['descricao'], 0, 50)) . (strlen($manutencao['descricao']) > 50 ? '...' : ''); ?>
                                </td>
                                <td class="action-buttons">
                                    <a href="ver_manutencao.php?id=<?php echo $manutencao['id']; ?>" class="edit-btn">Ver Detalhes</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">Nenhum registro de manutenção encontrado para sua OBM.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>