<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA PARA BUSCAR OS DADOS DO LOGBOOK ACUMULADO
$logbook_geral = [];
$sql_logbook = "
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

$result_logbook = $conn->query($sql_logbook);
if ($result_logbook) {
    while($row = $result_logbook->fetch_assoc()) {
        $logbook_geral[] = $row;
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

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Relatórios Gerais</h1>
    </div>

    <div class="table-container">
        <h2><i class="fas fa-book"></i> Logbook Geral por Aeronave</h2>
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
</div>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>