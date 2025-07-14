<?php
// 1. INCLUI O CABEÇALHO PADRÃO
require_once 'includes/header.php';

$missao_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
$missao_details = null;

if ($missao_id <= 0) {
    header("Location: listar_missoes.php");
    exit();
}

// 2. LÓGICA PARA BUSCAR OS DETALHES DA MISSÃO
$sql_details = "
    SELECT 
        m.*,
        a.prefixo AS aeronave_prefixo,
        a.modelo AS aeronave_modelo,
        p.nome_completo AS piloto_nome
    FROM missoes m
    JOIN aeronaves a ON m.aeronave_id = a.id
    JOIN pilotos p ON m.piloto_id = p.id
    WHERE m.id = ?
";

$stmt_details = $conn->prepare($sql_details);
$stmt_details->bind_param("i", $missao_id);
$stmt_details->execute();
$result_details = $stmt_details->get_result();

if ($result_details->num_rows === 1) {
    $missao_details = $result_details->fetch_assoc();
}
$stmt_details->close();

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
        return round($metros / 1000, 2) . ' km';
    }
}
?>

<div class="main-content">
    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 15px; margin-bottom: 20px;">
        <h1>Detalhes da Missão</h1>
        <a href="listar_missoes.php" style="text-decoration: none; color: #555;"><i class="fas fa-arrow-left"></i> Voltar para a Lista de Missões</a>
    </div>

    <div class="form-container">
        <?php if ($missao_details): ?>
            <fieldset class="details-fieldset">
                <legend>Detalhes da Ocorrência</legend>
                <div class="details-grid">
                    <div class="detail-item">
                        <strong>Aeronave:</strong>
                        <p><?php echo htmlspecialchars($missao_details['aeronave_prefixo'] . ' - ' . $missao_details['aeronave_modelo']); ?></p>
                    </div>
                    <div class="detail-item">
                        <strong>Piloto Responsável:</strong>
                        <p><?php echo htmlspecialchars($missao_details['piloto_nome']); ?></p>
                    </div>
                    <div class="detail-item">
                        <strong>Data da Ocorrência:</strong>
                        <p><?php echo date("d/m/Y", strtotime($missao_details['data_ocorrencia'])); ?></p>
                    </div>
                    <div class="detail-item">
                        <strong>Tipo de Ocorrência:</strong>
                        <p><?php echo htmlspecialchars($missao_details['tipo_ocorrencia']); ?></p>
                    </div>
                    <div class="detail-item">
                        <strong>Nº RGO:</strong>
                        <p><?php echo htmlspecialchars($missao_details['rgo_ocorrencia'] ?? 'Não informado'); ?></p>
                    </div>
                    <div class="detail-item" style="grid-column: 1 / -1;">
                        <strong>Dados da Vítima/Alvo:</strong>
                        <p style="white-space: pre-wrap;"><?php echo htmlspecialchars($missao_details['dados_vitima'] ?? 'Não informado'); ?></p>
                    </div>
                </div>
            </fieldset>

            <fieldset class="details-fieldset">
                <legend>Log de Voo</legend>
                 <div class="details-grid">
                    <div class="detail-item">
                        <strong>Primeira Decolagem:</strong>
                        <p><?php echo date("d/m/Y H:i", strtotime($missao_details['data_primeira_decolagem'])); ?></p>
                    </div>
                    <div class="detail-item">
                        <strong>Último Pouso:</strong>
                        <p><?php echo date("d/m/Y H:i", strtotime($missao_details['data_ultimo_pouso'])); ?></p>
                    </div>
                    <div class="detail-item">
                        <strong>Tempo Total de Voo:</strong>
                        <p><?php echo formatarTempoVooCompleto($missao_details['total_tempo_voo']); ?></p>
                    </div>
                    <div class="detail-item">
                        <strong>Distância Total Percorrida:</strong>
                        <p><?php echo formatarDistancia($missao_details['total_distancia_percorrida']); ?></p>
                    </div>
                    <div class="detail-item">
                        <strong>Altitude Máxima Atingida:</strong>
                        <p><?php echo round($missao_details['altitude_maxima'], 2); ?> m</p>
                    </div>
                     <div class="detail-item">
                        <strong>Altitude Mínima Atingida:</strong>
                        <p><?php echo round($missao_details['altitude_minima'], 2); ?> m</p>
                    </div>
                </div>
            </fieldset>
            
            <?php else: ?>
            <div class="error-message-box">
                Missão não encontrada. Por favor, verifique o ID e tente novamente.
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
    .details-fieldset { border: 1px solid #ddd; border-radius: 5px; padding: 20px; margin-bottom: 25px; }
    .details-fieldset legend { font-weight: 700; color: #34495e; padding: 0 10px; font-size: 1.2em; }
    .details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 25px;
    }
    .detail-item {
        background-color: #f9f9f9;
        padding: 15px;
        border-radius: 5px;
        border-left: 4px solid #3498db;
    }
    .detail-item strong {
        display: block;
        margin-bottom: 8px;
        color: #555;
        font-size: 0.9em;
    }
    .detail-item p {
        margin: 0;
        font-size: 1.1em;
        color: #333;
    }
</style>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>