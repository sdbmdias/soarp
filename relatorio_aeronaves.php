<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// --- LÓGICA PARA LISTAR AS AERONAVES COM FILTRO CRBM ---
$aeronaves = [];
$where_clauses = [];
$params = [];
$types = '';

// Adiciona filtro por CRBM se o parâmetro estiver presente na URL
if (isset($_GET['crbm']) && !empty($_GET['crbm'])) {
    $where_clauses[] = "crbm = ?";
    $params[] = $_GET['crbm'];
    $types .= 's';
}

$sql_aeronaves = "SELECT id, prefixo, fabricante, modelo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, status, homologacao_anatel FROM aeronaves";

if (!empty($where_clauses)) {
    $sql_aeronaves .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql_aeronaves .= " ORDER BY prefixo ASC";

// Prepara e executa a consulta
$stmt_aeronaves = $conn->prepare($sql_aeronaves);
if ($stmt_aeronaves) {
    if (!empty($params)) {
        $stmt_aeronaves->bind_param($types, ...$params);
    }
    $stmt_aeronaves->execute();
    $result_aeronaves = $stmt_aeronaves->get_result();
    if ($result_aeronaves && $result_aeronaves->num_rows > 0) {
        while ($row = $result_aeronaves->fetch_assoc()) {
            $aeronaves[] = $row;
        }
    }
    $stmt_aeronaves->close();
} else {
    // Tratar erro na preparação da consulta
    die("Erro na preparação da consulta de aeronaves: " . $conn->error);
}
$conn->close(); // Fechar conexão após todas as consultas necessárias
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
    <?php
    $title_crbm_suffix = '';
    $current_crbm_filter = '';

    if (isset($_GET['crbm']) && !empty($_GET['crbm'])) {
        $current_crbm_filter = $_GET['crbm'];
    }

    if (!empty($current_crbm_filter)) {
        if ($current_crbm_filter === 'GOST') {
            $title_crbm_suffix = ' - GOST';
        } else {
            $title_crbm_suffix = ' - CRBM ' . htmlspecialchars($current_crbm_filter);
        }
    }
    ?>
    <h1>Logbook por Aeronave<?php echo $title_crbm_suffix; ?></h1>
    
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
                    <th>ANATEL</th>
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
                                $raw_validade = $aeronave['validade_sisant'] ?? null; // Obtém o valor bruto
                                $validade_formatada = 'N/A'; // Inicializa a variável
                                if (!empty($raw_validade)) { // Verifica se há um valor antes de formatar
                                    $validade_formatada = date("d/m/Y", strtotime($raw_validade));
                                }
                                echo htmlspecialchars($sisant) . ' (' . htmlspecialchars($validade_formatada) . ')';
                                ?>
                            </td>
                            <td>
                                <?php 
                                    $crbm_formatado = preg_replace('/(\d)(CRBM)/', '$1º $2', $aeronave['crbm'] ?? 'N/A');
                                    echo htmlspecialchars($crbm_formatado . ' / ' . ($aeronave['obm'] ?? 'N/A'));
                                ?>
                            </td>
                            <td><?php echo htmlspecialchars(ucfirst(str_replace('_', '-', $aeronave['tipo_drone'] ?? 'N/A'))); ?></td>
                            <td><?php echo htmlspecialchars($aeronave['pmd_kg'] ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                $status_map = ['ativo' => 'Ativa', 'em_manutencao' => 'Em Manutenção', 'baixada' => 'Baixada', 'adida' => 'Adida'];
                                $status = $aeronave['status'] ?? 'desconhecido';
                                $status_texto = $status_map[$status] ?? ucfirst($status);
                                ?>
                                <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                            </td>
                            <td><?php echo htmlspecialchars($aeronave['homologacao_anatel'] ?? 'Não'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="9">Nenhuma aeronave encontrada<?php echo $title_crbm_suffix; ?>.</td>
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