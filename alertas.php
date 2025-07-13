<?php
// 1. INCLUI O CABEÇALHO E AS VERIFICAÇÕES PADRÃO
require_once 'includes/header.php';

// Apenas administradores podem ver esta página, então adicionamos uma verificação extra.
if (!$isAdmin) {
    // Redireciona para o dashboard se não for admin
    header("Location: dashboard.php");
    exit();
}

// 2. LÓGICA ESPECÍFICA DA PÁGINA DE ALERTAS
$alertas_proximos = [];
$alertas_vencidos = [];

// Busca todas as aeronaves para verificar a validade do SISANT
$sql = "SELECT prefixo, modelo, validade_sisant FROM aeronaves ORDER BY validade_sisant ASC";
$resultado = $conn->query($sql);

$hoje = new DateTime();
$hoje->setTime(0, 0, 0); // Zera o tempo para comparar apenas as datas

if ($resultado && $resultado->num_rows > 0) {
    while($aeronave = $resultado->fetch_assoc()) {
        if (!empty($aeronave['validade_sisant'])) {
            $validade_data = new DateTime($aeronave['validade_sisant']);
            
            if ($validade_data < $hoje) {
                // Se a data de validade for anterior a hoje, está vencido
                $intervalo = $hoje->diff($validade_data);
                $aeronave['dias'] = $intervalo->days;
                $alertas_vencidos[] = $aeronave;
            } else {
                // Se a data de validade for hoje ou no futuro
                $intervalo = $hoje->diff($validade_data);
                $dias_para_vencer = $intervalo->days;

                // Adiciona à lista de próximos se estiver a 15 dias ou menos de vencer
                if ($dias_para_vencer <= 15) {
                    $aeronave['dias'] = $dias_para_vencer;
                    $alertas_proximos[] = $aeronave;
                }
            }
        }
    }
}
?>

<div class="main-content">
    <h1>Alertas de Vencimento do SISANT</h1>

    <div class="alerts-container">
        <h2><i class="fas fa-exclamation-triangle"></i> Vencimentos Próximos (Próximos 15 dias)</h2>
        <ul class="alert-list">
            <?php if (!empty($alertas_proximos)): ?>
                <?php foreach ($alertas_proximos as $alerta): ?>
                    <li class="alert-item proximo">
                        <i class="fas fa-clock"></i>
                        <div class="alert-details">
                            <strong>Aeronave:</strong> <?php echo htmlspecialchars($alerta['prefixo']) . " (" . htmlspecialchars($alerta['modelo']) . ")"; ?><br>
                            O SISANT vence em <strong><?php echo $alerta['dias']; ?> dia(s)</strong>, na data de <?php echo date("d/m/Y", strtotime($alerta['validade_sisant'])); ?>.
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="no-alerts">Nenhum SISANT com vencimento nos próximos 15 dias.</li>
            <?php endif; ?>
        </ul>

        <h2 style="margin-top: 40px;"><i class="fas fa-times-circle"></i> Documentos Vencidos</h2>
        <ul class="alert-list">
            <?php if (!empty($alertas_vencidos)): ?>
                <?php foreach ($alertas_vencidos as $alerta): ?>
                    <li class="alert-item vencido">
                        <i class="fas fa-calendar-times"></i>
                        <div class="alert-details">
                            <strong>Aeronave:</strong> <?php echo htmlspecialchars($alerta['prefixo']) . " (" . htmlspecialchars($alerta['modelo']) . ")"; ?><br>
                            O SISANT está <strong>vencido há <?php echo $alerta['dias']; ?> dia(s)</strong>. A data de validade era <?php echo date("d/m/Y", strtotime($alerta['validade_sisant'])); ?>.
                        </div>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="no-alerts">Nenhuma aeronave com SISANT vencido.</li>
            <?php endif; ?>
        </ul>
    </div>
</div>

<?php
// 4. INCLUI O RODAPÉ
require_once 'includes/footer.php';
?>