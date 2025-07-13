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

// --- Início da verificação de Alertas ---
$existem_alertas = false;
$conn_alerts = new mysqli($servername, $username, $password, $dbname);
if (!$conn_alerts->connect_error) {
    $sql_alerts = "SELECT COUNT(id) AS total_alertas FROM aeronaves WHERE validade_sisant <= DATE_ADD(CURDATE(), INTERVAL 15 DAY)";
    $result_alerts = $conn_alerts->query($sql_alerts);
    if ($result_alerts && $result_alerts->num_rows > 0) {
        $row_alerts = $result_alerts->fetch_assoc();
        if ($row_alerts['total_alertas'] > 0) {
            $existem_alertas = true;
        }
    }
    $conn_alerts->close();
}
// --- Fim da verificação de Alertas ---

?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SOARP - CBMPR - Manutenção de Aeronaves</title>
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

        .sidebar ul li {
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
            color: #333;
        }

        .main-content h1 {
            color: #2c3e50;
            margin-bottom: 30px;
        }

        .maintenance-container {
            background-color: #ffffff;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            max-width: 900px;
            margin: auto;
        }

        .maintenance-container h2 {
            color: #2c3e50;
            margin-bottom: 20px;
        }

        .maintenance-container p {
            font-size: 1.1em;
            line-height: 1.6;
        }

        .maintenance-list {
            list-style: none;
            padding: 0;
            margin-top: 20px;
        }

        .maintenance-list li {
            background-color: #f9f9f9;
            border: 1px solid #eee;
            padding: 15px;
            margin-bottom: 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .maintenance-list li strong {
            color: #3498db;
        }

        .maintenance-actions button {
            background-color: #007bff;
            color: white;
            padding: 8px 15px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }

        .maintenance-actions button:hover {
            background-color: #0056b3;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <ul>
            <li><a href="dashboard.php"><i class="fas fa-chart-line"></i> Dashboard</a></li>
            <li><a href="listar_pilotos.php"><i class="fas fa-users"></i> Pilotos</a></li>
            <li><a href="listar_aeronaves.php"><i class="fas fa-plane"></i> Aeronaves</a></li>
            <li><a href="listar_controles.php"><i class="fas fa-gamepad"></i> Controles</a></li>
            <li><a href="manutencao.php" class="active"><i class="fas fa-tools"></i> Manutenção</a></li>
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
        <h1>Manutenção de Aeronaves</h1>

        <div class="maintenance-container">
            <h2>Próximas Manutenções Agendadas</h2>
            <ul class="maintenance-list">
                <li>
                    <div>
                        <strong>Drone:</strong> HAWK 05 (DJI Mavic 3 Thermal) <br>
                        <strong>Tipo de Manutenção:</strong> Revisão Anual Obrigatória <br>
                        <strong>Data Prevista:</strong> 25/08/2025
                    </div>
                    <div class="maintenance-actions">
                        <button>Ver Detalhes</button>
                    </div>
                </li>
                <li>
                    <div>
                        <strong>Drone:</strong> HAWK 12 (Autel EVO Max 4T) <br>
                        <strong>Tipo de Manutenção:</strong> Troca de Baterias Principais <br>
                        <strong>Data Prevista:</strong> 10/09/2025
                    </div>
                    <div class="maintenance-actions">
                        <button>Ver Detalhes</button>
                    </div>
                </li>
                <li>
                    <div>
                        <strong>Drone:</strong> HAWK 01 (DJI Air 3) <br>
                        <strong>Tipo de Manutenção:</strong> Calibração de Sensores <br>
                        <strong>Data Prevista:</strong> 01/10/2025
                    </div>
                    <div class="maintenance-actions">
                        <button>Ver Detalhes</button>
                    </div>
                </li>
            </ul>

            <h2 style="margin-top: 40px;">Histórico de Manutenções</h2>
            <p>Esta seção exibirá um histórico detalhado de todas as manutenções realizadas em cada aeronave.</p>
            <h2 style="margin-top: 40px;">Registrar Nova Manutenção</h2>
            <p>Formulário para registrar uma nova manutenção realizada ou agendada.</p>
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