<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

$mensagem_status = "";

// --- LÓGICA DE EXCLUSÃO (APENAS PARA ADMINS) ---
if ($isAdmin && isset($_GET['delete_id'])) {
    $missao_id_para_excluir = intval($_GET['delete_id']);

    $conn->begin_transaction();
    try {
        $stmt_get_data = $conn->prepare("SELECT aeronave_id, total_distancia_percorrida, total_tempo_voo FROM missoes WHERE id = ?");
        $stmt_get_data->bind_param("i", $missao_id_para_excluir);
        $stmt_get_data->execute();
        $result_data = $stmt_get_data->get_result();
        $missao_data = $result_data->fetch_assoc();
        $stmt_get_data->close();

        if ($missao_data) {
            
            // Excluir coordenadas primeiro, que dependem dos gpx_files
            $stmt_delete_coords = $conn->prepare("DELETE FROM missao_coordenadas WHERE gpx_file_id IN (SELECT id FROM missoes_gpx_files WHERE missao_id = ?)");
            $stmt_delete_coords->bind_param("i", $missao_id_para_excluir);
            $stmt_delete_coords->execute();
            $stmt_delete_coords->close();

            // Excluir ficheiros GPX
            $stmt_delete_gpx = $conn->prepare("DELETE FROM missoes_gpx_files WHERE missao_id = ?");
            $stmt_delete_gpx->bind_param("i", $missao_id_para_excluir);
            $stmt_delete_gpx->execute();
            $stmt_delete_gpx->close();

            // Excluir associações de pilotos
            $stmt_delete_pilots = $conn->prepare("DELETE FROM missoes_pilotos WHERE missao_id = ?");
            $stmt_delete_pilots->bind_param("i", $missao_id_para_excluir);
            $stmt_delete_pilots->execute();
            $stmt_delete_pilots->close();
            
            // Reverter o logbook da aeronave
            $stmt_update_logbook = $conn->prepare("UPDATE aeronaves_logbook SET distancia_total_acumulada = GREATEST(0, distancia_total_acumulada - ?), tempo_voo_total_acumulado = GREATEST(0, tempo_voo_total_acumulado - ?) WHERE aeronave_id = ?");
            $stmt_update_logbook->bind_param("ddi", $missao_data['total_distancia_percorrida'], $missao_data['total_tempo_voo'], $missao_data['aeronave_id']);
            $stmt_update_logbook->execute();
            $stmt_update_logbook->close();

            // Finalmente, excluir a missão
            $stmt_delete_mission = $conn->prepare("DELETE FROM missoes WHERE id = ?");
            $stmt_delete_mission->bind_param("i", $missao_id_para_excluir);
            $stmt_delete_mission->execute();
            $stmt_delete_mission->close();
            
            $conn->commit();
            $mensagem_status = "<div class='success-message-box'>Missão #" . $missao_id_para_excluir . " e todos os seus dados foram excluídos com sucesso.</div>";
        } else {
            throw new Exception("Missão não encontrada.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $mensagem_status = "<div class='error-message-box'>Erro ao excluir a missão: " . $e->getMessage() . "</div>";
    }
}

// --- LÓGICA PARA BUSCAR AS MISSÕES COM ORDENAÇÃO DE PILOTOS CORRIGIDA ---
$missoes = [];
$sql_missoes = "
    SELECT 
        m.id, m.data, m.descricao_operacao, m.rgo_ocorrencia, m.total_tempo_voo,
        a.prefixo AS aeronave_prefixo,
        GROUP_CONCAT(DISTINCT CONCAT(p.posto_graduacao, ' ', p.nome_completo) 
            ORDER BY
                CASE p.posto_graduacao
                    WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3
                    WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6
                    WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9
                    WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12
                    WHEN 'Sd. QPBM' THEN 13 ELSE 14
                END
            SEPARATOR '<br>') AS pilotos_nomes
    FROM missoes m
    JOIN aeronaves a ON m.aeronave_id = a.id
    LEFT JOIN missoes_pilotos mp ON m.id = mp.missao_id
    LEFT JOIN pilotos p ON mp.piloto_id = p.id
    GROUP BY m.id
    ORDER BY m.data DESC, m.id DESC
";
$result_missoes = $conn->query($sql_missoes);
if ($result_missoes) {
    while($row = $result_missoes->fetch_assoc()) {
        $missoes[] = $row;
    }
}

function formatarTempoVoo($segundos) {
    if ($segundos <= 0) return '0min';
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $resultado = '';
    if ($horas > 0) $resultado .= $horas . 'h ';
    if ($minutos > 0) $resultado .= $minutos . 'min';
    return trim($resultado) ?: '0min';
}
?>

<style>
/* Adiciona uma dica visual para rolagem em telas pequenas */
@media (max-width: 768px) {
    .table-container::after { content: '◄ Arraste para ver mais ►'; display: block; text-align: center; font-size: 0.8em; color: #999; margin-top: 10px; }
    .page-header { flex-direction: column; align-items: flex-start; gap: 15px; }
}
</style>
<div class="main-content">
    <div class="page-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap;">
        <h1>Logbook de Missões</h1>
        <a href="cadastro_missao.php" class="form-actions button" style="text-decoration: none; display: inline-block; padding: 10px 20px; background-color: #28a745; color: #fff;">
            <i class="fas fa-plus"></i> Adicionar Nova Missão
        </a>
    </div>

    <?php echo $mensagem_status; ?>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data</th>
                    <th>Aeronave</th>
                    <th>Piloto(s)</th>
                    <th>Operação</th>
                    <th>Tempo de Voo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($missoes)): ?>
                    <?php foreach ($missoes as $missao): ?>
                        <tr>
                            <td style="text-align: center;"><?php echo date("d/m/Y", strtotime($missao['data'])); ?></td>
                            <td style="text-align: center;"><?php echo htmlspecialchars($missao['aeronave_prefixo']); ?></td>
                            <td style="text-align: center;"><?php echo $missao['pilotos_nomes'] ?? 'Nenhum piloto associado'; ?></td>
                            <td style="text-align: center;">
                                <strong><?php echo htmlspecialchars($missao['rgo_ocorrencia'] ?? 'MISSÃO SEM RGO'); ?></strong><br>
                                <small><?php echo htmlspecialchars($missao['descricao_operacao']); ?></small>
                            </td>
                            <td style="text-align: center;"><?php echo formatarTempoVoo($missao['total_tempo_voo']); ?></td>
                            <td class="action-buttons">
                                <a href="ver_missao.php?id=<?php echo $missao['id']; ?>" class="edit-btn">Ver Detalhes</a>
                                <?php if ($isAdmin): ?>
                                    <a href="editar_missao.php?id=<?php echo $missao['id']; ?>" class="edit-btn" style="background-color: #ffc107; color: #212529;">Editar</a>
                                    <a href="listar_missoes.php?delete_id=<?php echo $missao['id']; ?>" class="edit-btn" style="background-color: #dc3545; color: #fff;" onclick="return confirm('Tem a certeza que deseja excluir permanentemente a missão (RGO: <?php echo htmlspecialchars($missao['rgo_ocorrencia']); ?>) e todos os seus dados associados? Esta ação é irreversível.');">Excluir</a>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">Nenhuma missão encontrada. Clique em "Adicionar Nova Missão" para começar.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>
<?php
require_once 'includes/footer.php';
?>