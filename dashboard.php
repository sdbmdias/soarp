<?php
// 1. INCLUI O CABEÇALHO E AS VERIFICAÇÕES DE SEGURANÇA
require_once 'includes/header.php';

// 2. LÓGICA ESPECÍFICA DO DASHBOARD
$total_pilotos = 0;
$total_aeronaves = 0;
$total_aeronaves_crbm = 0;
$crbm_do_usuario_logado = '';

// Busca a contagem total de aeronaves
$sql_total_aeronaves = "SELECT COUNT(id) AS total_aeronaves FROM aeronaves";
$result_total_aeronaves = $conn->query($sql_total_aeronaves);
if ($result_total_aeronaves->num_rows > 0) {
    $total_aeronaves = $result_total_aeronaves->fetch_assoc()['total_aeronaves'];
}

// Busca dados específicos dependendo do perfil do usuário
if ($isAdmin) {
    $sql_total_pilotos = "SELECT COUNT(id) AS total_pilotos FROM pilotos";
    $result_total_pilotos = $conn->query($sql_total_pilotos);
    if ($result_total_pilotos->num_rows > 0) {
        $total_pilotos = $result_total_pilotos->fetch_assoc()['total_pilotos'];
    }

} elseif ($isPiloto) {
    $stmt_crbm = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    $stmt_crbm->bind_param("i", $_SESSION['user_id']);
    if ($stmt_crbm->execute()) {
        $result_crbm = $stmt_crbm->get_result();
        if ($result_crbm->num_rows > 0) {
            $crbm_do_usuario_logado = $result_crbm->fetch_assoc()['crbm_piloto'];
        }
    }
    $stmt_crbm->close();

    if (!empty($crbm_do_usuario_logado)) {
        $stmt_acft_crbm = $conn->prepare("SELECT COUNT(id) AS total FROM aeronaves WHERE crbm = ?");
        $stmt_acft_crbm->bind_param("s", $crbm_do_usuario_logado);
        if ($stmt_acft_crbm->execute()) {
            $result_acft_crbm = $stmt_acft_crbm->get_result();
            $total_aeronaves_crbm = $result_acft_crbm->fetch_assoc()['total'];
        }
        $stmt_acft_crbm->close();
    }
}
?>

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
                <div class="card-icon" style="background-color: #fffbe6; color: #faad14;"><i class="fas fa-clock"></i></div>
                <div class="card-content">
                    <h2>Horas Voadas (Total)</h2>
                    <p>1245</p>
                </div>
            </div>           
            <div class="card">
                 <div class="card-icon" style="background-color: #fce8e7; color: #f5222d;"><i class="fas fa-calendar-check"></i></div>
                <div class="card-content">
                    <h2>Voos Realizados (Mês)</h2>
                    <p>87</p>
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
                <div class="card-icon" style="background-color: #e6f7ff; color: #1890ff;"><i class="fas fa-globe"></i></div>
                <div class="card-content">
                    <h2>Total de Aeronaves</h2>
                    <p><?php echo $total_aeronaves; ?></p>
                </div>
            </div>
            <div class="card">
                 <div class="card-icon" style="background-color: #fffbe6; color: #faad14;"><i class="fas fa-clock"></i></div>
                <div class="card-content">
                    <h2>Suas Horas de Voo</h2>
                    <p>1245</p>
                </div>
            </div>           
            <div class="card">
                <div class="card-icon" style="background-color: #fce8e7; color: #f5222d;"><i class="fas fa-calendar-check"></i></div>
                <div class="card-content">
                    <h2>Seus Voos (Mês)</h2>
                    <p>12</p>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <div class="recent-flights">
        <h2><i class="fas fa-history"></i> Últimos Voos Realizados</h2>
        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Drone</th>
                        <th>Piloto</th>
                        <th>Data</th>
                        <th>Duração</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <tr>
                        <td>Drone XYZ</td>
                        <td>João Silva</td>
                        <td>12/07/2025</td>
                        <td>2h 15min</td>
                        <td><span class="status-ativo">Concluído</span></td>
                    </tr>
                    </tbody>
            </table>
        </div>
    </div>
</div>

<style>
.dashboard-header {
    margin-bottom: 30px;
}
.welcome-message {
    font-size: 1.1em;
    color: #555;
    margin-top: -15px;
}
.dashboard-cards {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 25px;
    margin-bottom: 40px;
}
.card {
    background-color: #fff;
    padding: 25px;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,.05);
    display: flex;
    align-items: center;
    gap: 20px;
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}
.card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,.08);
}
.card-icon {
    font-size: 28px;
    width: 60px;
    height: 60px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
}
.card-content h2 {
    margin: 0 0 5px 0;
    font-size: 1em;
    color: #555;
    font-weight: 600;
}
.card-content p {
    margin: 0;
    font-size: 2.2em;
    font-weight: 700;
    color: #2c3e50;
}
.recent-flights h2 {
    display: flex;
    align-items: center;
    gap: 10px;
    color: #2c3e50;
    margin-bottom: 15px;
}
</style>

<?php
// 4. INCLUI O RODAPÉ (com scripts JS e fechamento do HTML)
require_once 'includes/footer.php';
?>