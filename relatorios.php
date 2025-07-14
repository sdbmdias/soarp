<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA PARA BUSCAR OS DADOS DO LOGBOOK POR AERONAVE
$logbook_geral = [];
$sql_logbook_aeronave = "
    SELECT 
        a.id,
        a.prefixo,
        a.modelo,
        lb.distancia_total_acumulada,
        lb.tempo_voo_total_acumulado
    FROM aeronaves_logbook lb
    JOIN aeronaves a ON lb.aeronave_id = a.id
    ORDER BY a.prefixo ASC
";

$result_logbook_aeronave = $conn->query($sql_logbook_aeronave);
if ($result_logbook_aeronave) {
    while($row = $result_logbook_aeronave->fetch_assoc()) {
        $logbook_geral[] = $row;
    }
}

// ### NOVA LÓGICA: BUSCAR DADOS DO LOGBOOK POR PILOTO ###
$logbook_pilotos = [];
$sql_logbook_pilotos = "
    SELECT 
        p.posto_graduacao,
        p.nome_completo,
        SUM(m.total_tempo_voo) as tempo_total,
        SUM(m.total_distancia_percorrida) as distancia_total
    FROM pilotos p
    JOIN missoes_pilotos mp ON p.id = mp.piloto_id
    JOIN missoes m ON mp.missao_id = m.id
    GROUP BY p.id, p.nome_completo, p.posto_graduacao
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
        END,
        p.nome_completo ASC
";

$result_logbook_pilotos = $conn->query($sql_logbook_pilotos);
if ($result_logbook_pilotos) {
    while($row = $result_logbook_pilotos->fetch_assoc()) {
        $logbook_pilotos[] = $row;
    }
}

// Funções de formatação para exibição
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
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Relatórios Gerais</h1>
    </div>

    <div class="table-container" style="margin-bottom: 40px;">
        <h2><i class="fas fa-plane"></i> Logbook Geral por Aeronave</h2>
        <p>Este relatório apresenta o total de distância e tempo de voo acumulado para cada aeronave desde o seu primeiro registro de missão.</p>
        
        <table class="data-table" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th>Aeronave (HAWK)</th>
                    <th>Modelo</th>
                    <th>Distância Total Percorrida</th>
                    <th>Tempo Total de Voo Acumulado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logbook_geral)): ?>
                    <?php foreach ($logbook_geral as $log): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($log['prefixo']); ?></td>
                            <td><?php echo htmlspecialchars($log['modelo']); ?></td>
                            <td><?php echo formatarDistancia($log['distancia_total_acumulada']); ?></td>
                            <td><?php echo formatarTempoVooCompleto($log['tempo_voo_total_acumulado']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="4">Nenhum dado de logbook encontrado. Registre uma missão para começar a gerar relatórios.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="table-container">
        <h2><i class="fas fa-users"></i> Logbook Geral por Piloto</h2>
        <p>Este relatório apresenta o total de distância e tempo de voo acumulado para cada piloto em todas as missões participadas.</p>
        
        <table class="data-table" style="margin-top: 20px;">
            <thead>
                <tr>
                    <th>Piloto</th>
                    <th>Distância Total Percorrida</th>
                    <th>Tempo Total de Voo Acumulado</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($logbook_pilotos)): ?>
                    <?php foreach ($logbook_pilotos as $log): ?>
                        <tr>
                            <td style="text-align: left;">
                                <strong><?php echo htmlspecialchars($log['posto_graduacao']); ?></strong> <?php echo htmlspecialchars($log['nome_completo']); ?>
                            </td>
                            <td><?php echo formatarDistancia($log['distancia_total']); ?></td>
                            <td><?php echo formatarTempoVooCompleto($log['tempo_total']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="3">Nenhum dado de voo encontrado para os pilotos.</td>
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