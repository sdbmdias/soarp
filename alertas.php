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

// Define se o usuário é um administrador
$isAdmin = isset($_SESSION['user_type']) && $_SESSION['user_type'] == 'administrador';

// Configurações do banco de dados
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "drones_db";

// Arrays para armazenar os alertas
$alertas_proximos = [];
$alertas_vencidos = [];

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

                if ($dias_para_vencer <= 15) {
                    $aeronave['dias'] = $dias_para_vencer;
                    $alertas_proximos[] = $aeronave;
                }
            }
        }
    }
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR - Alertas de Vencimento</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 0;
            display: flex;
            min-height: 100vh;
            background-color: #f0f2f5;
            color: #333;
        }
        .sidebar {
            width: 250px;
            background-color: #2c3e50;
            color: white;
            padding-top: 20px;
            box-shadow: 2px 0 5px rgba(0, 0, 0, 0.1);
            display: flex;
            flex-direction: column;
            align-items: center;
        }
        .sidebar ul {
            list-style: none;
            padding: 0;
            width: 100%;
        }
        .sidebar ul li a {
            display: flex;
            align-items: center;
            padding: 15px 20px;
            color: white;
            text-decoration: none;
            transition: background-color 0.3s ease;
            font-size: 16px;
            line-height: 1.2;
        }
        .sidebar ul li a i {
            margin-right: 15px;
            font-size: 20px;
            width: 25px;
            text-align: center;
        }
        .sidebar ul li a:hover, .sidebar ul li a.active {
            background-color: #34495e;
            border-left: 5px solid #3498db;
            padding-left: 15px;
        }
        .menu-alert {
            color: #e74c3c !important; /* Vermelho para destaque */
            font-weight: bold;
        }
        .sidebar ul li.has-submenu > a {
            position: relative;
        }
        .sidebar ul li.has-submenu > a .submenu-arrow {
            position: absolute;
            right: 20px;
            transition: transform 0.3s ease;
        }
        .sidebar ul li.has-submenu > a .submenu-arrow.fa-chevron-up {
            transform: rotate(180deg);
        }
        .sidebar ul li .submenu {
            list-style: none;
            padding: 0;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
            background-color: #34495e;
        }
        .sidebar ul li .submenu li a {
            padding: 10px 20px 10px 45px;
            font-size: 0.95em;
            background-color: transparent;
        }
        .sidebar ul li .submenu li a:hover,
        .sidebar ul li .submenu li a.active {
            background-color: #3f5872;
            border-left: 5px solid #3498db;
        }
        .sidebar ul li .submenu.open {
            max-height: 250px;
        }
        .main-content {
            flex-grow: 1;
            padding: 30px;
            background-color: #f0f2f5;
        }
        .main-content h1 {
            color: #2c3e50;
            margin-bottom: 30px;
        }
        .alerts-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: auto;
        }
        .alerts-container h2 {
            color: #2c3e50;
            margin-bottom: 20px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .alert-list {
            list-style: none;
            padding: 0;
        }
        .alert-item {
            display: flex;
            align-items: center;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            border-left-width: 5px;
            border-left-style: solid;
        }
        .alert-item.proximo {
            background-color: #fff9e6;
            border-left-color: #ffc107;
        }
        .alert-item.vencido {
            background-color: #f8d7da;
            border-left-color: #dc3545;
        }
        .alert-item i {
            font-size: 24px;
            margin-right: 20px;
        }
        .alert-item.proximo i { color: #ffc107; }
        .alert-item.vencido i { color: #dc3545; }
        .alert-details {
            font-size: 1.1em;
            line-height: 1.5;
        }
        .alert-details strong {
            color: #2c3e50;
        }
        .no-alerts {
            text-align: center;
            padding: 20px;
            font-size: 1.2em;
            color: #555;
            background-color: #f9f9f9;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="listar_pilotos.php"><i class="fas fa-users"></i> Pilotos</a></li>
            <li><a href="listar_aeronaves.php"><i class="fas fa-plane"></i> Aeronaves</a></li>
            <li><a href="manutencao.php"><i class="fas fa-tools"></i> Manutenção</a></li>
            <li><a href="#"><i class="fas fa-map-marked-alt"></i> Missões Realizadas</a></li>
            <li><a href="#"><i class="fas fa-file-pdf"></i> Relatório em PDF</a></li>
            
            <?php if ($isAdmin): ?>
            <li class="has-submenu">
                <a href="#" id="admin-menu-toggle"><i class="fas fa-user-shield"></i> Admin <i class="fas fa-chevron-down submenu-arrow"></i></a>
                <ul class="submenu">
                    <li><a href="cadastro_aeronaves.php"> Cadastro de Aeronaves</a></li>
                    <li><a href="cadastro_pilotos.php"> Cadastro de Pilotos</a></li>
                    <li><a href="alertas.php" class="active <?php if ($existem_alertas) echo 'menu-alert'; ?>"> Alertas</a></li>
                </ul>
            </li>
            <?php endif; ?>
            
            <li><a href="index.php" style="color: #e74c3c;"><i class="fas fa-sign-out-alt"></i> Sair</a></li>
        </ul>
    </div>

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
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const adminMenuToggle = document.getElementById('admin-menu-toggle');
            const adminSubmenu = document.querySelector('.has-submenu .submenu');
            const submenuArrow = document.querySelector('.has-submenu .submenu-arrow');

            if (adminMenuToggle && adminSubmenu) {
                // Abre o submenu se a página ativa estiver dentro dele
                if (adminSubmenu.querySelector('a.active')) {
                    adminSubmenu.classList.add('open');
                    if (submenuArrow) {
                        submenuArrow.classList.remove('fa-chevron-down');
                        submenuArrow.classList.add('fa-chevron-up');
                    }
                    adminMenuToggle.classList.add('active');
                }

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