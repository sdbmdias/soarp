<?php
require_once 'includes/header.php';

// --- Funções de formatação ---
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

// --- Lógica de Busca e Ordenação ---
$search_term = isset($_GET['q']) ? $conn->real_escape_string($_GET['q']) : '';
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'posto_graduacao';
$sort_order = isset($_GET['order']) && $_GET['order'] == 'desc' ? 'DESC' : 'ASC';

// Validação da coluna de ordenação
$allowed_columns = ['nome_completo', 'distancia_total', 'tempo_total', 'posto_graduacao'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'posto_graduacao';
}

// Lógica de ordenação especial para Posto/Graduação
$order_by_clause = "ORDER BY ";
if ($sort_column === 'posto_graduacao') {
    $order_by_clause .= "
        CASE p.posto_graduacao
            WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3
            WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6
            WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9
            WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12
            WHEN 'Sd. QPBM' THEN 13 ELSE 14
        END $sort_order, p.nome_completo ASC
    ";
} else {
    $order_by_clause .= " $sort_column $sort_order";
}


// Montagem da query
$sql_logbook_pilotos = "
    SELECT 
        p.posto_graduacao,
        p.nome_completo,
        SUM(m.total_tempo_voo) as tempo_total,
        SUM(m.total_distancia_percorrida) as distancia_total
    FROM pilotos p
    JOIN missoes_pilotos mp ON p.id = mp.piloto_id
    JOIN missoes m ON mp.missao_id = m.id
";

if (!empty($search_term)) {
    $sql_logbook_pilotos .= " WHERE p.nome_completo LIKE '%$search_term%' OR p.posto_graduacao LIKE '%$search_term%'";
}

$sql_logbook_pilotos .= " GROUP BY p.id, p.nome_completo, p.posto_graduacao ";
$sql_logbook_pilotos .= $order_by_clause;


$logbook_pilotos = [];
$result_logbook_pilotos = $conn->query($sql_logbook_pilotos);
if ($result_logbook_pilotos) {
    while($row = $result_logbook_pilotos->fetch_assoc()) {
        $logbook_pilotos[] = $row;
    }
}

// Helper para gerar links de ordenação
function get_sort_link_piloto($column, $current_column, $current_order) {
    $order = ($column == $current_column && $current_order == 'asc') ? 'desc' : 'asc';
    return "?sort=$column&order=$order" . (isset($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '');
}
?>

<style>
.data-table th a {
    color: inherit;
    text-decoration: none;
    display: block;
}
.data-table th a:hover {
    color: #0056b3;
}
.data-table th .sort-icon {
    margin-left: 5px;
    opacity: 0.5;
}
.action-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    margin: 2px;
    border-radius: 4px;
    text-decoration: none;
    color: #fff;
    font-size: .85em;
    transition: opacity 0.2s;
    border: none;
    cursor: pointer;
}
</style>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Logbook Geral por Piloto</h1>
        <a href="relatorios.php" style="text-decoration: none; color: #555;"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>
    <p>Este relatório apresenta o total de distância e tempo de voo acumulado para cada piloto.</p>
    
    <div class="form-container" style="margin-bottom: 30px; padding: 20px;">
        <form action="" method="GET">
            <div class="form-group" style="margin: 0;">
                <label for="q" style="font-size: 1.1em;">Buscar Pilotos:</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="q" name="q" placeholder="Buscar por nome ou posto/graduação..." value="<?php echo htmlspecialchars($search_term); ?>" style="flex-grow: 1; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                    <button type="submit" class="action-btn" style="background-color: #007bff; padding: 10px 15px; border-radius: 5px;">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="relatorio_pilotos.php" class="action-btn" style="background-color: #6c757d; padding: 10px 15px; border-radius: 5px;">
                        Limpar
                    </a>
                </div>
            </div>
        </form>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th><a href="<?php echo get_sort_link_piloto('posto_graduacao', $sort_column, $sort_order); ?>">Piloto <i class="fas fa-sort sort-icon"></i></a></th>
                    <th><a href="<?php echo get_sort_link_piloto('distancia_total', $sort_column, $sort_order); ?>">Distância Total <i class="fas fa-sort sort-icon"></i></a></th>
                    <th><a href="<?php echo get_sort_link_piloto('tempo_total', $sort_column, $sort_order); ?>">Tempo de Voo <i class="fas fa-sort sort-icon"></i></a></th>
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
require_once 'includes/footer.php';
?>