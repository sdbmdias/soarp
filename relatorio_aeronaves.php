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
$sort_column = isset($_GET['sort']) ? $_GET['sort'] : 'prefixo';
$sort_order = isset($_GET['order']) && $_GET['order'] == 'desc' ? 'DESC' : 'ASC';

// Validação da coluna de ordenação para evitar SQL Injection
$allowed_columns = ['prefixo', 'modelo', 'distancia_total_acumulada', 'tempo_voo_total_acumulado'];
if (!in_array($sort_column, $allowed_columns)) {
    $sort_column = 'prefixo';
}

// Montagem da query
$sql_logbook_aeronave = "
    SELECT 
        a.id,
        a.prefixo,
        a.modelo,
        lb.distancia_total_acumulada,
        lb.tempo_voo_total_acumulado
    FROM aeronaves_logbook lb
    JOIN aeronaves a ON lb.aeronave_id = a.id
";

if (!empty($search_term)) {
    $sql_logbook_aeronave .= " WHERE a.prefixo LIKE '%$search_term%' OR a.modelo LIKE '%$search_term%'";
}

$sql_logbook_aeronave .= " ORDER BY $sort_column $sort_order";

$logbook_geral = [];
$result_logbook_aeronave = $conn->query($sql_logbook_aeronave);
if ($result_logbook_aeronave) {
    while($row = $result_logbook_aeronave->fetch_assoc()) {
        $logbook_geral[] = $row;
    }
}

// Helper para gerar links de ordenação
function get_sort_link($column, $current_column, $current_order) {
    $order = ($column == $current_column && $current_order == 'asc') ? 'desc' : 'asc';
    return "?sort=$column&order=$order" . (isset($_GET['q']) ? '&q=' . urlencode($_GET['q']) : '');
}
?>

<style>
.search-container {
    margin-bottom: 20px;
    padding: 15px;
    background-color: #f8f9fa;
    border-radius: 5px;
}
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
        <h1>Logbook Geral por Aeronave</h1>
        <a href="relatorios.php" style="text-decoration: none; color: #555;"><i class="fas fa-arrow-left"></i> Voltar</a>
    </div>
    <p>Este relatório apresenta o total de distância e tempo de voo acumulado para cada aeronave.</p>
    
    <div class="form-container" style="margin-bottom: 30px; padding: 20px;">
        <form action="" method="GET">
            <div class="form-group" style="margin: 0;">
                <label for="q" style="font-size: 1.1em;">Buscar Aeronaves:</label>
                <div style="display: flex; gap: 10px;">
                    <input type="text" id="q" name="q" placeholder="Buscar por prefixo ou modelo..." value="<?php echo htmlspecialchars($search_term); ?>" style="flex-grow: 1; padding: 10px; border: 1px solid #ccc; border-radius: 5px;">
                    <button type="submit" class="action-btn" style="background-color: #007bff; padding: 10px 15px; border-radius: 5px;">
                        <i class="fas fa-search"></i>
                    </button>
                    <a href="relatorio_aeronaves.php" class="action-btn" style="background-color: #6c757d; padding: 10px 15px; border-radius: 5px;">
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
                    <th><a href="<?php echo get_sort_link('prefixo', $sort_column, $sort_order); ?>">Aeronave (HAWK) <i class="fas fa-sort sort-icon"></i></a></th>
                    <th><a href="<?php echo get_sort_link('modelo', $sort_column, $sort_order); ?>">Modelo <i class="fas fa-sort sort-icon"></i></a></th>
                    <th><a href="<?php echo get_sort_link('distancia_total_acumulada', $sort_column, $sort_order); ?>">Distância Total <i class="fas fa-sort sort-icon"></i></a></th>
                    <th><a href="<?php echo get_sort_link('tempo_voo_total_acumulado', $sort_column, $sort_order); ?>">Tempo de Voo <i class="fas fa-sort sort-icon"></i></a></th>
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
                        <td colspan="4">Nenhum dado de logbook encontrado.</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once 'includes/footer.php';
?>