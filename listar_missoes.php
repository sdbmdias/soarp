<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

// 2. LÓGICA PARA BUSCAR AS MISSÕES
$missoes = [];
$sql_missoes = "
    SELECT 
        m.id,
        m.data_ocorrencia,
        m.tipo_ocorrencia,
        m.rgo_ocorrencia,
        m.total_tempo_voo,
        a.prefixo AS aeronave_prefixo,
        p.nome_completo AS piloto_nome
    FROM missoes m
    JOIN aeronaves a ON m.aeronave_id = a.id
    JOIN pilotos p ON m.piloto_id = p.id
    ORDER BY m.data_ocorrencia DESC, m.id DESC
";

$result_missoes = $conn->query($sql_missoes);
if ($result_missoes) {
    while($row = $result_missoes->fetch_assoc()) {
        $missoes[] = $row;
    }
}

/**
 * Função para converter segundos em formato de horas e minutos.
 * @param int $segundos O total de segundos.
 * @return string O tempo formatado como "Xh Ymin".
 */
function formatarTempoVoo($segundos) {
    if ($segundos <= 0) {
        return '0min';
    }
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    
    $resultado = '';
    if ($horas > 0) {
        $resultado .= $horas . 'h ';
    }
    if ($minutos > 0) {
        $resultado .= $minutos . 'min';
    }
    return trim($resultado);
}
?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h1>Logbook de Missões</h1>
        <a href="cadastro_missao.php" class="form-actions button" style="text-decoration: none; display: inline-block; padding: 10px 20px; background-color: #28a745; color: #fff;">
            <i class="fas fa-plus"></i> Adicionar Nova Missão
        </a>
    </div>

    <div class="table-container">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Data da Ocorrência</th>
                    <th>Aeronave (HAWK)</th>
                    <th>Piloto Responsável</th>
                    <th>Tipo de Ocorrência</th>
                    <th>RGO</th>
                    <th>Tempo de Voo</th>
                    <th>Ações</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($missoes)): ?>
                    <?php foreach ($missoes as $missao): ?>
                        <tr>
                            <td><?php echo date("d/m/Y", strtotime($missao['data_ocorrencia'])); ?></td>
                            <td><?php echo htmlspecialchars($missao['aeronave_prefixo']); ?></td>
                            <td><?php echo htmlspecialchars($missao['piloto_nome']); ?></td>
                            <td><?php echo htmlspecialchars($missao['tipo_ocorrencia']); ?></td>
                            <td><?php echo htmlspecialchars($missao['rgo_ocorrencia'] ?? 'N/A'); ?></td>
                            <td><?php echo formatarTempoVoo($missao['total_tempo_voo']); ?></td>
                            <td class="action-buttons">
                                <a href="ver_missao.php?id=<?php echo $missao['id']; ?>" class="edit-btn" style="background-color: #6c757d;">Ver Detalhes</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7">Nenhuma missão encontrada. Clique em "Adicionar Nova Missão" para começar.</td>
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