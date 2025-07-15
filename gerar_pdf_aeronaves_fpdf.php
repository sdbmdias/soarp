<?php
// 1. Incluir os arquivos necessários
require_once 'includes/database.php';
require_once 'libs/fpdf/fpdf.php';

// 2. Lógica para buscar os dados das aeronaves
$aeronaves = [];
// Assumindo que o PDF será gerado por um administrador, buscando todas as aeronaves.
// Se fosse para permitir filtros, a lógica aqui seria mais complexa, similar à de pilotos.
$sql_aeronaves = "SELECT id, prefixo, fabricante, modelo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, status, homologacao_anatel FROM aeronaves ORDER BY prefixo ASC";
$result_aeronaves = $conn->query($sql_aeronaves);

if ($result_aeronaves && $result_aeronaves->num_rows > 0) {
    while ($row = $result_aeronaves->fetch_assoc()) {
        $aeronaves[] = $row;
    }
}
$conn->close();

// Função auxiliar para formatar status
function formatarStatusAeronave($status) {
    $status_map = ['ativo' => 'Ativa', 'em_manutencao' => 'Em Manutenção', 'baixada' => 'Baixada', 'adida' => 'Adida', 'desativado' => 'Desativada'];
    return $status_map[$status] ?? ucfirst($status);
}

// 3. Classe para criar o PDF
class PDF extends FPDF
{
    // Cabeçalho
    function Header()
    {
        $this->SetFont('Arial','B',15);
        $this->Cell(0,10,utf8_decode('Relatório de Aeronaves'),0,1,'C');
        $this->Ln(10); // Pular linha
    }

    // Rodapé
    function Footer()
    {
        $this->SetY(-15); // Posição a 1.5 cm do final
        $this->SetFont('Arial','I',8);
        $this->Cell(0,10,utf8_decode('Página ').$this->PageNo().'/{nb}',0,0,'C');
    }

    // NOVO: Método público para acessar a margem de quebra de página automática
    function GetAutoPageBreakMargin()
    {
        return $this->bMargin;
    }
}

// 4. Geração do PDF
$pdf = new PDF('L'); // Layout Paisagem (Landscape) para mais colunas
$pdf->AliasNbPages(); // Habilita a contagem total de páginas no rodapé
$pdf->AddPage();
$pdf->SetFont('Arial','B',8);

// Definição das larguras das colunas
// Ajustado para 9 colunas e largura total de aprox 277 (A4 paisagem)
$w = [30, 45, 30, 35, 30, 25, 20, 25, 30]; 

// Cabeçalho da Tabela
$header = ['Prefixo', 'Fabricante/Modelo', 'Nº Série', 'SISANT (Val.)', 'Lotação', 'Tipo', 'PMD (kg)', 'Status', 'ANATEL'];
for($i=0; $i<count($header); $i++) {
    $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
}
$pdf->Ln();

// Dados da Tabela
$pdf->SetFont('Arial','',7); // Fonte menor para os dados
if (!empty($aeronaves)) {
    foreach($aeronaves as $aeronave) {
        $row_height = 6; // Altura padrão da linha
        
        // Adicionar uma nova página se a linha atual exceder o limite
        // Correção: Agora usa o método público GetAutoPageBreakMargin()
        if($pdf->GetY() + $row_height > ($pdf->GetPageHeight() - $pdf->GetAutoPageBreakMargin())) { 
            $pdf->AddPage();
            $pdf->SetFont('Arial','B',8); // Restaura fonte para cabeçalho
            for($i=0; $i<count($header); $i++) {
                $pdf->Cell($w[$i], 7, utf8_decode($header[$i]), 1, 0, 'C');
            }
            $pdf->Ln();
            $pdf->SetFont('Arial','',7); // Restaura fonte para dados
        }

        $pdf->Cell($w[0], $row_height, utf8_decode($aeronave['prefixo'] ?? 'N/A'), 'LR', 0, 'C');
        $pdf->Cell($w[1], $row_height, utf8_decode(($aeronave['fabricante'] ?? 'N/A') . ' / ' . ($aeronave['modelo'] ?? 'N/A')), 'LR', 0, 'C');
        $pdf->Cell($w[2], $row_height, utf8_decode($aeronave['numero_serie'] ?? 'N/A'), 'LR', 0, 'C');
        
        $sisant_val = ($aeronave['cadastro_sisant'] ?? 'N/A') . ' (' . (isset($aeronave['validade_sisant']) ? date("d/m/Y", strtotime($aeronave['validade_sisant'])) : 'N/A') . ')';
        $pdf->Cell($w[3], $row_height, utf8_decode($sisant_val), 'LR', 0, 'C');
        
        $crbm_formatado = preg_replace('/(\d)(CRBM)/', '$1º $2', $aeronave['crbm'] ?? 'N/A');
        $lotacao = $crbm_formatado . ' / ' . ($aeronave['obm'] ?? 'N/A');
        $pdf->Cell($w[4], $row_height, utf8_decode($lotacao), 'LR', 0, 'C');
        
        $pdf->Cell($w[5], $row_height, utf8_decode(ucfirst(str_replace('_', '-', $aeronave['tipo_drone'] ?? 'N/A'))), 'LR', 0, 'C');
        $pdf->Cell($w[6], $row_height, utf8_decode($aeronave['pmd_kg'] ?? 'N/A'), 'LR', 0, 'C');
        $pdf->Cell($w[7], $row_height, utf8_decode(formatarStatusAeronave($aeronave['status'] ?? 'desconhecido')), 'LR', 0, 'C');
        $pdf->Cell($w[8], $row_height, utf8_decode($aeronave['homologacao_anatel'] ?? 'Não'), 'LR', 0, 'C');
        $pdf->Ln();
    }
} else {
    $pdf->Cell(array_sum($w), 10, utf8_decode('Nenhuma aeronave encontrada'), 1, 1, 'C');
}

// Linha de fechamento da tabela
$pdf->Cell(array_sum($w),0,'','T');

// 5. Saída do PDF
$pdf->Output('I', 'Relatorio_Aeronaves_SOARP.pdf');
?>