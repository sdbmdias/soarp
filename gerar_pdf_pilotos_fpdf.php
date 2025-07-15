<?php
// 1. Incluir os arquivos necessários (caminho corrigido)
require_once 'includes/database.php';
require_once 'libs/fpdf/fpdf.php';

// 2. Lógica para buscar os dados dos pilotos (mesma lógica dos filtros)
$where_clauses = [];
$params = [];
$types = '';
$report_title = "Lista de Pilotos"; // Título padrão

$sql = "SELECT posto_graduacao, nome_completo, cpf, crbm_piloto, obm_piloto, status_piloto FROM pilotos";

if (!empty($_GET['graduacao'])) { $where_clauses[] = "posto_graduacao = ?"; $params[] = $_GET['graduacao']; $types .= 's'; }
if (!empty($_GET['crbm_piloto'])) { $where_clauses[] = "crbm_piloto = ?"; $params[] = $_GET['crbm_piloto']; $types .= 's'; }
if (!empty($_GET['obm_piloto'])) { $where_clauses[] = "obm_piloto = ?"; $params[] = $_GET['obm_piloto']; $types .= 's'; }
if (!empty($_GET['status_piloto'])) { $where_clauses[] = "status_piloto = ?"; $params[] = $_GET['status_piloto']; $types .= 's'; }

if (count($where_clauses) > 0) {
    $sql .= " WHERE " . implode(' AND ', $where_clauses);
}

// Ordenação consistente com a página de relatórios
$sql .= " ORDER BY CASE posto_graduacao WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3 WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6 WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9 WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12 WHEN 'Sd. QPBM' THEN 13 ELSE 14 END, nome_completo ASC";

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

// 3. Classe para criar o PDF com Cabeçalho e Rodapé personalizados
class PDF extends FPDF
{
    // Cabeçalho
    function Header()
    {
        // Logo (opcional, adicione a sua imagem em /img/logo.png)
        // $this->Image('img/logo.png',10,6,30); 
        $this->SetFont('Arial','B',15);
        $this->Cell(80); // Mover para a direita
        $this->Cell(30,10,utf8_decode('Relatório de Pilotos'),0,0,'C');
        $this->Ln(20); // Pular linha
    }

    // Rodapé
    function Footer()
    {
        $this->SetY(-15); // Posição a 1.5 cm do final
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }
}

// 4. Geração do PDF
$pdf = new PDF();
$pdf->AliasNbPages(); // Habilita a contagem total de páginas no rodapé
$pdf->AddPage();
$pdf->SetFont('Arial','B',9);

// Definição das larguras das colunas
$w = [35, 60, 25, 20, 20, 25];

// Cabeçalho da Tabela
$header = ['Posto/Grad.', 'Nome Completo', 'CPF', 'CRBM', 'OBM', 'Status'];
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('Arial','',8);
if (!empty($resultados)) {
    foreach($resultados as $row) {
        $pdf->Cell($w[0], 6, utf8_decode($row['posto_graduacao']), 'LR');
        $pdf->Cell($w[1], 6, utf8_decode($row['nome_completo']), 'LR');
        $pdf->Cell($w[2], 6, utf8_decode(substr($row['cpf'], 0, 3) . '.XXX.XXX-' . substr($row['cpf'], -2)), 'LR');
        $pdf->Cell($w[3], 6, utf8_decode($row['crbm_piloto']), 'LR', 0, 'C');
        $pdf->Cell($w[4], 6, utf8_decode($row['obm_piloto']), 'LR', 0, 'C');
        $pdf->Cell($w[5], 6, utf8_decode(ucfirst($row['status_piloto'])), 'LR', 0, 'C');
        $pdf->Ln();
    }
} else {
    $pdf->Cell(array_sum($w), 10, utf8_decode('Nenhum resultado encontrado'), 1, 1, 'C');
}

// Linha de fechamento da tabela
$pdf->Cell(array_sum($w),0,'','T');

// 5. Saída do PDF
$pdf->Output('I', 'Relatorio_Pilotos_SOARP.pdf');
?>