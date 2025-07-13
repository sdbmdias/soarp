<?php
session_start(); // Inicia a sessão

// Bloco de proteção para forçar redefinição de senha
if (isset($_SESSION['force_password_reset'])) {
    header('Location: primeiro_acesso.php');
    exit();
}

// Verifica se o usuário está logado
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php"); // Redireciona para a página de login
    exit();
}

// Define os tipos de usuário para controle de acesso
$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'administrador';
$isPiloto = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'piloto';

// Configurações do banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "drones_db";

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Falha na conexão com o banco de dados: " . $conn->connect_error);
}

// --- Início da verificação de Alertas ---
$existem_alertas = false;
$sql_alerts = "SELECT COUNT(id) AS total_alertas FROM aeronaves WHERE validade_sisant <= DATE_ADD(CURDATE(), INTERVAL 15 DAY)";
$result_alerts = $conn->query($sql_alerts);
if ($result_alerts && $result_alerts->num_rows > 0) {
    $row_alerts = $result_alerts->fetch_assoc();
    if ($row_alerts['total_alertas'] > 0) {
        $existem_alertas = true;
    }
}
// --- Fim da verificação de Alertas ---


$aeronaves = [];

// Lógica de filtragem por CRBM para pilotos
if ($isPiloto) {
    // 1. Busca o CRBM do piloto logado
    $crbm_do_usuario_logado = '';
    $stmt_crbm = $conn->prepare("SELECT crbm_piloto FROM pilotos WHERE id = ?");
    $stmt_crbm->bind_param("i", $_SESSION['user_id']);
    $stmt_crbm->execute();
    $result_crbm = $stmt_crbm->get_result();
    if ($result_crbm->num_rows > 0) {
        $crbm_do_usuario_logado = $result_crbm->fetch_assoc()['crbm_piloto'];
    }
    $stmt_crbm->close();

    // 2. Busca apenas as aeronaves do mesmo CRBM
    if (!empty($crbm_do_usuario_logado)) {
        $stmt_aeronaves = $conn->prepare("SELECT id, prefixo, fabricante, modelo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, status FROM aeronaves WHERE crbm = ? ORDER BY prefixo ASC");
        $stmt_aeronaves->bind_param("s", $crbm_do_usuario_logado);
        $stmt_aeronaves->execute();
        $result_aeronaves = $stmt_aeronaves->get_result();
        $stmt_aeronaves->close();
    }
} else {
    // Administradores veem todas as aeronaves
    $sql_aeronaves = "SELECT id, prefixo, fabricante, modelo, numero_serie, cadastro_sisant, validade_sisant, crbm, obm, tipo_drone, pmd_kg, status FROM aeronaves ORDER BY prefixo ASC";
    $result_aeronaves = $conn->query($sql_aeronaves);
}


if (isset($result_aeronaves) && $result_aeronaves->num_rows > 0) {
    while ($row = $result_aeronaves->fetch_assoc()) {
        $aeronaves[] = $row;
    }
}

$conn->close();

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR - Lista de Aeronaves</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body{font-family:Arial,sans-serif;margin:0;padding:0;display:flex;min-height:100vh;background-color:#f0f2f5;color:#333}.sidebar{width:250px;background-color:#2c3e50;color:#fff;padding-top:20px;box-shadow:2px 0 5px rgba(0,0,0,.1);display:flex;flex-direction:column;justify-content:space-between;align-items:center}.sidebar ul{list-style:none;padding:0;width:100%;flex-grow:1;margin-bottom:20px}.sidebar ul li a{display:flex;align-items:center;padding:15px 20px;color:#fff;text-decoration:none;transition:background-color .3s ease;font-size:16px;line-height:1.2}.sidebar ul li a i{margin-right:15px;font-size:20px;width:25px;text-align:center}.sidebar ul li a:hover,.sidebar ul li a.active{background-color:#34495e;border-left:5px solid #3498db;padding-left:15px}.menu-alert{color:#e74c3c!important;font-weight:700}.sidebar ul li.has-submenu>a{position:relative}.sidebar ul li.has-submenu>a .submenu-arrow{position:absolute;right:20px;transition:transform .3s ease}.sidebar ul li.has-submenu>a .submenu-arrow.fa-chevron-up{transform:rotate(180deg)}.sidebar ul li .submenu{list-style:none;padding:0;max-height:0;overflow:hidden;transition:max-height .3s ease-out;background-color:#34495e}.sidebar ul li .submenu li a{padding:10px 20px 10px 45px;font-size:.95em;background-color:transparent}.sidebar ul li .submenu li a:hover,.sidebar ul li .submenu li a.active{background-color:#3f5872;border-left:5px solid #3498db}.sidebar ul li .submenu.open{max-height:250px}.main-content{flex-grow:1;padding:30px;background-color:#f0f2f5}.main-content h1{color:#2c3e50;margin-bottom:30px}.table-container{background-color:#fff;padding:25px;border-radius:8px;box-shadow:0 2px 10px rgba(0,0,0,.08);overflow-x:auto}.data-table{width:100%;border-collapse:collapse;margin-top:15px}.data-table th,.data-table td{padding:12px 10px;border-bottom:1px solid #eee;text-align:left;color:#555;font-size:.9em}.data-table th{background-color:#f8f8f8;font-weight:700;color:#2c3e50}.data-table tbody tr:hover{background-color:#f5f5f5}.status-ativo{color:#28a745;font-weight:700}.status-em_manutencao{color:#ffc107;font-weight:700}.status-baixada{color:#dc3545;font-weight:700}.status-adida{color:#007bff;font-weight:700}.action-buttons a{display:inline-block;padding:5px 10px;margin-right:5px;border-radius:4px;text-decoration:none;color:#fff;font-size:.85em}.action-buttons .edit-btn{background-color:#007bff}.action-buttons .edit-btn:hover{background-color:#0056b3}
    </style>
</head>
<body>
    <div class="sidebar">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="listar_pilotos.php"><i class="fas fa-users"></i> Pilotos</a></li>
            <li><a href="listar_aeronaves.php" class="active"><i class="fas fa-plane"></i> Aeronaves</a></li>
            <li><a href="listar_controles.php"><i class="fas fa-gamepad"></i> Controles</a></li>
            <li><a href="manutencao.php"><i class="fas fa-tools"></i> Manutenção</a></li>
            <li><a href="#"><i class="fas fa-map-marked-alt"></i> Missões Realizadas</a></li>
            <li><a href="#"><i class="fas fa-file-pdf"></i> Relatório em PDF</a></li>
            
            <?php if ($isAdmin): ?>
            <li class="has-submenu">
                <a href="#" id="admin-menu-toggle"><i class="fas fa-user-shield"></i> Admin <i class="fas fa-chevron-down submenu-arrow"></i></a>
                <ul class="submenu">
                    <li><a href="cadastro_aeronaves.php"> Cadastro de Aeronaves</a></li>
                    <li><a href="cadastro_pilotos.php"> Cadastro de Pilotos</a></li>
                    <li><a href="cadastro_controles.php"> Cadastro de Controles</a></li>
                    <li><a href="alertas.php" class="<?php if ($existem_alertas) echo 'menu-alert'; ?>"> Alertas</a></li>
                </ul>
            </li>
            <?php endif; ?>

            <li><a href="index.php" style="color: #e74c3c;"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
        </ul>
    </div>

    <div class="main-content">
        <h1>Lista de Aeronaves</h1>

        <div class="table-container">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Prefixo</th>
                        <th>Fabricante/Modelo</th>
                        <th>Nº Série</th>
                        <th>SISANT (Val.)</th>
                        <th>Lotação (CRBM/OBM)</th> <th>Tipo</th>
                        <th>PMD (kg)</th>
                        <th>Status</th>
                        <?php if ($isAdmin): ?>
                        <th>Ações</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($aeronaves)): ?>
                        <?php foreach ($aeronaves as $aeronave): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($aeronave['prefixo'] ?? 'N/A'); ?></td>
                                <td><?php echo htmlspecialchars(($aeronave['fabricante'] ?? 'N/A') . ' / ' . ($aeronave['modelo'] ?? 'N/A')); ?></td>
                                <td><?php echo htmlspecialchars($aeronave['numero_serie'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php
                                    $sisant = $aeronave['cadastro_sisant'] ?? 'N/A';
                                    $validade = $aeronave['validade_sisant'] ?? null;
                                    $validade_formatada = $validade ? date("d/m/Y", strtotime($validade)) : 'N/A';
                                    echo htmlspecialchars($sisant) . ' (' . $validade_formatada . ')';
                                    ?>
                                </td>
                                <td><?php echo htmlspecialchars(($aeronave['crbm'] ?? 'N/A') . ' / ' . ($aeronave['obm'] ?? 'N/A')); ?></td> <td><?php echo htmlspecialchars(str_replace('_', '-', $aeronave['tipo_drone'] ?? 'N/A')); ?></td>
                                <td><?php echo htmlspecialchars($aeronave['pmd_kg'] ?? 'N/A'); ?></td>
                                <td>
                                    <?php 
                                    $status = $aeronave['status'] ?? 'desconhecido';
                                    $status_texto = ucfirst(str_replace('_', ' ', $status));
                                    ?>
                                    <span class="status-<?php echo htmlspecialchars($status); ?>"><?php echo htmlspecialchars($status_texto); ?></span>
                                </td>
                                <?php if ($isAdmin): ?>
                                <td class="action-buttons">
                                    <a href="editar_aeronaves.php?id=<?php echo $aeronave['id']; ?>" class="edit-btn">Editar</a>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?php echo $isAdmin ? '9' : '8'; ?>">Nenhuma aeronave cadastrada.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const adminMenuToggle = document.getElementById('admin-menu-toggle');
            const adminSubmenu = document.querySelector('.has-submenu .submenu');
            const submenuArrow = document.querySelector('.has-submenu .submenu-arrow');

            if (adminMenuToggle && adminSubmenu) {
                adminMenuToggle.addEventListener('click', function(event) {
                    event.preventDefault();
                    adminSubmenu.classList.toggle('open');
                    if (submenuArrow) {
                        submenuArrow.classList.toggle('fa-chevron-down');
                        submenuArrow.classList.toggle('fa-chevron-up');
                    }
                });
            }
        });
    </script>
</body>
</html>