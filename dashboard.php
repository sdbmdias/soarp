<?php
// 1. INCLUI O CABEÇALHO E AS VERIFICAÇÕES DE SEGURANÇA
require_once 'includes/header.php';

// 2. LÓGICA ESPECÍFICA DO DASHBOARD
$total_pilotos = 0;
$total_aeronaves = 0;
$total_aeronaves_crbm = 0;
$total_missoes = 0;
$total_manutencoes = 0;

// Busca a contagem total de aeronaves para todos
$sql_total_aeronaves = "SELECT COUNT(id) AS total FROM aeronaves";
$result_total_aeronaves = $conn->query($sql_total_aeronaves);
if ($result_total_aeronaves->num_rows > 0) {
    $total_aeronaves = $result_total_aeronaves->fetch_assoc()['total'];
}

// Lógica para Administrador
if ($isAdmin) {
    // Conta total de pilotos
    $sql_total_pilotos = "SELECT COUNT(id) AS total FROM pilotos";
    $result_total_pilotos = $conn->query($sql_total_pilotos);
    if ($result_total_pilotos->num_rows > 0) {
        $total_pilotos = $result_total_pilotos->fetch_assoc()['total'];
    }
    // Conta total de missões
    $sql_total_missoes = "SELECT COUNT(id) AS total FROM missoes";
    $result_total_missoes = $conn->query($sql_total_missoes);
    if ($result_total_missoes->num_rows > 0) {
        $total_missoes = $result_total_missoes->fetch_assoc()['total'];
    }
    // Conta total de manutenções
    $sql_total_manutencoes = "SELECT COUNT(id) AS total FROM manutencoes";
    $result_total_manutencoes = $conn->query($sql_total_manutencoes);
    if ($result_total_manutencoes->num_rows > 0) {
        $total_manutencoes = $result_total_manutencoes->fetch_assoc()['total'];
    }

} 
// Lógica para Piloto
elseif ($isPiloto) {
    $crbm_do_usuario = '';
    $obm_do_usuario = '';
    
    // Busca CRBM e OBM do piloto logado
    $stmt_piloto_info = $conn->prepare("SELECT crbm_piloto, obm_piloto FROM pilotos WHERE id = ?");
    $stmt_piloto_info->bind_param("i", $_SESSION['user_id']);
    if ($stmt_piloto_info->execute()) {
        $result_piloto_info = $stmt_piloto_info->get_result();
        if ($result_piloto_info->num_rows > 0) {
            $piloto_info = $result_piloto_info->fetch_assoc();
            $crbm_do_usuario = $piloto_info['crbm_piloto'];
            $obm_do_usuario = $piloto_info['obm_piloto'];
        }
    }
    $stmt_piloto_info->close();

    // Conta aeronaves no CRBM do piloto
    if (!empty($crbm_do_usuario)) {
        $stmt_acft_crbm = $conn->prepare("SELECT COUNT(id) AS total FROM aeronaves WHERE crbm = ?");
        $stmt_acft_crbm->bind_param("s", $crbm_do_usuario);
        if ($stmt_acft_crbm->execute()) {
            $result_acft_crbm = $stmt_acft_crbm->get_result();
            $total_aeronaves_crbm = $result_acft_crbm->fetch_assoc()['total'];
        }
        $stmt_acft_crbm->close();

        // Conta missões no CRBM do piloto
        $stmt_missoes_crbm = $conn->prepare("SELECT COUNT(m.id) AS total FROM missoes m JOIN aeronaves a ON m.aeronave_id = a.id WHERE a.crbm = ?");
        $stmt_missoes_crbm->bind_param("s", $crbm_do_usuario);
        if ($stmt_missoes_crbm->execute()) {
            $result_missoes_crbm = $stmt_missoes_crbm->get_result();
            $total_missoes = $result_missoes_crbm->fetch_assoc()['total'];
        }
        $stmt_missoes_crbm->close();
    }

    // Conta manutenções na OBM do piloto
    if (!empty($obm_do_usuario)) {
        $stmt_manutencoes = $conn->prepare("SELECT COUNT(m.id) AS total 
                                            FROM manutencoes m 
                                            LEFT JOIN aeronaves a ON m.equipamento_id = a.id AND m.equipamento_tipo = 'Aeronave'
                                            LEFT JOIN controles c ON m.equipamento_id = c.id AND m.equipamento_tipo = 'Controle'
                                            WHERE a.obm = ? OR c.obm = ?");
        $stmt_manutencoes->bind_param("ss", $obm_do_usuario, $obm_do_usuario);
        if ($stmt_manutencoes->execute()) {
            $result_manutencoes = $stmt_manutencoes->get_result();
            $total_manutencoes = $result_manutencoes->fetch_assoc()['total'];
        }
        $stmt_manutencoes->close();
    }
}


// ### LÓGICA PARA AS ÚLTIMAS MISSÕES ###
$ultimas_missoes = [];
// CORREÇÃO APLICADA AQUI: m.data_ocorrencia foi trocado para m.data
$sql_ultimas_missoes = "
    SELECT 
        m.id, m.data, m.total_tempo_voo,
        a.prefixo AS aeronave_prefixo,
        GROUP_CONCAT(CONCAT(p.posto_graduacao, ' ', p.nome_completo) 
            ORDER BY
                CASE p.posto_graduacao
                    WHEN 'Cel. QOBM' THEN 1 WHEN 'Ten. Cel. QOBM' THEN 2 WHEN 'Maj. QOBM' THEN 3
                    WHEN 'Cap. QOBM' THEN 4 WHEN '1º Ten. QOBM' THEN 5 WHEN '2º Ten. QOBM' THEN 6
                    WHEN 'Asp. Oficial' THEN 7 WHEN 'Sub. Ten. QPBM' THEN 8 WHEN '1º Sgt. QPBM' THEN 9
                    WHEN '2º Sgt. QPBM' THEN 10 WHEN '3º Sgt. QPBM' THEN 11 WHEN 'Cb. QPBM' THEN 12
                    WHEN 'Sd. QPBM' THEN 13 ELSE 14
                END
            SEPARATOR '<br>') AS pilotos_nomes
    FROM missoes m
    JOIN aeronaves a ON m.aeronave_id = a.id
    LEFT JOIN missoes_pilotos mp ON m.id = mp.missao_id
    LEFT JOIN pilotos p ON mp.piloto_id = p.id
    GROUP BY m.id
    ORDER BY m.data DESC, m.id DESC
    LIMIT 5
";
$result_ultimas_missoes = $conn->query($sql_ultimas_missoes);
if ($result_ultimas_missoes) {
    while($row = $result_ultimas_missoes->fetch_assoc()) {
        $ultimas_missoes[] = $row;
    }
}

function formatarTempoVoo($segundos) {
    if ($segundos <= 0) return '0min';
    $horas = floor($segundos / 3600);
    $minutos = floor(($segundos % 3600) / 60);
    $resultado = '';
    if ($horas > 0) $resultado .= $horas . 'h ';
    if ($minutos > 0) $resultado .= $minutos . 'min';
    return trim($resultado) ?: '0min';
}
?>

<style>
.dashboard-header { margin-bottom: 30px; }
.welcome-message { font-size: 1.1em; color: #555; margin-top: -15px; }
.dashboard-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 25px; margin-bottom: 40px; }
.card { background-color: #fff; padding: 25px; border-radius: 8px; box-shadow: 0 4px 15px rgba(0,0,0,.05); display: flex; align-items: center; gap: 20px; transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; }
.card:hover { transform: translateY(-5px); box-shadow: 0 8px 25px rgba(0,0,0,.08); }
.card-icon { font-size: 28px; width: 60px; height: 60px; border-radius: 50%; display: flex; align-items: center; justify-content: center; }
.card-content h2 { margin: 0 0 5px 0; font-size: 1em; color: #555; font-weight: 600; }
.card-content p { margin: 0; font-size: 2.2em; font-weight: 700; color: #2c3e50; }
.recent-missions h2 { display: flex; align-items: center; gap: 10px; color: #2c3e50; margin-bottom: 15px; }

@media (max-width: 768px) {
    .dashboard-cards {
        grid-template-columns: 1fr; /* Cards em coluna única */
    }
    .card {
        flex-direction: column;
        text-align: center;
    }
    .card-content h2 {
        font-size: 1.1em;
    }
}
</style>

<div class="main-content">
    <div class="dashboard-header">
        <h1>Dashboard</h1>
        <p class="welcome-message">Bem-vindo(a) de volta, <?php echo htmlspecialchars(explode(' ', $_SESSION['user_name'])[0]); ?>!</p>
    </div>

    <div class="dashboard-cards">
        <?php if ($isAdmin): ?>
            <div class="card">
                <div class="card-icon" style="background-color: #e6f7ff; color: #1890ff;"><i class="fas fa-users"></i></div>
                <div class="card-content">
                    <h2>Pilotos Cadastrados</h2>
                    <p><?php echo $total_pilotos; ?></p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon" style="background-color: #e9fbf0; color: #52c41a;"><i class="fas fa-plane"></i></div>
                <div class="card-content">
                    <h2>Aeronaves Cadastradas</h2>
                    <p><?php echo $total_aeronaves; ?></p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon" style="background-color: #fffbe6; color: #faad14;"><i class="fas fa-map-marked-alt"></i></div>
                <div class="card-content">
                    <h2>Missões Realizadas</h2>
                    <p><?php echo $total_missoes; ?></p>
                </div>
            </div>           
            <div class="card">
                 <div class="card-icon" style="background-color: #fce8e7; color: #f5222d;"><i class="fas fa-tools"></i></div>
                <div class="card-content">
                    <h2>Manutenções Registradas</h2>
                    <p><?php echo $total_manutencoes; ?></p>
                </div>
            </div>
        <?php elseif ($isPiloto): ?>
            <div class="card">
                <div class="card-icon" style="background-color: #e9fbf0; color: #52c41a;"><i class="fas fa-plane"></i></div>
                <div class="card-content">
                    <h2>Aeronaves no seu CRBM</h2>
                    <p><?php echo $total_aeronaves_crbm; ?></p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon" style="background-color: #fffbe6; color: #faad14;"><i class="fas fa-map-marked-alt"></i></div>
                <div class="card-content">
                    <h2>Missões (seu CRBM)</h2>
                    <p><?php echo $total_missoes; ?></p>
                </div>
            </div>
            <div class="card">
                <div class="card-icon" style="background-color: #fce8e7; color: #f5222d;"><i class="fas fa-tools"></i></div>
                <div class="card-content">
                    <h2>Manutenções (sua OBM)</h2>
                    <p><?php echo $total_manutencoes; ?></p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="recent-missions">
        <h2><i class="fas fa-history"></i> Últimas Missões Realizadas</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Aeronave</th>
                        <th>Piloto(s)</th>
                        <th>Data</th>
                        <th>Duração</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($ultimas_missoes)): ?>
                        <?php foreach ($ultimas_missoes as $missao): ?>
                            <tr>
                                <td style="text-align: center;"><?php echo htmlspecialchars($missao['aeronave_prefixo']); ?></td>
                                <td style="text-align: center;"><?php echo $missao['pilotos_nomes'] ?? 'N/A'; ?></td>
                                <td style="text-align: center;"><?php echo date("d/m/Y", strtotime($missao['data'])); ?></td>
                                <td style="text-align: center;"><?php echo formatarTempoVoo($missao['total_tempo_voo']); ?></td>
                                <td style="text-align: center;"><span class="status-ativo">Concluída</span></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5">Nenhuma missão registrada recentemente.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php
// 4. INCLUI O RODAPÉ (com scripts JS e fechamento do HTML)
require_once 'includes/footer.php';
?>