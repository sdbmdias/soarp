<?php
// PASSO ESSENCIAL: Carrega todas as bibliotecas instaladas pelo Composer
require_once 'vendor/autoload.php';
require_once 'includes/database.php';

// --- Lógica para buscar os dados dos pilotos (idêntica à da página de relatórios) ---
$where_clauses = [];
$params = [];
$types = '';

$sql = "SELECT posto_graduacao, nome_completo, cpf, crbm_piloto, obm_piloto, status_piloto FROM pilotos";

if (!empty($_GET['graduacao'])) { $where_clauses[] = "posto_graduacao = ?"; $params[] = $_GET['graduacao']; $types .= 's'; }
if (!empty($_GET['crbm_piloto'])) { $where_clauses[] = "crbm_piloto = ?"; $params[] = $_GET['crbm_piloto']; $types .= 's'; }
if (!empty($_GET['obm_piloto'])) { $where_clauses[] = "obm_piloto = ?"; $params[] = $_GET['obm_piloto']; $types .= 's'; }
if (!empty($_GET['status_piloto'])) { $where_clauses[] = "status_piloto = ?"; $params[] = $_GET['status_piloto']; $types .= 's'; }

if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}
$sql .= " ORDER BY nome_completo ASC";

$stmt = $conn->prepare($sql);
if ($stmt) {
    if (count($params) > 0) {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $resultados = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} else {
    die("Erro na preparação da consulta SQL.");
}
$conn->close();

// --- Construção da Tabela HTML com os dados ---
$tableRows = '';
if (!empty($resultados)) {
    foreach ($resultados as $piloto) {
        $tableRows .= '<tr>';
        $tableRows .= '<td>' . htmlspecialchars($piloto['posto_graduacao']) . '</td>';
        $tableRows .= '<td>' . htmlspecialchars($piloto['nome_completo']) . '</td>';
        $tableRows .= '<td>' . htmlspecialchars(substr($piloto['cpf'], 0, 3) . '.XXX.XXX-' . substr($piloto['cpf'], -2)) . '</td>';
        $tableRows .= '<td>' . htmlspecialchars($piloto['crbm_piloto']) . '</td>';
        $tableRows .= '<td>' . htmlspecialchars($piloto['obm_piloto']) . '</td>';
        $tableRows .= '<td>' . htmlspecialchars(ucfirst($piloto['status_piloto'])) . '</td>';
        $tableRows .= '</tr>';
    }
} else {
    $tableRows = '<tr><td colspan="6" style="text-align:center;">Nenhum piloto encontrado para os filtros selecionados.</td></tr>';
}

// --- Geração do PDF a partir do Molde HTML ---

try {
    // Cria uma nova instância da biblioteca Mpdf
    $mpdf = new \Mpdf\Mpdf(['tempDir' => __DIR__ . '/tmp']);

    // Captura o conteúdo do seu ficheiro de molde HTML
    ob_start();
    include 'template_relatorio_pilotos.php';
    $html_template = ob_get_clean();

    // Substitui o marcador no tbody pelos dados da tabela
    $final_html = str_replace('', $tableRows, $html_template);

    // Escreve o HTML final no documento PDF
    $mpdf->WriteHTML($final_html);

    // Envia o PDF para o navegador para ser visualizado ou descarregado
    $mpdf->Output('relatorio_pilotos.pdf', 'I'); // 'I' para inline (visualizar), 'D' para download

} catch (\Mpdf\MpdfException $e) {
    // Exibe um erro detalhado se a Mpdf falhar
    die ('Mpdf Exception: ' . $e->getMessage());
}

exit;
?>