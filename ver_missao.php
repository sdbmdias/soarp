<?php
require_once 'includes/header.php';

$missao_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$missao_details = null;
$pilotos_envolvidos = [];
$gpx_files_logs = [];

if ($missao_id <= 0) {
    header("Location: listar_missoes.php");
    exit();
}

// 1. BUSCA OS DETALHES GERAIS DA MISSÃO
$sql_details = "
    SELECT 
        m.id, m.data_ocorrencia, m.tipo_ocorrencia, m.rgo_ocorrencia, 
        m.dados_vitima, m.altitude_maxima, m.total_distancia_percorrida, m.total_tempo_voo, 
        m.data_primeira_decolagem, m.data_ultimo_pouso,
        a.prefixo AS aeronave_prefixo, a.modelo AS aeronave_modelo
    FROM missoes m
    JOIN aeronaves a ON m.aeronave_id = a.id
    WHERE m.id = ?
";
$stmt_details = $conn->prepare($sql_details);
$stmt_details->bind_param("i", $missao_id);
$stmt_details->execute();
$result_details = $stmt_details->get_result();
if ($result_details->num_rows === 1) {
    $missao_details = $result_details->fetch_assoc();
} else {
    header("Location: listar_missoes.php");
    exit();
}
$stmt_details->close();

// 2. BUSCA OS PILOTOS ENVOLVIDOS NA MISSÃO (COM ORDENAÇÃO POR GRADUAÇÃO)
$sql_pilotos = "
    SELECT p.posto_graduacao, p.nome_completo 
    FROM pilotos p 
    JOIN missoes_pilotos mp ON p.id = mp.piloto_id 
    WHERE mp.missao_id = ?
    ORDER BY
        CASE p.posto_graduacao
            WHEN 'Cel. QOBM' THEN 1
            WHEN 'Ten. Cel. QOBM' THEN 2
            WHEN 'Maj. QOBM' THEN 3
            WHEN 'Cap. QOBM' THEN 4
            WHEN '1º Ten. QOBM' THEN 5
            WHEN '2º Ten. QOBM' THEN 6
            WHEN 'Asp. Oficial' THEN 7
            WHEN 'Sub. Ten. QPBM' THEN 8
            WHEN '1º Sgt. QPBM' THEN 9
            WHEN '2º Sgt. QPBM' THEN 10
            WHEN '3º Sgt. QPBM' THEN 11
            WHEN 'Cb. QPBM' THEN 12
            WHEN 'Sd. QPBM' THEN 13
            ELSE 14
        END
";
$stmt_pilotos = $conn->prepare($sql_pilotos);
$stmt_pilotos->bind_param("i", $missao_id);
$stmt_pilotos->execute();
$result_pilotos = $stmt_pilotos->get_result();
while ($row = $result_pilotos->fetch_assoc()) {
    $pilotos_envolvidos[] = $row;
}
$stmt_pilotos->close();


// 3. BUSCA OS DADOS DE CADA VOO INDIVIDUAL (GPX)
$sql_gpx = "SELECT * FROM missoes_gpx_files WHERE missao_id = ? ORDER BY data_decolagem ASC";
$stmt_gpx = $conn->prepare($sql_gpx);
$stmt_gpx->bind_param("i", $missao_id);
$stmt_gpx->execute();
$result_gpx = $stmt_gpx->get_result();
while ($row = $result_gpx->fetch_assoc()) {
    $gpx_files_logs[] = $row;
}
$stmt_gpx->close();


// Funções de formatação
function formatarTempoVooCompleto($segundos) {
    if ($segundos <= 0) return '0min';
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $resultado = '';
    if ($horas > 0) $resultado .= $horas . 'h ';
    if ($minutos > 0) $resultado .= $minutos . 'min';
    return trim($resultado) ?: '0min';
}

function formatarDistancia($metros) {
    if ($metros < 1000) {
        return round($metros) . ' m';
    } else {
        return number_format($metros / 1000, 2, ',', '.') . ' km';
    }
}
?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <h1>Detalhes da Missão <?php echo htmlspecialchars(!empty($missao_details['rgo_ocorrencia']) ? $missao_details['rgo_ocorrencia'] : '#' . $missao_id); ?></h1>
        <a href="listar_missoes.php" style="text-decoration: none; color: #555;"><i class="fas fa-arrow-left"></i> Voltar para a Lista de Missões</a>
    </div>

    <div class="form-container">
        <?php if ($missao_details): ?>
            <fieldset class="details-fieldset">
                <legend>Detalhes da Ocorrência</legend>
                <div class="details-grid">
                    <div class="detail-item"><strong>Aeronave:</strong><p><?php echo htmlspecialchars($missao_details['aeronave_prefixo'] . ' - ' . $missao_details['aeronave_modelo']); ?></p></div>
                    <div class="detail-item"><strong>Data da Ocorrência:</strong><p><?php echo date("d/m/Y", strtotime($missao_details['data_ocorrencia'])); ?></p></div>
                    <div class="detail-item"><strong>Tipo de Ocorrência:</strong><p><?php echo htmlspecialchars($missao_details['tipo_ocorrencia']); ?></p></div>
                    <div class="detail-item"><strong>Nº RGO:</strong><p><?php echo htmlspecialchars($missao_details['rgo_ocorrencia'] ?? 'Não informado'); ?></p></div>
                    <div class="detail-item" style="grid-column: 1 / -1;"><strong>Piloto(s) Envolvido(s):</strong>
                        <ul style="margin: 0; padding-left: 20px;">
                            <?php foreach($pilotos_envolvidos as $piloto): ?>
                                <li><?php echo htmlspecialchars($piloto['posto_graduacao'] . ' ' . $piloto['nome_completo']); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                    <div class="detail-item" style="grid-column: 1 / -1;"><strong>Dados da Vítima/Alvo:</strong><p style="white-space: pre-wrap;"><?php echo htmlspecialchars($missao_details['dados_vitima'] ?? 'Não informado'); ?></p></div>
                </div>
            </fieldset>

            <fieldset class="details-fieldset">
                <legend>Logs de Voo Individuais</legend>
                <div class="table-container" style="padding: 0; box-shadow: none;">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Ficheiro GPX</th>
                                <th>Decolagem</th>
                                <th>Pouso</th>
                                <th>Duração</th>
                                <th>Distância</th>
                                <th>Altura Máx.</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($gpx_files_logs as $log): ?>
                            <tr>
                                <td style="text-align: left;">
                                    <a href="<?php echo htmlspecialchars($log['file_path']); ?>" download title="Descarregar Ficheiro Original">
                                        <i class="fas fa-file-code"></i> <?php echo htmlspecialchars($log['file_name']); ?>
                                    </a>
                                </td>
                                <td><?php echo date("d/m/Y H:i:s", strtotime($log['data_decolagem'])); ?></td>
                                <td><?php echo date("d/m/Y H:i:s", strtotime($log['data_pouso'])); ?></td>
                                <td><?php echo formatarTempoVooCompleto($log['tempo_voo']); ?></td>
                                <td><?php echo formatarDistancia($log['distancia_percorrida']); ?></td>
                                <td><?php echo round($log['altura_maxima'], 1); ?> m</td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </fieldset>

            <fieldset class="details-fieldset">
                <legend>Log Total da Missão</legend>
                 <div class="details-grid">
                    <div class="detail-item"><strong>Primeira Decolagem:</strong><p><?php echo date("d/m/Y H:i", strtotime($missao_details['data_primeira_decolagem'])); ?></p></div>
                    <div class="detail-item"><strong>Último Pouso:</strong><p><?php echo date("d/m/Y H:i", strtotime($missao_details['data_ultimo_pouso'])); ?></p></div>
                    <div class="detail-item"><strong>Tempo Total de Voo:</strong><p><?php echo formatarTempoVooCompleto($missao_details['total_tempo_voo']); ?></p></div>
                    <div class="detail-item"><strong>Distância Total Percorrida:</strong><p><?php echo formatarDistancia($missao_details['total_distancia_percorrida']); ?></p></div>
                    <div class="detail-item" style="grid-column: 1 / -1;"><strong>Altura Máxima Atingida na Missão:</strong><p><?php echo round($missao_details['altitude_maxima'], 2); ?> m</p></div>
                 </div>
            </fieldset>

        <?php else: ?>
            <div class="error-message-box">
                Missão não encontrada. Por favor, verifique o ID e tente novamente.
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .details-fieldset { border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 25px; }
    .details-fieldset legend { font-weight: 700; color: #34495e; padding: 0 10px; font-size: 1.2em; }
    .details-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
    .detail-item { background-color: #f9f9f9; padding: 15px; border-radius: 5px; border-left: 4px solid #3498db; }
    .detail-item strong { display: block; margin-bottom: 8px; color: #555; font-size: 0.9em; }
    .detail-item p { margin: 0; font-size: 1.1em; color: #333; }
    .data-table td a { color: #007bff; text-decoration: none; }
    .data-table td a:hover { text-decoration: underline; }
    .data-table td i { margin-right: 5px; }
</style>

<?php
require_once 'includes/footer.php';
?>