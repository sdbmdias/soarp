<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA PARA BUSCAR O HISTÓRICO DE MANUTENÇÕES
$historico_manutencoes = [];
$manutencoes_por_crbm = [];

// --- Lógica de Ordenação ---
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'data_manutencao';
$sort_order = isset($_GET['order']) && strtolower($_GET['order']) == 'asc' ? 'ASC' : 'DESC';

// Validação da coluna de ordenação
$allowed_columns = ['data_manutencao', 'equipamento', 'tipo_manutencao'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'data_manutencao';
}

$order_by_clause = "ORDER BY ";
if ($sort_column === 'equipamento') {
    $order_by_clause .= "m.equipamento_tipo $sort_order, aeronave_prefixo $sort_order, controle_sn $sort_order";
} else {
    $order_by_clause .= "m.$sort_column $sort_order";
}


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
    // Admin vê tudo, aplicando a ordenação
    $sql_historico = $sql_base . " " . $order_by_clause;
    $result_historico = $conn->query($sql_historico);
    
    // Organiza os resultados em um array agrupado por CRBM
    if ($result_historico && $result_historico->num_rows > 0) {
        while ($row = $result_historico->fetch_assoc()) {
            $crbm_do_registro = $row['aeronave_crbm'] ?? $row['controle_crbm'];
            if (empty($crbm_do_registro)) {
                $crbm_do_registro = 'Sem Lotação Definida';
            }
            $manutencoes_por_crbm[$crbm_do_registro][] = $row;
        }
    }

} else { // Visão do Piloto
    $obm_do_piloto = '';
    $stmt_obm = $conn->prepare("SELECT obm_piloto FROM pilotos WHERE id = ?");
    $stmt_obm->bind_param("i", $_SESSION['user_id']);
    $stmt_obm->execute();
    $result_obm = $stmt_obm->get_result();
    if ($result_obm->num_rows > 0) {
        $obm_do_piloto = $result_obm->fetch_assoc()['obm_piloto'];
    }
    $stmt_obm->close();

    if (!empty($obm_do_piloto)) {
        $sql_historico = $sql_base . " WHERE (a.obm = ? OR c.obm = ?) " . $order_by_clause;
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

// Helper para links de ordenação
function get_sort_link_manutencao($column, $current_column, $current_order) {
    $order = ($column == $current_column && $current_order == 'desc') ? 'asc' : 'desc';
    return "?sort=$column&order=$order";
}
?>

<style>
@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
    .table-container::after { content: '◄ Arraste para ver mais ►'; display: block; text-align: center; font-size: 0.8em; color: #999; margin-top: 10px; }
}
.data-table th a { color: inherit; text-decoration: none; display: flex; align-items: center; justify-content: space-between; }
.data-table th a:hover { color: #0056b3; }
.data-table th .sort-icon { margin-left: 5px; opacity: 0.6; }
</style>

<div class="main-content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <h1>Histórico de Manutenções</h1>
        <a href="cadastro_manutencao.php" class="form-actions button" style="text-decoration: none; display: inline-block; padding: 10px 20px; background-color:#28a745; color:#fff;">
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
                                <th><a href="<?php echo get_sort_link_manutencao('data_manutencao', $sort_column, strtolower($sort_order)); ?>">Data <i class="fas fa-sort sort-icon"></i></a></th>
                                <th><a href="<?php echo get_sort_link_manutencao('equipamento', $sort_column, strtolower($sort_order)); ?>">Equipamento <i class="fas fa-sort sort-icon"></i></a></th>
                                <th><a href="<?php echo get_sort_link_manutencao('tipo_manutencao', $sort_column, strtolower($sort_order)); ?>">Tipo <i class="fas fa-sort sort-icon"></i></a></th>
                                <th>Responsável</th>
                                <th>Garantia até</th>
                                <th>Descrição</th>
                                <th>Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($manutencoes as $manutencao): ?>
                                <tr>
                                    <td><?php echo date("d/m/Y", strtotime($manutencao['data_manutencao'])); ?></td>
                                    <td>
                                        <?php 
                                            if ($manutencao['equipamento_tipo'] == 'Aeronave') {
                                                echo '<strong>Aeronave:</strong><br>' . htmlspecialchars($manutencao['aeronave_prefixo'] . ' - ' . $manutencao['aeronave_modelo']);
                                            } else {
                                                $vinculo = !empty($manutencao['controle_vinculado_a']) ? ' (Vinc. a ' . htmlspecialchars($manutencao['controle_vinculado_a']) . ')' : ' (Reserva)';
                                                echo '<strong>Controle:</strong><br>S/N: ' . htmlspecialchars($manutencao['controle_sn']) . ' ' . $vinculo;
                                            }
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($manutencao['tipo_manutencao']); ?></td>
                                    <td><?php echo htmlspecialchars($manutencao['responsavel']); ?></td>
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
            <div class="table-container"><p style="text-align: center;">Nenhum registro de manutenção encontrado.</p></div>
        <?php endif; ?>

    <?php else: ?>
        <div class="table-container">
            <table class="data-table">
                 <thead>
                    <tr>
                        <th><a href="<?php echo get_sort_link_manutencao('data_manutencao', $sort_column, strtolower($sort_order)); ?>">Data <i class="fas fa-sort sort-icon"></i></a></th>
                        <th><a href="<?php echo get_sort_link_manutencao('equipamento', $sort_column, strtolower($sort_order)); ?>">Equipamento <i class="fas fa-sort sort-icon"></i></a></th>
                        <th><a href="<?php echo get_sort_link_manutencao('tipo_manutencao', $sort_column, strtolower($sort_order)); ?>">Tipo <i class="fas fa-sort sort-icon"></i></a></th>
                        <th>Responsável</th>
                        <th>Garantia até</th>
                        <th>Descrição</th>
                        <th>Ações</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($historico_manutencoes)): ?>
                        <?php foreach ($historico_manutencoes as $manutencao): ?>
                            <tr>
                                <td><?php echo date("d/m/Y", strtotime($manutencao['data_manutencao'])); ?></td>
                                <td>
                                    <?php 
                                        if ($manutencao['equipamento_tipo'] == 'Aeronave') {
                                            echo '<strong>Aeronave:</strong><br>' . htmlspecialchars($manutencao['aeronave_prefixo'] . ' - ' . $manutencao['aeronave_modelo']);
                                        } else {
                                            $vinculo = !empty($manutencao['controle_vinculado_a']) ? ' (Vinc. a ' . htmlspecialchars($manutencao['controle_vinculado_a']) . ')' : ' (Reserva)';
                                            echo '<strong>Controle:</strong><br>S/N: ' . htmlspecialchars($manutencao['controle_sn']) . ' ' . $vinculo;
                                        }
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars($manutencao['tipo_manutencao']); ?></td>
                                <td><?php echo htmlspecialchars($manutencao['responsavel']); ?></td>
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
                        <tr><td colspan="7">Nenhum registro de manutenção encontrado para sua OBM.</td></tr>
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