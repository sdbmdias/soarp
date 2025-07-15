<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// --- LÓGICA PARA LISTAR OS PILOTOS COM FILTRO CRBM ---
$pilotos = [];
$where_clauses = [];
$params = [];
$types = '';

// Adiciona filtro por CRBM se o parâmetro estiver presente na URL
if (isset($_GET['crbm']) && !empty($_GET['crbm'])) {
    $where_clauses[] = "crbm_piloto = ?";
    $params[] = $_GET['crbm'];
    $types .= 's';
}

$sql_pilotos = "SELECT id, posto_graduacao, nome_completo, cpf, crbm_piloto, obm_piloto, status_piloto FROM pilotos";

if (!empty($where_clauses)) {
    $sql_pilotos .= " WHERE " . implode(' AND ', $where_clauses);
}

$sql_pilotos .= " ORDER BY CASE posto_graduacao WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3 WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6 WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9 WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12 WHEN 'Sd. QPBM' THEN 13 ELSE 14 END, nome_completo ASC";

// Prepara e executa a consulta
$stmt_pilotos = $conn->prepare($sql_pilotos);
if ($stmt_pilotos) {
    if (!empty($params)) {
        $stmt_pilotos->bind_param($types, ...$params);
    }
    $stmt_pilotos->execute();
    $result_pilotos = $stmt_pilotos->get_result();
    if ($result_pilotos && $result_pilotos->num_rows > 0) {
        while ($row = $result_pilotos->fetch_assoc()) {
            $pilotos[] = $row;
        }
    }
    $stmt_pilotos->close();
} else {
    // Tratar erro na preparação da consulta
    die("Erro na preparação da consulta de pilotos: " . $conn->error);
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
    <h1>Logbook por Piloto<?php echo $title_crbm_suffix; ?></h1>
    
    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Posto/Grad.</th>
                    <th>Nome Completo</th>
                    <th>CPF</th>
                    <th>CRBM</th>
                    <th>OBM</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($pilotos)): ?>
                    <?php foreach ($pilotos as $piloto): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($piloto['posto_graduacao'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($piloto['nome_completo'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($piloto['cpf'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($piloto['crbm_piloto'] ?? 'N/A'); ?></td>
                            <td><?php echo htmlspecialchars($piloto['obm_piloto'] ?? 'N/A'); ?></td>
                            <td>
                                <?php
                                $status_map = ['ativo' => 'Ativo', 'afastado' => 'Afastado', 'desativado' => 'Desativado'];
                                $status = $piloto['status_piloto'] ?? 'N/A';
                                $status_texto = $status_map[$status] ?? ucfirst($status);
                                ?>
                                <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="6">Nenhum piloto encontrado<?php echo $title_crbm_suffix; ?>.</td>
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