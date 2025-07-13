<?php
// 1. INCLUI O CABEÇALHO E AS VERIFICAÇÕES DE SEGURANÇA
// Esta única linha substitui a verificação de sessão, conexão com o banco, 
// definição de perfis e a lógica de alertas.
require_once 'includes/header.php';

// 2. LÓGICA ESPECÍFICA DO DASHBOARD
// Apenas o código que esta página realmente precisa.
$total_pilotos = 0;
$total_aeronaves = 0;
$total_aeronaves_crbm = 0;
$crbm_do_usuario_logado = '';

// Busca a contagem total de aeronaves (usada por ambos os perfis)
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
    // Busca o CRBM do piloto logado para filtrar os dados
    $stmt_crbm = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    $stmt_crbm->bind_param("i", $_SESSION['user_id']);
    if ($stmt_crbm->execute()) {
        $result_crbm = $stmt_crbm->get_result();
        if ($result_crbm->num_rows > 0) {
            $crbm_do_usuario_logado = $result_crbm->fetch_assoc()['crbm_piloto'];
        }
    }
    $stmt_crbm->close();

    // Conta as aeronaves apenas do CRBM do piloto
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
    <h1>Dashboard</h1>

    <div class="dashboard-cards">
        <?php if ($isAdmin): ?>
            <div class="card">
                <h2>Horas Voadas (Total)</h2>
                <p>1245 <small>horas</small></p>
            </div>           
            <div class="card">
                <h2>Voos Realizados (Mês)</h2>
                <p>87 <small>voos</small></p>
            </div>
            <div class="card pilots">
                <h2>Pilotos Cadastrados</h2>
                <p><?php echo $total_pilotos; ?> <small>pilotos</small></p>
            </div>
            <div class="card aeronaves">
                <h2>Aeronaves Cadastradas</h2>
                <p><?php echo $total_aeronaves; ?> <small>aeronaves</small></p>
            </div>

        <?php elseif ($isPiloto): ?>
            <div class="card">
                <h2>Horas Voadas (Total)</h2>
                <p>1245 <small>horas</small></p>
            </div>           
            <div class="card">
                <h2>Voos Realizados (Mês)</h2>
                <p>87 <small>voos</small></p>
            </div>
            <div class="card aeronaves">
                <h2>Aeronaves no CRBM</h2>
                <p><?php echo $total_aeronaves_crbm; ?> <small>aeronaves</small></p>
            </div>
            <div class="card aeronaves">
                <h2>Aeronaves no CBMPR</h2>
                <p><?php echo $total_aeronaves; ?> <small>aeronaves</small></p>
            </div>
        <?php endif; ?>
    </div>

    <div class="recent-flights">
        <h2>Últimos Voos Realizados</h2>
        <table>
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
                    <td>Concluído</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<?php
// 4. INCLUI O RODAPÉ (com scripts JS e fechamento do HTML)
require_once 'includes/footer.php';
?>