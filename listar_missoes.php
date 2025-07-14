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
            $stmt_get_files = $conn->prepare("SELECT file_path FROM missoes_gpx_files WHERE missao_id = ?");
            $stmt_get_files->bind_param("i", $missao_id_para_excluir);
            $stmt_get_files->execute();
            $result_files = $stmt_get_files->get_result();
            while ($file = $result_files->fetch_assoc()) {
                if (file_exists($file['file_path'])) {
                    unlink($file['file_path']);
                }
            }
            $stmt_get_files->close();

            $stmt_delete_mission = $conn->prepare("DELETE FROM missoes WHERE id = ?");
            $stmt_delete_mission->bind_param("i", $missao_id_para_excluir);
            $stmt_delete_mission->execute();
            $stmt_delete_mission->close();
            
            $stmt_update_logbook = $conn->prepare("UPDATE aeronaves_logbook SET distancia_total_acumulada = distancia_total_acumulada - ?, tempo_voo_total_acumulado = tempo_voo_total_acumulado - ? WHERE aeronave_id = ?");
            $stmt_update_logbook->bind_param("ddi", $missao_data['total_distancia_percorrida'], $missao_data['total_tempo_voo'], $missao_data['aeronave_id']);
            $stmt_update_logbook->execute();
            $stmt_update_logbook->close();

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

// --- LÓGICA PARA BUSCAR AS MISSÕES COM MÚLTIPLOS PILOTOS ---
$missoes = [];
$sql_missoes = "
    SELECT 
        m.id, m.data_ocorrencia, m.tipo_ocorrencia, m.rgo_ocorrencia, m.total_tempo_voo,
        a.prefixo AS aeronave_prefixo,
        GROUP_CONCAT(CONCAT(p.posto_graduacao, ' ', p.nome_completo) SEPARATOR '<br>') AS pilotos_nomes
    FROM missoes m
    JOIN aeronaves a ON m.aeronave_id = a.id
    JOIN missoes_pilotos mp ON m.id = mp.missao_id
    JOIN pilotos p ON mp.piloto_id = p.id
    GROUP BY m.id
    ORDER BY m.data_ocorrencia DESC, m.id DESC
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

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center;">
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
                    <th>Ocorrência</th>
                    <th>Tempo de Voo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($missoes)): ?>
                    <?php foreach ($missoes as $missao): ?>
                        <tr>
                            <td><?php echo date("d/m/Y", strtotime($missao['data_ocorrencia'])); ?></td>
                            <td><?php echo htmlspecialchars($missao['aeronave_prefixo']); ?></td>
                            <td style="text-align: left;"><?php echo $missao['pilotos_nomes']; ?></td>
                            <td style="text-align: left;">
                                <strong><?php echo htmlspecialchars($missao['rgo_ocorrencia'] ?? 'MISSÃO SEM RGO'); ?></strong><br>
                                <small><?php echo htmlspecialchars($missao['tipo_ocorrencia']); ?></small>
                            </td>
                            <td><?php echo formatarTempoVoo($missao['total_tempo_voo']); ?></td>
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